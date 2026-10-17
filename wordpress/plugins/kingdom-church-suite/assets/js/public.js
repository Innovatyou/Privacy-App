/**
 * Kingdom Church Suite — front-end behaviour (no dependencies).
 */
(function () {
	'use strict';

	var cfg = window.KCS || { restUrl: '/wp-json/kcs/v1/', nonce: '', i18n: {} };
	var t = function (k, d) { return (cfg.i18n && cfg.i18n[k]) || d; };

	function api(path, method, body) {
		var headers = { 'Content-Type': 'application/json' };
		if (cfg.loggedIn && cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
		return fetch(cfg.restUrl + path, {
			method: method || 'GET',
			headers: headers,
			credentials: 'same-origin',
			body: body ? JSON.stringify(body) : undefined
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) { throw new Error((data && data.message) || t('error', 'Error')); }
				return data;
			});
		});
	}

	function formData(form) {
		var out = {};
		new FormData(form).forEach(function (v, k) { out[k] = v; });
		return out;
	}

	function msg(form, text, type) {
		var el = form.querySelector('.kcs-form-msg');
		if (!el) { return; }
		el.className = 'kcs-form-msg kcs-alert kcs-alert--' + (type || 'info');
		el.textContent = text;
	}

	function busy(form, on) {
		var btn = form.querySelector('[type="submit"]');
		if (!btn) { return; }
		if (on) { btn.dataset.label = btn.textContent; btn.textContent = t('processing', 'Processing…'); btn.disabled = true; }
		else { btn.textContent = btn.dataset.label || btn.textContent; btn.disabled = false; }
	}

	/* Payment forms ------------------------------------------------------ */
	document.querySelectorAll('[data-kcs-payment-form]').forEach(function (form) {
		var amount = form.querySelector('[name="amount"]');
		form.querySelectorAll('.kcs-preset').forEach(function (b) {
			b.addEventListener('click', function () {
				amount.value = b.dataset.amount;
				form.querySelectorAll('.kcs-preset').forEach(function (x) { x.classList.toggle('is-active', x === b); });
			});
		});

		var currency = form.querySelector('[data-kcs-currency]');
		function filterGateways() {
			if (!currency) { return; }
			var code = currency.value, firstVisible = null, checkedVisible = false;
			form.querySelectorAll('.kcs-gateway').forEach(function (label) {
				var list = (label.dataset.currencies || '').split(',').filter(Boolean);
				var ok = !list.length || list.indexOf(code) !== -1;
				label.hidden = !ok;
				var radio = label.querySelector('input');
				radio.disabled = !ok;
				if (ok && !firstVisible) { firstVisible = radio; }
				if (ok && radio.checked) { checkedVisible = true; }
			});
			if (!checkedVisible && firstVisible) { firstVisible.checked = true; }
		}
		if (currency) { currency.addEventListener('change', filterGateways); filterGateways(); }

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			msg(form, t('processing', 'Processing…'), 'info');
			api('payments/initialize', 'POST', formData(form))
				.then(function (res) { window.location.href = res.redirect_url; })
				.catch(function (err) { busy(form, false); msg(form, err.message, 'error'); });
		});
	});

	/* Registration: show custom amount for custom plans ------------------- */
	document.querySelectorAll('[data-kcs-register]').forEach(function (form) {
		var box = form.querySelector('[data-kcs-custom-amount]');
		function sync() {
			var checked = form.querySelector('[name="plan_id"]:checked');
			var custom = !checked || checked.closest('.kcs-plan-option').dataset.custom === '1';
			if (box) {
				box.hidden = !custom;
				box.querySelectorAll('input,select').forEach(function (el) { el.disabled = !custom; });
				var amt = box.querySelector('[name="amount"]');
				if (amt) { amt.required = custom; }
			}
		}
		form.querySelectorAll('[name="plan_id"]').forEach(function (r) { r.addEventListener('change', sync); });
		sync();
	});

	/* Newsletter --------------------------------------------------------- */
	document.querySelectorAll('[data-kcs-newsletter]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			var data = formData(form);
			data.source = 'website';
			api('subscribe', 'POST', data)
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});

	/* Prayer requests ---------------------------------------------------- */
	document.querySelectorAll('[data-kcs-prayer]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			var data = formData(form);
			data.is_private = form.querySelector('[name="is_private"]').checked ? 1 : 0;
			api('prayer-requests', 'POST', data)
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-kcs-pray]');
		if (!btn || btn.disabled) { return; }
		btn.disabled = true;
		api('prayer-requests/' + btn.dataset.kcsPray + '/pray', 'POST')
			.then(function (res) { btn.querySelector('span').textContent = res.count; btn.classList.add('is-done'); })
			.catch(function () { btn.classList.add('is-done'); });
	});

	/* Countdown ---------------------------------------------------------- */
	document.querySelectorAll('[data-kcs-countdown]').forEach(function (el) {
		var target = parseInt(el.dataset.kcsCountdown, 10) * 1000;
		var clock = el.querySelector('.kcs-countdown__clock');
		function pad(n) { return n < 10 ? '0' + n : String(n); }
		function tick() {
			var diff = Math.max(0, target - Date.now());
			var d = Math.floor(diff / 86400000), h = Math.floor(diff / 3600000) % 24, m = Math.floor(diff / 60000) % 60, s = Math.floor(diff / 1000) % 60;
			if (diff === 0) {
				clock.innerHTML = '<span class="kcs-countdown__live">' + t('live', 'LIVE') + '</span>';
				return;
			}
			clock.innerHTML = [[d, t('days', 'Days')], [h, t('hours', 'Hours')], [m, t('minutes', 'Min')], [s, t('seconds', 'Sec')]]
				.map(function (p) { return '<span><b>' + pad(p[0]) + '</b><small>' + p[1] + '</small></span>'; }).join('');
			setTimeout(tick, 1000);
		}
		tick();
	});

	/* Live badge polling ------------------------------------------------- */
	var badges = document.querySelectorAll('[data-kcs-live-badge]');
	if (badges.length) {
		var poll = function () {
			api('live').then(function (s) {
				badges.forEach(function (b) {
					b.classList.toggle('is-live', !!s.is_live);
					var txt = b.querySelector('.kcs-live-text');
					if (txt) { txt.textContent = s.is_live ? t('live', 'LIVE') + ' NOW' : txt.dataset.offline || txt.textContent; }
				});
				document.documentElement.classList.toggle('kcs-is-live', !!s.is_live);
			}).catch(function () {});
		};
		badges.forEach(function (b) { var txt = b.querySelector('.kcs-live-text'); if (txt && !b.classList.contains('is-live')) { txt.dataset.offline = txt.textContent; } });
		setInterval(poll, 120000);
	}

	/* Payment status auto-refresh --------------------------------------- */
	var status = document.querySelector('[data-kcs-payment-status]');
	if (status && (status.dataset.status === 'pending' || status.dataset.status === 'review') && !/kcs_offline/.test(location.search)) {
		var tries = 0;
		var check = function () {
			api('payments/status?reference=' + encodeURIComponent(status.dataset.kcsPaymentStatus)).then(function (r) {
				if (r.status !== status.dataset.status) { location.reload(); }
				else if (++tries < 10) { setTimeout(check, 4000); }
			}).catch(function () {});
		};
		setTimeout(check, 3000);
	}

	/* Dashboard tabs ----------------------------------------------------- */
	document.querySelectorAll('[data-kcs-tabs]').forEach(function (wrap) {
		function show(name) {
			wrap.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'); });
			wrap.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== name; });
		}
		wrap.addEventListener('click', function (e) {
			var b = e.target.closest('[data-tab],[data-tab-link]');
			if (b) { show(b.dataset.tab || b.dataset.tabLink); }
		});
		show(wrap.dataset.active || 'overview');
	});

	/* Share -------------------------------------------------------------- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-kcs-share]');
		if (!b) { return; }
		var data = { title: document.title, url: location.href };
		if (navigator.share) { navigator.share(data).catch(function () {}); }
		else if (navigator.clipboard) { navigator.clipboard.writeText(data.url); b.textContent = '✓'; }
	});
})();
