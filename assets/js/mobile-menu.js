/*
 * itkin.az — mobil menyunun davranışı (surətə əlavə edilib, orijinalda yoxdur).
 *
 * Elementor menyunu başlığın altında açılan panel kimi verir. Burada onu
 * tam ekran panelə çeviririk: başlığın hündürlüyünü ölçüb CSS dəyişəninə
 * yazırıq, açıq olanda arxa fonu kilidləyirik və bənddən sonra bağlayırıq.
 */
(function () {
	'use strict';

	var BREAKPOINT = 1024;

	function header() {
		return document.querySelector('.elementor-location-header');
	}

	function toggle() {
		return document.querySelector('.elementor-location-header .elementor-menu-toggle');
	}

	function isOpen() {
		var t = toggle();
		return !!t && t.classList.contains('elementor-active');
	}

	/** Panel başlığın düz altından başlasın deyə hündürlüyü ölçürük */
	function measure() {
		var h = header();
		if (!h) {
			return;
		}
		var height = Math.round(h.getBoundingClientRect().height);
		if (height > 0) {
			document.documentElement.style.setProperty('--itkin-mobile-header-h', height + 'px');
		}
	}

	function syncBodyLock() {
		var open = isOpen() && window.innerWidth <= BREAKPOINT;
		document.body.classList.toggle('itkin-menu-open', open);
		if (open) {
			measure();
		}
	}

	function close() {
		var t = toggle();
		if (t && t.classList.contains('elementor-active')) {
			t.click();
		}
	}

	function init() {
		var t = toggle();
		if (!t) {
			return;
		}

		measure();

		// Elementor sinifi klikdən sonra dəyişir — bir kadr gözləyirik
		t.addEventListener('click', function () {
			window.setTimeout(syncBodyLock, 0);
		});

		// Bəndə keçid olanda panel bağlansın (alt menyu açan oxlar istisna)
		var nav = document.querySelector('.elementor-location-header nav.elementor-nav-menu--dropdown');
		if (nav) {
			nav.addEventListener('click', function (e) {
				var link = e.target.closest('a');
				if (!link || e.target.closest('.sub-arrow')) {
					return;
				}
				var href = link.getAttribute('href');
				if (href && href !== '#') {
					close();
					window.setTimeout(syncBodyLock, 0);
				}
			});
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && isOpen()) {
				close();
				window.setTimeout(syncBodyLock, 0);
			}
		});

		window.addEventListener('resize', function () {
			measure();
			syncBodyLock();
		});

		window.addEventListener('orientationchange', function () {
			window.setTimeout(function () {
				measure();
				syncBodyLock();
			}, 150);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
