/**
 * Kingdom Church Suite — admin helpers: colour pickers, reveal secrets,
 * collapsible gateway sections and the live SEO preview/analysis.
 */
(function () {
	'use strict';

	if (window.jQuery && jQuery.fn.wpColorPicker) {
		jQuery('.kcs-color').wpColorPicker();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.kcs-reveal');
		if (!btn) { return; }
		var input = document.getElementById(btn.dataset.target);
		if (input) { input.type = input.type === 'password' ? 'text' : 'password'; }
	});

	document.querySelectorAll('.kcs-collapsible').forEach(function (section) {
		var head = section.querySelector('h2');
		var enabled = section.querySelector('input[type=checkbox][name$="_enabled]"]');
		if (enabled && !enabled.checked) { section.classList.add('is-collapsed'); }
		head.addEventListener('click', function () { section.classList.toggle('is-collapsed'); });
	});

	/* SEO meta box ------------------------------------------------------- */
	var box = document.querySelector('.kcs-seo-box');
	if (!box) { return; }

	var titleIn = document.getElementById('kcs_seo_title');
	var descIn = document.getElementById('kcs_seo_desc');
	var kwIn = document.getElementById('kcs_seo_keyword');
	var list = box.querySelector('.kcs-seo-analysis');

	function postTitle() {
		var el = document.querySelector('.editor-post-title__input, #title');
		return el ? (el.value || el.textContent || '') : '';
	}
	function postContent() {
		if (window.wp && wp.data && wp.data.select('core/editor')) {
			try { return wp.data.select('core/editor').getEditedPostContent() || ''; } catch (err) { /* classic */ }
		}
		var ta = document.getElementById('content');
		return ta ? ta.value : '';
	}
	function postSlug() {
		if (window.wp && wp.data && wp.data.select('core/editor')) {
			try { return wp.data.select('core/editor').getEditedPostAttribute('slug') || ''; } catch (err) { /* classic */ }
		}
		var s = document.getElementById('editable-post-name');
		return s ? s.textContent : '';
	}
	function strip(html) { var d = document.createElement('div'); d.innerHTML = html; return (d.textContent || '').replace(/\s+/g, ' ').trim(); }

	function update() {
		var sep = box.dataset.sep, site = box.dataset.sitename;
		var title = titleIn.value || (postTitle() + ' ' + sep + ' ' + site);
		var text = strip(postContent());
		var desc = descIn.value || text.slice(0, 157);
		box.querySelector('.kcs-serp__url').textContent = box.dataset.url || location.origin;
		box.querySelector('.kcs-serp__title').textContent = title.length > 62 ? title.slice(0, 60) + '…' : title;
		box.querySelector('.kcs-serp__desc').textContent = desc.length > 160 ? desc.slice(0, 157) + '…' : desc;

		box.querySelectorAll('.kcs-count').forEach(function (c) {
			var input = document.getElementById(c.dataset.for);
			var len = input.value.length, max = parseInt(c.dataset.max, 10);
			c.textContent = len + ' / ' + max;
			c.className = 'kcs-count ' + (len === 0 ? '' : (len > max ? 'is-bad' : (len < max * 0.5 ? 'is-warn' : 'is-good')));
		});

		var kw = kwIn.value.trim().toLowerCase();
		var words = text ? text.split(' ').length : 0;
		var checks = [];
		checks.push([words >= 300, 'Content length: ' + words + ' words (aim for 300+).']);
		checks.push([descIn.value.length >= 70 && descIn.value.length <= 160, 'Meta description is between 70 and 160 characters.']);
		checks.push([/<img[^>]+alt="[^"]+"/i.test(postContent()) || !/<img/i.test(postContent()), 'Images have alt text.']);
		checks.push([/<h2/i.test(postContent()), 'Content uses subheadings (H2).']);
		checks.push([/href="\//.test(postContent()) || postContent().indexOf('href="' + location.origin) !== -1, 'Contains internal links.']);
		if (kw) {
			var count = text.toLowerCase().split(kw).length - 1;
			var density = words ? (count * kw.split(' ').length / words * 100) : 0;
			checks.push([title.toLowerCase().indexOf(kw) !== -1, 'Focus keyword appears in the SEO title.']);
			checks.push([desc.toLowerCase().indexOf(kw) !== -1, 'Focus keyword appears in the meta description.']);
			checks.push([text.toLowerCase().slice(0, 600).indexOf(kw) !== -1, 'Focus keyword appears in the first paragraph.']);
			checks.push([postSlug().toLowerCase().indexOf(kw.replace(/\s+/g, '-')) !== -1, 'Focus keyword appears in the URL slug.']);
			checks.push([density >= 0.5 && density <= 3, 'Keyword density ' + density.toFixed(1) + '% (aim for 0.5–3%).']);
		}
		list.innerHTML = checks.map(function (c) { return '<li class="' + (c[0] ? 'is-good' : 'is-bad') + '">' + c[1] + '</li>'; }).join('');
	}

	[titleIn, descIn, kwIn].forEach(function (el) { el.addEventListener('input', update); });
	setInterval(update, 4000);
	update();
})();
