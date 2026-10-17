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

	/* Mobile navigation (header button and bottom tab bar) */
	var navToggles = document.querySelectorAll('[data-nav-toggle]');
	if (navToggles.length) {
		var setNav = function (open) {
			document.body.classList.toggle('is-nav-open', open);
			navToggles.forEach(function (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); });
		};
		var closeNav = function () { setNav(false); };
		navToggles.forEach(function (t) {
			t.addEventListener('click', function () { setNav(!document.body.classList.contains('is-nav-open')); });
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeNav(); } });
		document.addEventListener('click', function (e) {
			if (document.body.classList.contains('is-nav-open') && !e.target.closest('[data-nav]') && !e.target.closest('[data-nav-toggle]')) { closeNav(); }
		});
	}

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

	/* 3D tilt cards */
	if (document.body.classList.contains('k-3d') && !reduceMotion && finePointer) {
		document.querySelectorAll('[data-tilt]').forEach(function (card) {
			card.addEventListener('pointermove', function (e) {
				var r = card.getBoundingClientRect();
				var x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
				card.classList.add('is-tilting');
				card.style.transform = 'rotateX(' + ((0.5 - y) * 10).toFixed(2) + 'deg) rotateY(' + ((x - 0.5) * 12).toFixed(2) + 'deg) translateZ(10px)';
				card.style.setProperty('--mx', (x * 100) + '%');
				card.style.setProperty('--my', (y * 100) + '%');
			});
			card.addEventListener('pointerleave', function () { card.classList.remove('is-tilting'); card.style.transform = ''; });
		});
	}

	/* Hero parallax depth */
	var scene = document.querySelector('[data-parallax]');
	if (scene && document.body.classList.contains('k-3d') && !reduceMotion) {
		var layers = scene.children;
		var move = function (px, py) {
			for (var i = 0; i < layers.length; i++) {
				var depth = (i + 1) * 6;
				layers[i].style.translate = (px * depth).toFixed(1) + 'px ' + (py * depth).toFixed(1) + 'px';
			}
		};
		if (finePointer) {
			scene.parentElement.addEventListener('pointermove', function (e) {
				move(e.clientX / window.innerWidth - 0.5, e.clientY / window.innerHeight - 0.5);
			});
		}
		window.addEventListener('scroll', function () { scene.style.transform = 'translateY(' + (window.scrollY * 0.25).toFixed(1) + 'px)'; }, { passive: true });
	}

	/* WhatsApp chat widget */
	var wa = document.querySelector('[data-wa]');
	if (wa) {
		var waPanel = wa.querySelector('.k-wa__panel'), waBtn = wa.querySelector('.k-wa__btn');
		var setWa = function (open) { waPanel.hidden = !open; waBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); wa.classList.toggle('is-open', open); };
		wa.addEventListener('click', function (e) { if (e.target.closest('[data-wa-toggle]')) { setWa(waPanel.hidden); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !waPanel.hidden) { setWa(false); waBtn.focus(); } });
		document.addEventListener('click', function (e) { if (!waPanel.hidden && !e.target.closest('[data-wa]')) { setWa(false); } });
		// Shrink the label to an icon after the visitor starts scrolling.
		window.addEventListener('scroll', function () { wa.classList.toggle('is-compact', window.scrollY > 400); }, { passive: true });
	}

	/* Visitor colour palette */
	var picker = document.querySelector('[data-palette-picker]');
	if (picker) {
		var toggle = picker.querySelector('.k-palette__toggle');
		var panel = picker.querySelector('.k-palette__panel');
		var mark = function () {
			var cur = root.getAttribute('data-palette') || '';
			document.querySelectorAll('.k-palette [data-palette], .k-nav__palette [data-palette]').forEach(function (b) { b.classList.toggle('is-active', b.dataset.palette === cur && cur !== ''); });
		};
		toggle.addEventListener('click', function () {
			panel.hidden = !panel.hidden;
			toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
		});
		document.addEventListener('click', function (e) {
			var b = e.target.closest('.k-palette [data-palette], .k-nav__palette [data-palette]');
			if (!b) { return; }
			if (b.dataset.palette) { root.setAttribute('data-palette', b.dataset.palette); } else { root.removeAttribute('data-palette'); }
			try { b.dataset.palette ? localStorage.setItem('kingdom-palette', b.dataset.palette) : localStorage.removeItem('kingdom-palette'); } catch (err) { /* private mode */ }
			mark();
		});
		document.addEventListener('click', function (e) { if (!panel.hidden && !e.target.closest('[data-palette-picker]')) { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); } });
		mark();
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
			var trigger = e.target.closest('[data-video], [data-video-src]');
			if (trigger) {
				e.preventDefault();
				var iframe = document.createElement('iframe');
				// data-video = YouTube ID; data-video-src = any embed URL (e.g. Vimeo).
				iframe.src = trigger.dataset.videoSrc || 'https://www.youtube.com/embed/' + encodeURIComponent(trigger.dataset.video) + '?autoplay=1&rel=0';
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
