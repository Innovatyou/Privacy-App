/* global KCS, self, caches, clients */
/**
 * Church app service worker. KCS (version, offline page, files to save, paths never to cache)
 * is printed above this file by KCS_PWA::service_worker().
 *
 * - Pages: network first (fast timeout), saved copy when offline, offline page as last resort.
 * - Theme/plugin files and images: served from cache, refreshed in the background.
 * - Push notifications: shows them and opens the right page when tapped.
 */
'use strict';

var PAGES = 'kcs-pages';
var STATIC = 'kcs-static-' + KCS.version;
var SHELL = 'kcs-shell-' + KCS.version;
var FONTS = 'kcs-fonts';
var KEEP = [PAGES, STATIC, SHELL, FONTS];
var MAX_PAGES = 40;
var MAX_STATIC = 120;
var TIMEOUT = 4000;

self.addEventListener('install', function (event) {
	event.waitUntil(
		caches.open(SHELL).then(function (cache) {
			return Promise.all(KCS.precache.map(function (url) {
				return cache.add(new Request(url, { credentials: 'same-origin' })).catch(function () {});
			}));
		})
	);
});

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (k) {
				return k.indexOf('kcs-') === 0 && KEEP.indexOf(k) === -1;
			}).map(function (k) { return caches.delete(k); }));
		}).then(function () {
			// No navigation preload: it would request pages this worker skips (dashboards, one-time
			// links) a second time.
			return self.registration.navigationPreload ? self.registration.navigationPreload.disable().catch(function () {}) : null;
		}).then(function () { return self.clients.claim(); })
	);
});

self.addEventListener('message', function (event) {
	if (event.data === 'skipWaiting') {
		self.skipWaiting();
	}
});

function never(url) {
	var s = url.pathname + url.search;
	return KCS.never.some(function (p) { return s.indexOf(p) !== -1; });
}

function trim(name, max) {
	return caches.open(name).then(function (cache) {
		return cache.keys().then(function (keys) {
			if (keys.length <= max) { return null; }
			return Promise.all(keys.slice(0, keys.length - max).map(function (k) { return cache.delete(k); }));
		});
	});
}

function timeout(promise, ms) {
	return new Promise(function (resolve, reject) {
		var t = setTimeout(function () { reject(new Error('timeout')); }, ms);
		promise.then(function (r) { clearTimeout(t); resolve(r); }, function (e) { clearTimeout(t); reject(e); });
	});
}

function offlineFallback(request) {
	return caches.match(request, { ignoreVary: true }).then(function (hit) {
		return hit || caches.match(KCS.offline).then(function (page) {
			return page || new Response('<h1>Offline</h1>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
		});
	});
}

function page(event) {
	var request = event.request;
	var network = fetch(request).then(function (res) {
		// Only public pages the site marked as safe to save (never dashboards or logged-in views).
		if (res && res.ok && res.type === 'basic' && res.headers.get('X-KCS-Offline') === '1') {
			var copy = res.clone();
			event.waitUntil(caches.open(PAGES).then(function (c) { return c.put(request, copy); }).then(function () { return trim(PAGES, MAX_PAGES); }));
		}
		return res;
	});
	// Wait for the network, but if it is slow and we have a saved copy, show that instead.
	return timeout(network, TIMEOUT).catch(function () {
		return caches.match(request, { ignoreVary: true }).then(function (hit) {
			return hit || network.catch(function () { return offlineFallback(request); });
		});
	}).catch(function () { return offlineFallback(request); });
}

function staleWhileRevalidate(event, name) {
	var request = event.request;
	return caches.open(name).then(function (cache) {
		return cache.match(request).then(function (hit) {
			var refresh = fetch(request).then(function (res) {
				if (res && (res.ok || res.type === 'opaque')) {
					cache.put(request, res.clone()).then(function () { return trim(name, MAX_STATIC); });
				}
				return res;
			});
			if (hit) {
				event.waitUntil(refresh.catch(function () {}));
				return hit;
			}
			return refresh;
		});
	});
}

function cacheFirst(request, name) {
	return caches.open(name).then(function (cache) {
		return cache.match(request).then(function (hit) {
			return hit || fetch(request).then(function (res) {
				if (res && (res.ok || res.type === 'opaque')) { cache.put(request, res.clone()); }
				return res;
			});
		});
	});
}

self.addEventListener('fetch', function (event) {
	var request = event.request;
	if (request.method !== 'GET') { return; }
	var url = new URL(request.url);
	if (url.protocol.indexOf('http') !== 0) { return; }

	if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
		event.respondWith(cacheFirst(request, FONTS));
		return;
	}
	if (url.origin !== self.location.origin || never(url)) { return; }

	if (request.mode === 'navigate') {
		event.respondWith(page(event));
		return;
	}
	if (url.searchParams.get('kcs_pwa')) {
		event.respondWith(caches.match(request).then(function (hit) { return hit || fetch(request); }));
		return;
	}
	if (/\.(?:css|js|woff2?|ttf|png|jpe?g|gif|webp|avif|svg|ico)$/i.test(url.pathname)) {
		event.respondWith(staleWhileRevalidate(event, STATIC));
	}
});

/* Push notifications ------------------------------------------------------------------- */

self.addEventListener('push', function (event) {
	var data = {};
	try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }
	var title = data.title || 'Church';
	event.waitUntil(self.registration.showNotification(title, {
		body: data.body || '',
		icon: data.icon || undefined,
		badge: data.badge || data.icon || undefined,
		image: data.image || undefined,
		tag: data.tag || undefined,
		renotify: !!data.tag,
		data: { url: data.url || self.registration.scope }
	}));
});

self.addEventListener('notificationclick', function (event) {
	event.notification.close();
	var target = new URL((event.notification.data && event.notification.data.url) || self.registration.scope, self.registration.scope);
	if (target.origin !== self.location.origin) { target = new URL(self.registration.scope); }
	event.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
			for (var i = 0; i < list.length; i++) {
				var c = list[i];
				if ('focus' in c && new URL(c.url).origin === target.origin) {
					return c.focus().then(function (w) { return w && 'navigate' in w ? w.navigate(target.href) : w; });
				}
			}
			return clients.openWindow ? clients.openWindow(target.href) : null;
		})
	);
});

self.addEventListener('pushsubscriptionchange', function (event) {
	// The browser renewed the subscription; the page re-sends it next time the app opens.
	event.waitUntil(Promise.resolve());
});
