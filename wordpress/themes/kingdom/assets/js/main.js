/**
 * Kingdom Church theme interactions (no dependencies).
 */
(function () {
	'use strict';

	var root = document.documentElement;
	root.classList.add('js');

	/* Sticky header shadow / transparent header switch */
	var header = document.querySelector('[data-header]');
	var toTop = document.querySelector('[data-to-top]');
	function onScroll() {
		var y = window.scrollY;
		if (header) { header.classList.toggle('is-scrolled', y > 40); }
		if (toTop) { toTop.classList.toggle('is-visible', y > 600); }
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
	if (toTop) { toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); }

	/* Mobile navigation */
	var navToggle = document.querySelector('[data-nav-toggle]');
	if (navToggle) {
		var closeNav = function () {
			document.body.classList.remove('is-nav-open');
			navToggle.setAttribute('aria-expanded', 'false');
		};
		navToggle.addEventListener('click', function () {
			var open = document.body.classList.toggle('is-nav-open');
			navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeNav(); } });
		document.addEventListener('click', function (e) {
			if (document.body.classList.contains('is-nav-open') && !e.target.closest('[data-nav]') && !e.target.closest('[data-nav-toggle]')) { closeNav(); }
		});
	}

	/* Search panel */
	var searchToggle = document.querySelector('[data-search-toggle]');
	var searchPanel = document.querySelector('[data-search-panel]');
	if (searchToggle && searchPanel) {
		searchToggle.addEventListener('click', function () {
			searchPanel.hidden = !searchPanel.hidden;
			if (!searchPanel.hidden) { var i = searchPanel.querySelector('input'); if (i) { i.focus(); } }
		});
	}

	/* Dark mode */
	var themeToggle = document.querySelector('[data-theme-toggle]');
	if (themeToggle) {
		themeToggle.addEventListener('click', function () {
			var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			root.setAttribute('data-theme', next);
			try { localStorage.setItem('kingdom-scheme', next); } catch (e) { /* private mode */ }
		});
	}

	/* Reveal on scroll */
	var reveal = document.querySelectorAll('[data-reveal]');
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { entry.target.classList.add('is-revealed'); io.unobserve(entry.target); }
			});
		}, { rootMargin: '0px 0px -60px 0px' });
		reveal.forEach(function (el) { io.observe(el); });
	} else {
		reveal.forEach(function (el) { el.classList.add('is-revealed'); });
	}

	/* Animated counters */
	var counters = document.querySelectorAll('[data-count]');
	if ('IntersectionObserver' in window && counters.length) {
		var co = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				var el = entry.target, target = parseInt(el.dataset.count, 10) || 0, start = null;
				co.unobserve(el);
				var step = function (ts) {
					if (!start) { start = ts; }
					var p = Math.min(1, (ts - start) / 1600);
					el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
					if (p < 1) { requestAnimationFrame(step); }
				};
				requestAnimationFrame(step);
			});
		}, { threshold: .4 });
		counters.forEach(function (c) { co.observe(c); });
	}

	/* YouTube video modal */
	var modal = document.querySelector('[data-video-modal]');
	if (modal) {
		var body = modal.querySelector('.k-modal__body');
		var closeModal = function () { modal.hidden = true; body.innerHTML = ''; };
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-video]');
			if (trigger) {
				e.preventDefault();
				var iframe = document.createElement('iframe');
				iframe.src = 'https://www.youtube.com/embed/' + encodeURIComponent(trigger.dataset.video) + '?autoplay=1&rel=0';
				iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
				iframe.allowFullscreen = true;
				body.innerHTML = '';
				body.appendChild(iframe);
				modal.hidden = false;
			}
			if (e.target === modal || e.target.closest('[data-modal-close]')) { closeModal(); }
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { closeModal(); } });
	}

	/* Filter selects navigate to the chosen archive */
	document.querySelectorAll('[data-filter-select]').forEach(function (sel) {
		sel.addEventListener('change', function () { if (sel.value) { window.location.href = sel.value; } });
	});

	/* Audio playback speed */
	document.querySelectorAll('[data-audio-speed]').forEach(function (sel) {
		var audio = sel.closest('.k-audio').querySelector('audio');
		sel.addEventListener('change', function () { audio.playbackRate = parseFloat(sel.value); });
	});

	/* Share fallback when the plugin script is not present */
	if (!window.KCS) {
		document.addEventListener('click', function (e) {
			var b = e.target.closest('[data-kcs-share]');
			if (!b) { return; }
			if (navigator.share) { navigator.share({ title: document.title, url: location.href }).catch(function () {}); }
			else if (navigator.clipboard) { navigator.clipboard.writeText(location.href); b.textContent = '✓'; }
		});
	}
})();
