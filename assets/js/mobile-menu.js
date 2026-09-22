/*
 * itkin.az — mobil menyunun davranışı (surətə əlavə edilib, orijinalda yoxdur).
 *
 * Elementor menyunu başlığın altında açılan panel kimi verir. Burada onu
 * bütün səhifəni örtən panelə çeviririk. Panel başlığı da örtdüyü üçün
 * loqo və bağlama düyməsi panelin öz yuxarı zolağına əlavə olunur.
 */
(function () {
	'use strict';

	var BREAKPOINT = 1024;
	var LOGO_SRC = 'uploads/2023/11/fav.png';   // tünd variant — ağ fonda görünür
	var CLOSE_SVG =
		'<svg aria-hidden="true" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg">' +
		'<path d="M742 167L500 408 258 167C246 154 233 150 217 150 196 150 179 158 167 167 154 179 150 196 150 212 150 229 154 242 171 254L408 500 167 742C138 771 138 800 167 829 196 858 225 858 254 829L496 587 738 829C750 842 767 846 783 846 800 846 817 842 829 829 842 817 846 804 846 783 846 767 842 750 829 737L588 500 833 258C863 229 863 200 833 171 804 137 775 137 742 167Z"></path>' +
		'</svg>';

	function header() {
		return document.querySelector('.elementor-location-header');
	}

	function toggle() {
		return document.querySelector('.elementor-location-header .elementor-menu-toggle');
	}

	function panel() {
		return document.querySelector('.elementor-location-header nav.elementor-nav-menu--dropdown');
	}

	function isOpen() {
		var t = toggle();
		return !!t && t.classList.contains('elementor-active');
	}

	/** Sayt kökünə nisbi ünvan (alt qovluqda da işləsin deyə) */
	function basePath() {
		var logo = document.querySelector('.elementor-location-header img');
		if (logo) {
			var src = logo.getAttribute('src') || '';
			var at = src.indexOf('/uploads/');
			if (at >= 0) {
				return src.slice(0, at + 1);
			}
		}
		return '/';
	}

	/** Panelin yuxarı zolağı: loqo + bağlama düyməsi */
	function buildBar(p) {
		if (p.querySelector('.itkin-mm-bar')) {
			return;
		}

		var bar = document.createElement('div');
		bar.className = 'itkin-mm-bar';

		var home = document.createElement('a');
		home.className = 'itkin-mm-bar__logo';
		home.setAttribute('href', basePath());

		var img = document.createElement('img');
		img.setAttribute('src', basePath() + LOGO_SRC);
		img.setAttribute('alt', 'İtkin');
		img.setAttribute('width', '46');
		img.setAttribute('height', '46');
		img.setAttribute('decoding', 'async');
		home.appendChild(img);

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'itkin-mm-bar__close';
		close.setAttribute('aria-label', 'Menyunu bağla');
		close.innerHTML = CLOSE_SVG;
		close.addEventListener('click', function () {
			closeMenu();
		});

		bar.appendChild(home);
		bar.appendChild(close);
		p.insertBefore(bar, p.firstChild);
	}

	function syncBodyLock() {
		var open = isOpen() && window.innerWidth <= BREAKPOINT;
		document.body.classList.toggle('itkin-menu-open', open);
	}

	function closeMenu() {
		var t = toggle();
		if (t && t.classList.contains('elementor-active')) {
			t.click();
		}
		window.setTimeout(syncBodyLock, 0);
	}

	function init() {
		var t = toggle();
		var p = panel();
		if (!t || !p) {
			return;
		}

		buildBar(p);

		// Elementor sinifi klikdən sonra dəyişir — bir kadr gözləyirik
		t.addEventListener('click', function () {
			window.setTimeout(syncBodyLock, 0);
		});

		// Bəndə keçid olanda panel bağlansın (alt menyu açan oxlar istisna)
		p.addEventListener('click', function (e) {
			if (e.target.closest('.itkin-mm-bar')) {
				return;
			}
			var link = e.target.closest('a');
			if (!link || e.target.closest('.sub-arrow')) {
				return;
			}
			var href = link.getAttribute('href');
			if (href && href !== '#') {
				closeMenu();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && isOpen()) {
				closeMenu();
			}
		});

		window.addEventListener('resize', syncBodyLock);
		window.addEventListener('orientationchange', function () {
			window.setTimeout(syncBodyLock, 150);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
