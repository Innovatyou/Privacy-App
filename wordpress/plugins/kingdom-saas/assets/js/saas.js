/* Kingdom Cloud — platform pages. */
(function () {
	'use strict';
	var script = document.currentScript || document.querySelector('script[data-rest]');
	var rest = script ? script.getAttribute('data-rest') : '/wp-json/ks/v1/';

	/* Pricing: International / Local and Monthly / Yearly switches. */
	function wirePrices(scope) {
		scope.addEventListener('change', function (e) {
			var el = e.target;
			if (el.type !== 'radio') { return; }
			if (/region/.test(el.name)) { scope.setAttribute('data-r', el.value); }
			if (/interval/.test(el.name)) { scope.setAttribute('data-i', el.value); }
			scope.querySelectorAll('[data-plan-link]').forEach(function (a) {
				var u = new URL(a.href);
				u.searchParams.set('region', scope.getAttribute('data-r'));
				u.searchParams.set('interval', scope.getAttribute('data-i'));
				a.href = u.toString();
			});
		});
	}
	document.querySelectorAll('[data-ks-prices]').forEach(wirePrices);

	/* Sign-up: suggest the web address from the church name and check it live. */
	var church = document.querySelector('[data-ks-church]');
	var sub = document.querySelector('[data-ks-subdomain]');
	var msg = document.querySelector('[data-ks-subdomain-msg]');
	var touched = !!(sub && sub.value);
	var timer;
	function slug(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 34); }
	function check() {
		if (!sub || !msg) { return; }
		var v = sub.value.trim().toLowerCase();
		sub.value = v;
		if (!v) { msg.textContent = ''; return; }
		clearTimeout(timer);
		timer = setTimeout(function () {
			fetch(rest + 'subdomain?name=' + encodeURIComponent(v), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					msg.textContent = d.available ? '✓ ' + d.domain + ' is available' : '✕ ' + d.message;
					msg.className = 'ks-hint ' + (d.available ? 'is-ok' : 'is-bad');
					sub.setCustomValidity(d.available ? '' : d.message);
				})
				.catch(function () {});
		}, 300);
	}
	if (church && sub) {
		church.addEventListener('input', function () { if (!touched) { sub.value = slug(church.value); check(); } });
	}
	if (sub) {
		sub.addEventListener('input', function () { touched = true; check(); });
		if (sub.value) { check(); }
	}

	/* Carry region / interval chosen on the pricing page into sign-up. */
	var params = new URLSearchParams(location.search);
	if (params.get('region')) {
		var r = document.querySelector('[data-ks-signup] input[name="region"][value="' + params.get('region') + '"]');
		if (r) { r.checked = true; }
	}

	/* Sign-up: friendly validation messages and no double submit. */
	var form = document.querySelector('[data-ks-signup]');
	if (form) {
		form.addEventListener('submit', function (e) {
			if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
			var b = form.querySelector('button');
			b.disabled = true;
			b.textContent = b.getAttribute('data-busy') || 'Creating your website…';
		});
	}
})();
