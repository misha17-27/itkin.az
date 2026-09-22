/* itkin.az — idarə panelinin kiçik köməkçiləri */
(function () {
	'use strict';

	/* --- silmək üçün təsdiq --- */
	document.addEventListener('click', function (e) {
		var link = e.target.closest('[data-confirm]');
		if (link && !window.confirm(link.getAttribute('data-confirm'))) {
			e.preventDefault();
		}
	});

	/* --- başlıqdan slug qurmaq (yalnız yeni qeydlərdə) --- */
	var source = document.querySelector('[data-slug-source]');
	var target = document.querySelector('[data-slug-target]');
	if (source && target && target.value === '') {
		var touched = false;
		target.addEventListener('input', function () { touched = true; });
		source.addEventListener('input', function () {
			if (touched) { return; }
			target.value = slugify(source.value);
		});
	}

	function slugify(text) {
		var map = { 'ə': 'e', 'ı': 'i', 'ö': 'o', 'ü': 'u', 'ş': 's', 'ç': 'c', 'ğ': 'g', 'İ': 'i', 'â': 'a' };
		return String(text).toLowerCase()
			.replace(/[əıöüşçğİâ]/g, function (ch) { return map[ch] || ch; })
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	/* --- şəkil yolunu köçürmək --- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-copy]');
		if (!btn) { return; }
		var path = btn.getAttribute('data-copy');
		if (navigator.clipboard) {
			navigator.clipboard.writeText(path).then(function () { flash(btn, 'Köçürüldü'); });
		} else {
			window.prompt('Yolu köçürün:', path);
		}
	});

	function flash(btn, text) {
		var old = btn.textContent;
		btn.textContent = text;
		window.setTimeout(function () { btn.textContent = old; }, 1400);
	}

	/* --- şəkil sahəsi --- */

	/* Gizli sahəyə yolu yazır və önizləməni yeniləyir */
	function setImage(field, path) {
		var input = document.getElementById('f-' + field);
		var img = document.getElementById('p-' + field);
		if (!input) { return; }
		input.value = path;
		if (!img) { return; }
		var box = img.closest('.pick__box');
		if (path === '') {
			img.removeAttribute('src');
			img.hidden = true;
			if (box) { box.classList.add('is-empty'); }
			return;
		}
		img.src = base() + path.replace(/^\/+/, '');
		img.hidden = false;
		if (box) { box.classList.remove('is-empty'); }
	}

	document.addEventListener('click', function (e) {
		var clear = e.target.closest('[data-clear]');
		if (clear) {
			setImage(clear.getAttribute('data-clear'), '');
			return;
		}

		var pick = e.target.closest('[data-pick]');
		if (pick) {
			e.preventDefault();
			openLibrary(pick.getAttribute('data-pick'));
		}
	});

	/* --- kitabxana pəncərəsi --- */

	/*
	 * Kitabxana forma üzərindəki pəncərədə açılır. Ayrıca brauzer pəncərəsi
	 * açmırıq: onu bloklaya bilərlər, bəzi brauzerlərdə isə sadəcə tab kimi
	 * açılır və geri qayıtmaq mümkün olmur.
	 */
	var modal = null;

	function openLibrary(field) {
		closeLibrary();

		modal = document.createElement('div');
		modal.className = 'lib';
		modal.innerHTML =
			'<div class="lib__box" role="dialog" aria-modal="true" aria-label="Şəkil seçin">'
			+ '<div class="lib__head"><strong>Şəkil seçin</strong>'
			+ '<button type="button" class="btn btn--sm" data-lib-close>Bağla</button></div>'
			+ '<div class="lib__body"><div class="empty">Yüklənir…</div></div>'
			+ '</div>';
		document.body.appendChild(modal);
		document.body.classList.add('is-lib-open');

		var body = modal.querySelector('.lib__body');

		load(base() + 'admin/?section=media&fragment=1&picker=' + encodeURIComponent(field));

		function load(url, options) {
			body.innerHTML = '<div class="empty">Yüklənir…</div>';
			window.fetch(url, options || { credentials: 'same-origin' })
				.then(function (r) { return r.text(); })
				.then(function (html) { body.innerHTML = html; body.scrollTop = 0; })
				.catch(function () {
					body.innerHTML = '<div class="empty">Kitabxana açılmadı. Səhifəni yeniləyib yenidən cəhd edin.</div>';
				});
		}

		modal.addEventListener('click', function (e) {
			// fondan kənara basanda bağlanır
			if (e.target === modal || e.target.closest('[data-lib-close]')) {
				closeLibrary();
				return;
			}

			var choose = e.target.closest('[data-choose]');
			if (!choose) {
				var card = e.target.closest('.media-item');
				choose = card && card.querySelector('[data-choose]');
			}
			if (choose) {
				e.preventDefault();
				setImage(field, choose.getAttribute('data-choose'));
				closeLibrary();
				return;
			}

			var link = e.target.closest('.lib__body a[href]');
			if (link) {
				e.preventDefault();
				load(withFragment(link.getAttribute('href')));
			}
		});

		modal.addEventListener('submit', function (e) {
			var form = e.target;
			e.preventDefault();

			if ((form.method || 'get').toLowerCase() === 'post') {
				load(withFragment(form.getAttribute('action')), {
					method: 'POST',
					body: new FormData(form),
					credentials: 'same-origin'
				});
				return;
			}

			var params = new URLSearchParams(new FormData(form));
			load(base() + 'admin/?' + params.toString() + '&fragment=1');
		});

		/* Ünvanda fragment=1 olmasa əlavə edirik */
		function withFragment(href) {
			if (!href) { return base() + 'admin/?section=media&fragment=1&picker=' + encodeURIComponent(field); }
			return href + (href.indexOf('?') >= 0 ? '&' : '?') + 'fragment=1';
		}
	}

	function closeLibrary() {
		if (!modal) { return; }
		modal.remove();
		modal = null;
		document.body.classList.remove('is-lib-open');
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closeLibrary(); }
	});

	/* saytın kök ünvanı: /admin/... -> / */
	function base() {
		var path = window.location.pathname;
		var at = path.indexOf('/admin/');
		return at >= 0 ? path.slice(0, at + 1) : '/';
	}
})();
