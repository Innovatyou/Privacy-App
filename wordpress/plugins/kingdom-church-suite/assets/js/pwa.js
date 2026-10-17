/* global KCS_PWA */
/**
 * Church app: service worker, install prompts (Android + iPhone), push notifications and
 * app-like behaviour when opened from the home screen.
 */
(function () {
	'use strict';
	if (typeof KCS_PWA === 'undefined') { return; }

	var cfg = KCS_PWA;
	var t = cfg.i18n || {};
	var doc = document;
	var root = doc.documentElement;
	var ua = navigator.userAgent || '';
	var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	var isSafari = isIOS && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
	var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
	var deferred = null;
	var registration = null;

	function store(key, value) {
		try {
			if (typeof value === 'undefined') { return localStorage.getItem('kcs-app-' + key); }
			localStorage.setItem('kcs-app-' + key, value);
		} catch (e) { return null; }
		return null;
	}

	function el(tag, cls, html) {
		var n = doc.createElement(tag);
		if (cls) { n.className = cls; }
		if (html) { n.innerHTML = html; }
		return n;
	}

	function esc(s) {
		return String(s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}

	/* Toasts ------------------------------------------------------------------------------ */

	var toastBox = null;
	function toast(message, action, onAction, ms) {
		if (!toastBox) {
			toastBox = el('div', 'kcs-app-toasts');
			toastBox.setAttribute('role', 'status');
			toastBox.setAttribute('aria-live', 'polite');
			doc.body.appendChild(toastBox);
		}
		var n = el('div', 'kcs-app-toast', '<span>' + esc(message) + '</span>');
		if (action) {
			var b = el('button', 'kcs-app-toast__btn');
			b.type = 'button';
			b.textContent = action;
			b.addEventListener('click', function () { n.remove(); onAction(); });
			n.appendChild(b);
		}
		toastBox.appendChild(n);
		if (ms !== 0) { setTimeout(function () { n.classList.add('is-out'); setTimeout(function () { n.remove(); }, 300); }, ms || 3500); }
		return n;
	}

	/* Bottom sheet ------------------------------------------------------------------------ */

	function sheet(html, onClose) {
		var wrap = el('div', 'kcs-app-sheet');
		wrap.innerHTML = '<div class="kcs-app-sheet__backdrop" data-close></div><div class="kcs-app-sheet__panel" role="dialog" aria-modal="true">' + html + '</div>';
		function close(why) {
			wrap.classList.remove('is-open');
			setTimeout(function () { wrap.remove(); }, 250);
			doc.removeEventListener('keydown', onKey);
			if (onClose) { onClose(why); }
		}
		function onKey(e) { if (e.key === 'Escape') { close('dismiss'); } }
		wrap.addEventListener('click', function (e) {
			if (e.target.closest('[data-close]')) { close('dismiss'); }
		});
		doc.addEventListener('keydown', onKey);
		doc.body.appendChild(wrap);
		requestAnimationFrame(function () { wrap.classList.add('is-open'); });
		var first = wrap.querySelector('button');
		if (first) { setTimeout(function () { first.focus(); }, 50); }
		return { el: wrap, close: close };
	}

	function appIcon() {
		return '<img class="kcs-app-sheet__icon" src="' + esc(cfg.icon) + '" alt="" width="56" height="56">';
	}

	/* Service worker ---------------------------------------------------------------------- */

	function registerSW() {
		if (!('serviceWorker' in navigator)) { return; }
		navigator.serviceWorker.register(cfg.sw, { scope: cfg.scope }).then(function (reg) {
			registration = reg;
			function watch(worker) {
				worker.addEventListener('statechange', function () {
					if (worker.state === 'installed' && navigator.serviceWorker.controller) {
						toast(t.update, t.reload, function () { worker.postMessage('skipWaiting'); }, 0);
					}
				});
			}
			if (reg.waiting && navigator.serviceWorker.controller) {
				toast(t.update, t.reload, function () { reg.waiting.postMessage('skipWaiting'); }, 0);
			}
			reg.addEventListener('updatefound', function () { if (reg.installing) { watch(reg.installing); } });
			syncPush();
			updatePushButtons();
		}).catch(function () {});

		// Reload only when an update replaces a running app, not when the app is first installed.
		var refreshing = false;
		var hadController = !!navigator.serviceWorker.controller;
		navigator.serviceWorker.addEventListener('controllerchange', function () {
			if (refreshing || !hadController) { hadController = true; return; }
			refreshing = true;
			window.location.reload();
		});
	}

	/* Install: Android (and desktop Chrome/Edge) ------------------------------------------ */

	function canInstall() { return !standalone && (deferred || isSafari); }

	function updateInstallButtons() {
		doc.querySelectorAll('[data-kcs-install]').forEach(function (b) { b.hidden = !canInstall(); });
	}

	function install() {
		if (deferred) {
			deferred.prompt();
			deferred.userChoice.then(function (choice) {
				deferred = null;
				updateInstallButtons();
				if (choice && choice.outcome === 'accepted') { store('installed', '1'); }
			});
			return;
		}
		if (isSafari) { iosGuide(); }
	}

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferred = e;
		updateInstallButtons();
		maybeBanner();
	});

	window.addEventListener('appinstalled', function () {
		deferred = null;
		store('installed', '1');
		updateInstallButtons();
	});

	/* Install: iPhone / iPad (Safari has no install prompt, so show how) ------------------ */

	var shareIcon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2.5l4.2 4.2-1.4 1.4L13 6.3V15h-2V6.3L9.2 8.1 7.8 6.7 12 2.5zM5 10h3v2H6v8h12v-8h-2v-2h3a1 1 0 011 1v10a1 1 0 01-1 1H5a1 1 0 01-1-1V11a1 1 0 011-1z"/></svg>';
	var addIcon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2zm0 2v14h14V5H5zm6 3h2v3h3v2h-3v3h-2v-3H8v-2h3V8z"/></svg>';

	function iosGuide() {
		var s = sheet(
			'<div class="kcs-app-sheet__head">' + appIcon() + '<div><h2>' + esc(t.ios_title) + '</h2><p>' + esc(cfg.name) + '</p></div></div>' +
			'<ol class="kcs-app-steps">' +
			'<li><span class="kcs-app-steps__icon">' + shareIcon + '</span>' + esc(t.ios_step1) + '</li>' +
			'<li><span class="kcs-app-steps__icon">' + addIcon + '</span>' + esc(t.ios_step2) + '</li>' +
			'<li><span class="kcs-app-steps__icon kcs-app-steps__icon--text">Add</span>' + esc(t.ios_step3) + '</li>' +
			'</ol>' +
			'<div class="kcs-app-sheet__actions"><button type="button" class="kcs-app-btn" data-close>' + esc(t.got_it) + '</button></div>' +
			'<div class="kcs-app-sheet__arrow" aria-hidden="true"></div>',
			function () { store('dismissed', String(Date.now())); }
		);
		s.el.classList.add('kcs-app-sheet--ios');
	}

	/* Install banner (once, after a little browsing, never again for 3 weeks if dismissed) */

	var bannerShown = false;
	function maybeBanner() {
		if (!cfg.banner || bannerShown || !canInstall() || store('installed')) { return; }
		var dismissed = parseInt(store('dismissed') || '0', 10);
		if (dismissed && Date.now() - dismissed < 21 * 864e5) { return; }
		var visits = parseInt(store('visits') || '0', 10);
		if (visits < 2) { return; }
		bannerShown = true;
		setTimeout(function () {
			var b = el('div', 'kcs-app-banner');
			b.setAttribute('role', 'dialog');
			b.setAttribute('aria-label', t.install_title);
			b.innerHTML = appIcon() +
				'<div class="kcs-app-banner__text"><strong>' + esc(t.install_title) + '</strong><span>' + esc(t.install_text) + '</span></div>' +
				'<div class="kcs-app-banner__actions"><button type="button" class="kcs-app-btn" data-go>' + esc(t.install) + '</button>' +
				'<button type="button" class="kcs-app-btn kcs-app-btn--ghost" data-no>' + esc(t.not_now) + '</button></div>';
			doc.body.appendChild(b);
			requestAnimationFrame(function () { b.classList.add('is-open'); });
			function close() { b.classList.remove('is-open'); setTimeout(function () { b.remove(); }, 300); }
			b.querySelector('[data-go]').addEventListener('click', function () { close(); install(); });
			b.querySelector('[data-no]').addEventListener('click', function () { close(); store('dismissed', String(Date.now())); });
		}, 2500);
	}

	/* Push notifications ------------------------------------------------------------------ */

	function pushSupported() {
		return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && !!cfg.vapid;
	}

	function keyBytes(b64) {
		var pad = '='.repeat((4 - (b64.length % 4)) % 4);
		var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
		var out = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
		return out;
	}

	function post(url, body) {
		var headers = { 'Content-Type': 'application/json' };
		// Only send the nonce for logged-in members: saved/offline pages can carry an old one.
		if (doc.body.classList.contains('logged-in') && cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
		return fetch(url, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify(body) }).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.json();
		});
	}

	function currentSub() {
		if (!pushSupported()) { return Promise.resolve(null); }
		return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); });
	}

	function subscribe() {
		return Notification.requestPermission().then(function (perm) {
			if (perm !== 'granted') { throw new Error(perm); }
			return navigator.serviceWorker.ready;
		}).then(function (reg) {
			return reg.pushManager.getSubscription().then(function (sub) {
				return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(cfg.vapid) });
			});
		}).then(function (sub) {
			return post(cfg.subscribe, sub.toJSON()).then(function () {
				store('push', '1');
				store('push-ep', sub.endpoint);
				return sub;
			});
		});
	}

	function unsubscribe() {
		return currentSub().then(function (sub) {
			if (!sub) { return null; }
			var ep = sub.endpoint;
			return sub.unsubscribe().then(function () { return post(cfg.unsub, { endpoint: ep }).catch(function () {}); });
		}).then(function () { store('push', '0'); });
	}

	// Re-send the subscription if the browser renewed it (or the server lost it).
	function syncPush() {
		currentSub().then(function (sub) {
			if (!sub || Notification.permission !== 'granted') { return; }
			var last = parseInt(store('push-sync') || '0', 10);
			if (store('push-ep') === sub.endpoint && Date.now() - last < 7 * 864e5) { return; }
			post(cfg.subscribe, sub.toJSON()).then(function () {
				store('push-ep', sub.endpoint);
				store('push-sync', String(Date.now()));
			}).catch(function () {});
		}).catch(function () {});
	}

	function updatePushButtons() {
		var buttons = doc.querySelectorAll('[data-kcs-push]');
		if (!buttons.length) { return; }
		// iPhone only allows notifications for apps added to the Home Screen (iOS 16.4+).
		var show = pushSupported() || (isIOS && !standalone);
		currentSub().then(function (sub) {
			buttons.forEach(function (b) {
				b.hidden = !show;
				b.setAttribute('aria-pressed', sub ? 'true' : 'false');
				b.classList.toggle('is-on', !!sub);
				var label = b.querySelector('[data-kcs-push-label]');
				if (label) { label.textContent = sub ? (b.getAttribute('data-on-label') || t.notify_done) : (b.getAttribute('data-off-label') || t.notify_on); }
			});
		}).catch(function () {});
	}

	function togglePush() {
		if (!pushSupported()) {
			if (isIOS && !standalone) { toast(t.notify_ios, isSafari ? t.install : null, iosGuide, 6000); }
			return;
		}
		if (Notification.permission === 'denied') { toast(t.notify_denied, null, null, 6000); return; }
		currentSub().then(function (sub) {
			if (sub) {
				return unsubscribe().then(function () { toast(t.notify_off); });
			}
			return subscribe().then(function () { toast(t.notify_done); });
		}).catch(function (e) {
			if (e && e.message === 'denied') { toast(t.notify_denied, null, null, 6000); }
		}).then(updatePushButtons);
	}

	// In the installed app, gently offer notifications once (after the app has been used a bit).
	function maybeAskPush() {
		if (!standalone || !pushSupported() || Notification.permission !== 'default' || store('push-asked')) { return; }
		setTimeout(function () {
			store('push-asked', '1');
			var s = sheet(
				'<div class="kcs-app-sheet__head">' + appIcon() + '<div><h2>' + esc(t.notify_title) + '</h2><p>' + esc(t.notify_text) + '</p></div></div>' +
				'<div class="kcs-app-sheet__actions"><button type="button" class="kcs-app-btn" data-yes>' + esc(t.notify_on) + '</button>' +
				'<button type="button" class="kcs-app-btn kcs-app-btn--ghost" data-close>' + esc(t.not_now) + '</button></div>'
			);
			s.el.querySelector('[data-yes]').addEventListener('click', function () {
				s.close('yes');
				subscribe().then(function () { toast(t.notify_done); }).catch(function () {}).then(updatePushButtons);
			});
		}, 4000);
	}

	/* App mode (opened from the home screen) ---------------------------------------------- */

	function appMode() {
		root.classList.add('is-app');
		if (isIOS) { root.classList.add('is-app-ios'); }

		// Back button: home-screen apps have no browser back button.
		var path = location.pathname.replace(/\/+$/, '/') ;
		var home = cfg.scope.replace(/\/+$/, '/');
		if (path !== home) {
			var back = el('button', 'kcs-app-back', '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M15.4 5.4L14 4l-8 8 8 8 1.4-1.4L8.8 12z"/></svg>');
			back.type = 'button';
			back.setAttribute('aria-label', t.back);
			back.addEventListener('click', function () {
				if (history.length > 1 && doc.referrer && doc.referrer.indexOf(location.origin) === 0) { history.back(); } else { location.href = cfg.scope; }
			});
			// Like a native app bar: inside the site header when there is one, else floating.
			var bar0 = doc.querySelector('[data-kcs-app-bar], .k-header__inner');
			if (bar0) { back.classList.add('kcs-app-back--inline'); bar0.insertBefore(back, bar0.firstChild); } else { doc.body.appendChild(back); }
		}

		// Loading bar for page changes, so taps feel instant.
		var bar = el('div', 'kcs-app-progress');
		doc.body.appendChild(bar);
		doc.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('a[href]');
			if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || a.target === '_blank' || a.hasAttribute('download')) { return; }
			var u;
			try { u = new URL(a.href, location.href); } catch (err) { return; }
			if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search && u.hash)) { return; }
			bar.classList.add('is-loading');
		});
		window.addEventListener('pageshow', function () { bar.classList.remove('is-loading'); });

		// External links open outside the app instead of trapping people.
		doc.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('a[href^="http"]');
			if (a && a.origin !== location.origin && !a.target) { a.target = '_blank'; a.rel = 'noopener'; }
		}, true);

		pullToRefresh();
		maybeAskPush();
	}

	function pullToRefresh() {
		var startY = 0;
		var pulling = false;
		var dist = 0;
		var ind = el('div', 'kcs-app-ptr', '<span class="kcs-app-ptr__spin" aria-hidden="true"></span>');
		doc.body.appendChild(ind);
		doc.addEventListener('touchstart', function (e) {
			if (window.scrollY > 0 || e.touches.length !== 1 || doc.querySelector('.kcs-app-sheet, .is-nav-open, [aria-modal="true"]:not([hidden])')) { pulling = false; return; }
			startY = e.touches[0].clientY;
			pulling = true;
			dist = 0;
		}, { passive: true });
		doc.addEventListener('touchmove', function (e) {
			if (!pulling) { return; }
			dist = Math.max(0, Math.min(120, (e.touches[0].clientY - startY) * 0.5));
			if (window.scrollY > 0) { dist = 0; }
			ind.style.transform = 'translate(-50%,' + (dist - 48) + 'px) rotate(' + dist * 3 + 'deg)';
			ind.classList.toggle('is-ready', dist >= 70);
		}, { passive: true });
		doc.addEventListener('touchend', function () {
			if (!pulling) { return; }
			pulling = false;
			if (dist >= 70) {
				ind.classList.add('is-refreshing');
				window.location.reload();
				return;
			}
			ind.style.transform = '';
			ind.classList.remove('is-ready');
		});
	}

	/* Connection status ------------------------------------------------------------------- */

	function connection() {
		function set() { root.classList.toggle('is-offline', !navigator.onLine); }
		set();
		window.addEventListener('offline', function () { set(); toast(t.offline, null, null, 4000); });
		window.addEventListener('online', function () { set(); toast(t.online, null, null, 2500); });
	}

	/* Start ------------------------------------------------------------------------------- */

	function start() {
		store('visits', String(parseInt(store('visits') || '0', 10) + 1));
		if (standalone) { appMode(); }
		connection();
		registerSW();
		doc.addEventListener('click', function (e) {
			var i = e.target.closest && e.target.closest('[data-kcs-install]');
			if (i) { e.preventDefault(); install(); return; }
			var p = e.target.closest && e.target.closest('[data-kcs-push]');
			if (p) { e.preventDefault(); togglePush(); }
		});
		updateInstallButtons();
		updatePushButtons();
		if (isSafari && !standalone) { maybeBanner(); }
	}

	if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', start); } else { start(); }

	window.KCSApp = { install: install, notifications: togglePush, guide: iosGuide };
}());
