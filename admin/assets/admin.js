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

	/* --- şəkil önizləməsi --- */
	document.addEventListener('input', function (e) {
		if (!e.target.matches('[data-image-input]')) { return; }
		var img = document.getElementById('p-' + e.target.name);
		if (!img) { return; }
		if (e.target.value.trim() === '') {
			img.style.visibility = 'hidden';
			return;
		}
		img.src = base() + e.target.value.trim().replace(/^\/+/, '');
		img.style.visibility = 'visible';
	});

	document.addEventListener('click', function (e) {
		var clear = e.target.closest('[data-clear]');
		if (clear) {
			var input = document.getElementById('f-' + clear.getAttribute('data-clear'));
			if (input) {
				input.value = '';
				input.dispatchEvent(new Event('input', { bubbles: true }));
			}
			return;
		}

		var pick = e.target.closest('[data-pick]');
		if (pick) {
			e.preventDefault();
			window.open(base() + 'admin/?section=media', 'itkin-media', 'width=1000,height=760');
		}
	});

	/* saytın kök ünvanı: /admin/... -> / */
	function base() {
		var path = window.location.pathname;
		var at = path.indexOf('/admin/');
		return at >= 0 ? path.slice(0, at + 1) : '/';
	}
})();
