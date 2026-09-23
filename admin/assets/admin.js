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

	/* --- şəkil və video sahəsi --- */

	/* Gizli sahəyə yolu yazır və önizləməni (şəkil və ya video) yeniləyir */
	function setMedia(field, path) {
		var input = document.getElementById('f-' + field);
		var preview = document.getElementById('p-' + field);
		if (!input) { return; }
		input.value = path;
		if (!preview) { return; }
		var box = preview.closest('.pick__box');
		if (path === '') {
			preview.removeAttribute('src');
			preview.hidden = true;
			if (box) { box.classList.add('is-empty'); }
			return;
		}
		preview.src = base() + path.replace(/^\/+/, '');
		preview.hidden = false;
		if (box) { box.classList.remove('is-empty'); }
	}

	document.addEventListener('click', function (e) {
		var clear = e.target.closest('[data-clear]');
		if (clear) {
			setMedia(clear.getAttribute('data-clear'), '');
			return;
		}

		var reset = e.target.closest('[data-reset]');
		if (reset) {
			setMedia(reset.getAttribute('data-reset'), reset.getAttribute('data-reset-to') || '');
			return;
		}

		var pick = e.target.closest('[data-pick]');
		if (pick) {
			e.preventDefault();
			var field = pick.getAttribute('data-pick');
			openLibrary({
				kind: pick.getAttribute('data-kind') || 'image',
				done: function (paths) { setMedia(field, paths[0]); }
			});
		}
	});

	/* --- qalereya --- */

	function galleryCount(gal) {
		var n = gal.querySelectorAll('.gal__item').length;
		var label = gal.closest('.field') && gal.closest('.field').querySelector('[data-gal-count]');
		if (label) { label.textContent = n + ' şəkil'; }
	}

	function galleryItem(gal, path, thumb) {
		var name = gal.getAttribute('data-gallery');
		var fig = document.createElement('figure');
		fig.className = 'gal__item';
		fig.draggable = true;

		var img = document.createElement('img');
		img.src = thumb || (base() + path);
		img.alt = '';
		img.loading = 'lazy';

		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = name + '[]';
		input.value = path;

		var tools = document.createElement('div');
		tools.className = 'gal__tools';
		tools.innerHTML = '<button type="button" data-gal-move="-1" title="Sola">‹</button>'
			+ '<button type="button" data-gal-move="1" title="Sağa">›</button>'
			+ '<button type="button" data-gal-remove title="Qalereyadan çıxar">×</button>';

		fig.appendChild(img);
		fig.appendChild(input);
		fig.appendChild(tools);
		return fig;
	}

	document.addEventListener('click', function (e) {
		var gal = e.target.closest('[data-gallery]');
		if (!gal) { return; }
		var grid = gal.querySelector('.gal__grid');
		var item = e.target.closest('.gal__item');

		if (e.target.closest('[data-gal-remove]') && item) {
			item.remove();
			galleryCount(gal);
			return;
		}

		var move = e.target.closest('[data-gal-move]');
		if (move && item) {
			if (move.getAttribute('data-gal-move') === '-1') {
				if (item.previousElementSibling) { grid.insertBefore(item, item.previousElementSibling); }
			} else if (item.nextElementSibling) {
				grid.insertBefore(item.nextElementSibling, item);
			}
			return;
		}

		if (e.target.closest('[data-gal-add]')) {
			openLibrary({
				kind: 'image',
				multi: true,
				done: function (paths) {
					paths.forEach(function (p) { grid.appendChild(galleryItem(gal, p)); });
					galleryCount(gal);
				}
			});
			return;
		}

		if (e.target.closest('[data-gal-reset]')) {
			if (!window.confirm('Qalereya orijinal vəziyyətinə qaytarılsın?')) { return; }
			var list = [];
			try { list = JSON.parse(gal.getAttribute('data-reset') || '[]'); } catch (err) { list = []; }
			grid.innerHTML = '';
			list.forEach(function (it) { grid.appendChild(galleryItem(gal, it.p, it.t)); });
			galleryCount(gal);
		}
	});

	/* Sürüşdürərək sıralamaq */
	var dragged = null;

	document.addEventListener('dragstart', function (e) {
		var item = e.target.closest && e.target.closest('.gal__item');
		if (!item) { return; }
		dragged = item;
		item.classList.add('is-dragging');
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', '');
		}
	});

	document.addEventListener('dragover', function (e) {
		var over = dragged && e.target.closest && e.target.closest('.gal__item');
		if (!over || over === dragged || over.parentNode !== dragged.parentNode) { return; }
		e.preventDefault();
		Array.prototype.forEach.call(over.parentNode.children, function (el) { el.classList.remove('is-over'); });
		over.classList.add('is-over');
	});

	document.addEventListener('drop', function (e) {
		var over = dragged && e.target.closest && e.target.closest('.gal__item');
		if (!over || over === dragged || over.parentNode !== dragged.parentNode) { return; }
		e.preventDefault();
		var items = Array.prototype.slice.call(over.parentNode.children);
		// sağa aparanda hədəfdən sonra, sola aparanda ondan əvvəl qoyulur
		if (items.indexOf(dragged) < items.indexOf(over)) {
			over.parentNode.insertBefore(dragged, over.nextElementSibling);
		} else {
			over.parentNode.insertBefore(dragged, over);
		}
	});

	document.addEventListener('dragend', function () {
		if (!dragged) { return; }
		dragged.classList.remove('is-dragging');
		Array.prototype.forEach.call(dragged.parentNode.children, function (el) { el.classList.remove('is-over'); });
		dragged = null;
	});

	/* --- kitabxana pəncərəsi --- */

	/*
	 * Kitabxana forma üzərindəki pəncərədə açılır. Ayrıca brauzer pəncərəsi
	 * açmırıq: onu bloklaya bilərlər, bəzi brauzerlərdə isə sadəcə tab kimi
	 * açılır və geri qayıtmaq mümkün olmur.
	 *
	 * opts.kind   'image' | 'video'
	 * opts.multi  bir neçə faylı birdən seçmək (qalereya üçün)
	 * opts.done   function (paths) — seçim bitəndə çağırılır
	 */
	var modal = null;

	function openLibrary(opts) {
		closeLibrary();

		var kind = opts.kind === 'video' ? 'video' : 'image';
		var multi = !!opts.multi;
		var picked = [];
		var title = kind === 'video' ? 'Video seçin' : (multi ? 'Şəkilləri seçin' : 'Şəkil seçin');

		modal = document.createElement('div');
		modal.className = 'lib';
		modal.innerHTML =
			'<div class="lib__box" role="dialog" aria-modal="true" aria-label="' + title + '">'
			+ '<div class="lib__head"><strong>' + title + '</strong>'
			+ '<div class="lib__bar">'
			+ (multi ? '<span class="field__hint" data-lib-count>Heç nə seçilməyib</span>'
				+ '<button type="button" class="btn btn--sm btn--primary" data-lib-done disabled>Əlavə et</button>' : '')
			+ '<button type="button" class="btn btn--sm" data-lib-close>Bağla</button></div></div>'
			+ '<div class="lib__body"><div class="empty">Yüklənir…</div></div>'
			+ '</div>';
		document.body.appendChild(modal);
		document.body.classList.add('is-lib-open');

		var body = modal.querySelector('.lib__body');
		var start = base() + 'admin/?section=media&fragment=1&picker=1' + (kind === 'video' ? '&kind=video' : '');

		load(start);

		function load(url, options) {
			body.innerHTML = '<div class="empty">Yüklənir…</div>';
			window.fetch(url, options || { credentials: 'same-origin' })
				.then(function (r) { return r.text(); })
				.then(function (html) {
					body.innerHTML = html;
					body.scrollTop = 0;
					markPicked();
				})
				.catch(function () {
					body.innerHTML = '<div class="empty">Kitabxana açılmadı. Səhifəni yeniləyib yenidən cəhd edin.</div>';
				});
		}

		/* Səhifə dəyişəndə əvvəl seçilənlər işarəli qalsın */
		function markPicked() {
			Array.prototype.forEach.call(body.querySelectorAll('[data-choose]'), function (btn) {
				var on = picked.indexOf(btn.getAttribute('data-choose')) >= 0;
				var card = btn.closest('.media-item');
				if (card) { card.classList.toggle('is-picked', on); }
				btn.textContent = on ? 'Seçildi' : 'Seç';
			});
			if (!multi) { return; }
			var count = modal.querySelector('[data-lib-count]');
			var done = modal.querySelector('[data-lib-done]');
			count.textContent = picked.length ? picked.length + ' şəkil seçilib' : 'Heç nə seçilməyib';
			done.disabled = picked.length === 0;
			done.textContent = picked.length ? 'Əlavə et (' + picked.length + ')' : 'Əlavə et';
		}

		modal.addEventListener('click', function (e) {
			// fondan kənara basanda bağlanır
			if (e.target === modal || e.target.closest('[data-lib-close]')) {
				closeLibrary();
				return;
			}

			if (e.target.closest('[data-lib-done]')) {
				var list = picked.slice();
				closeLibrary();
				if (list.length) { opts.done(list); }
				return;
			}

			var choose = e.target.closest('[data-choose]');
			if (!choose) {
				var card = e.target.closest('.media-item');
				choose = card && card.querySelector('[data-choose]');
			}
			if (choose) {
				e.preventDefault();
				var path = choose.getAttribute('data-choose');
				if (!multi) {
					closeLibrary();
					opts.done([path]);
					return;
				}
				var at = picked.indexOf(path);
				if (at >= 0) { picked.splice(at, 1); } else { picked.push(path); }
				markPicked();
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
			if (!href) { return start; }
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

	/* --- süzgəc: seçim dəyişən kimi forma göndərilir --- */
	document.addEventListener('change', function (e) {
		if (e.target.matches('[data-autosubmit]') && e.target.form) {
			e.target.form.submit();
		}
	});

	/* saytın kök ünvanı: /admin/... -> / */
	function base() {
		var path = window.location.pathname;
		var at = path.indexOf('/admin/');
		return at >= 0 ? path.slice(0, at + 1) : '/';
	}
})();
