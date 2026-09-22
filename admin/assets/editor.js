/*
 * itkin.az — mətn üçün sadə vizual redaktor.
 *
 * Kənar kitabxana yoxdur: adi contenteditable sahə və execCommand.
 *
 * Ən vacib qayda — toxunulmayan mətn dəyişmir. Saytın məzmununda cədvəllər,
 * video, figure, srcset və inline style var; brauzer bunları öz bildiyi kimi
 * yenidən yaza bilər. Ona görə <textarea> yerində qalır və məzmunun əsl
 * saxlandığı yer elə odur. Redaktor yalnız istifadəçi həqiqətən nəsə
 * yazandan sonra textarea-ya toxunur (bax: box.dirty).
 *
 * Şəkil yolları məzmunda nisbi saxlanılır ("uploads/..."). Panel /admin/
 * altında işlədiyi üçün belə yol qırılır, ona görə göstərəndə tam ünvana
 * çevrilir, saxlayanda isə əvvəlki sətir olduğu kimi geri qoyulur —
 * sətir yaddaşda saxlandığına görə srcset kimi uzun dəyərlər də zədələnmir.
 */
(function () {
	'use strict';

	var fields = document.querySelectorAll('textarea[data-rich]');
	if (!fields.length) {
		return;
	}

	// Nisbi yolu yadda saxladığımız atributun adı
	var KEEP = 'data-itkin-rel';
	var URL_ATTRS = ['src', 'href', 'srcset', 'poster', 'data-src'];

	var TOOLS = [
		{ cmd: 'bold', label: 'B', title: 'Qalın (Ctrl+B)', cls: 'rich__btn--b' },
		{ cmd: 'italic', label: 'I', title: 'Maili (Ctrl+I)', cls: 'rich__btn--i' },
		{ block: 'H2', label: 'H2', title: 'Başlıq' },
		{ block: 'H3', label: 'H3', title: 'Alt başlıq' },
		{ block: 'P', label: '¶', title: 'Adi abzas' },
		{ list: 'UL', label: '• siyahı', title: 'Nişanlı siyahı' },
		{ list: 'OL', label: '1. siyahı', title: 'Nömrəli siyahı' },
		{ act: 'link', label: '🔗', title: 'Keçid əlavə et' },
		{ act: 'unlink', label: 'keçidi sil', title: 'Keçidi götür' },
		{ act: 'clear', label: 'təmizlə', title: 'Formatı götür' },
		{ act: 'code', label: 'HTML', title: 'HTML kodunu göstər', right: true }
	];

	/* Saytın kök ünvanı: /admin/... -> / */
	function base() {
		var path = window.location.pathname;
		var at = path.indexOf('/admin/');
		return at >= 0 ? path.slice(0, at + 1) : '/';
	}

	var ROOT = base();

	/* ------------------------------------------------------------ yollar */

	/* "uploads/a.jpg 1000w, uploads/b.jpg 300w" -> hər ünvana kök əlavə edir */
	function absolutise(value) {
		return value.split(',').map(function (part) {
			var bits = part.trim().split(/\s+/);
			if (bits[0] && bits[0].indexOf('uploads/') === 0) {
				bits[0] = ROOT + bits[0];
			}
			return bits.join(' ');
		}).join(', ');
	}

	/*
	 * Tam ünvanı yenidən nisbi hala salır — yalnız öz saytımızın uploads/
	 * faylları üçün. Kənar saytın şəkli olduğu kimi qalır.
	 */
	function relativise(value) {
		return value.split(',').map(function (part) {
			var bits = part.trim().split(/\s+/);
			var url  = bits[0] || '';
			var path = url;

			if (/^https?:\/\//i.test(url)) {
				if (url.indexOf(window.location.origin + '/') !== 0) {
					return part;   // başqa sayt
				}
				path = url.slice(window.location.origin.length);
			}
			if (path.indexOf(ROOT + 'uploads/') === 0) {
				bits[0] = path.slice(ROOT.length);
			}
			return bits.join(' ');
		}).join(', ');
	}

	/* Göstərmək üçün: nisbi yolları tam ünvana çevirir, əslini saxlayır */
	function unfold(area) {
		var nodes = area.querySelectorAll('[src], [href], [srcset], [poster], [data-src]');
		Array.prototype.forEach.call(nodes, function (el) {
			URL_ATTRS.forEach(function (attr) {
				var value = el.getAttribute(attr);
				if (value === null || value.indexOf('uploads/') !== 0) {
					return;
				}
				el.setAttribute(KEEP + '-' + attr, value);
				el.setAttribute(attr, absolutise(value));
			});
		});
	}

	/* Saxlamaq üçün: nisbi yolları olduğu kimi geri qoyur (nüsxə üzərində) */
	function fold(clone) {
		var nodes = clone.querySelectorAll('*');
		Array.prototype.forEach.call(nodes, function (el) {
			URL_ATTRS.forEach(function (attr) {
				var keep = el.getAttribute(KEEP + '-' + attr);
				if (keep === null) {
					return;
				}
				el.setAttribute(attr, keep);
				el.removeAttribute(KEEP + '-' + attr);
			});
		});
	}

	/* execCommand bəzən boş <span> qoyub gedir — onları açırıq */
	function dropEmptySpans(clone) {
		var spans = clone.querySelectorAll('span');
		for (var i = spans.length - 1; i >= 0; i--) {
			if (spans[i].attributes.length === 0) {
				var el = spans[i];
				while (el.firstChild) {
					el.parentNode.insertBefore(el.firstChild, el);
				}
				el.parentNode.removeChild(el);
			}
		}
	}

	/* execCommand <b>/<i> qoyur, məzmunun qalanı isə <strong>/<em> işlədir */
	function unify(clone) {
		['b:strong', 'i:em'].forEach(function (pair) {
			var from = pair.split(':')[0];
			var to = pair.split(':')[1];
			var old = clone.querySelectorAll(from);
			Array.prototype.forEach.call(old, function (el) {
				var fresh = document.createElement(to);
				while (el.firstChild) {
					fresh.appendChild(el.firstChild);
				}
				Array.prototype.forEach.call(el.attributes, function (a) {
					fresh.setAttribute(a.name, a.value);
				});
				el.parentNode.replaceChild(fresh, el);
			});
		});
	}

	/* Redaktordakı görüntünü saxlanacaq HTML-ə çevirir */
	function toStore(area) {
		var clone = area.cloneNode(true);
		fold(clone);
		dropEmptySpans(clone);
		unify(clone);
		var html = clone.innerHTML.trim();
		// boş redaktorun brauzerdən qalan qalığı
		return (html === '<br>' || html === '<p><br></p>' || html === '<p></p>') ? '' : html;
	}

	/* ------------------------------------------------------ yapışdırmaq */

	var OK_TAGS = ('p br strong b em i u sub sup ul ol li a h2 h3 h4 blockquote '
		+ 'table thead tbody tr td th img figure figcaption').split(' ');
	var OK_ATTRS = 'href src alt title target rel colspan rowspan width height srcset sizes'.split(' ');

	/* Kənardan (Word, sayt) gələn kodu sadələşdirir */
	function clean(html) {
		var box = document.createElement('div');
		box.innerHTML = html;
		Array.prototype.forEach.call(box.querySelectorAll('script, style, meta, link'), function (el) {
			el.parentNode.removeChild(el);
		});
		var all = box.querySelectorAll('*');
		// sondan gəlirik ki, əvəz olunan element sonrakı addımı pozmasın
		for (var i = all.length - 1; i >= 0; i--) {
			var el = all[i];
			var tag = el.tagName.toLowerCase();
			if (OK_TAGS.indexOf(tag) < 0) {
				while (el.firstChild) {
					el.parentNode.insertBefore(el.firstChild, el);
				}
				el.parentNode.removeChild(el);
				continue;
			}
			for (var a = el.attributes.length - 1; a >= 0; a--) {
				var name = el.attributes[a].name.toLowerCase();
				var value = el.attributes[a].value;
				if (OK_ATTRS.indexOf(name) < 0 || /^\s*javascript:/i.test(value)) {
					el.removeAttribute(el.attributes[a].name);
					continue;
				}
				// Redaktorda şəkillərin ünvanı tamdır; yapışdırılanda onu
				// yenidən nisbi hala salırıq, yoxsa məzmuna bu saytın
				// domeni yazılıb qalar.
				if (name === 'src' || name === 'srcset' || name === 'poster' || name === 'href') {
					el.setAttribute(el.attributes[a].name, relativise(value));
				}
			}
		}
		return box.innerHTML;
	}

	/* -------------------------------------------------------- seçim/bloklar */

	/*
	 * Blok əmrləri üçün execCommand('formatBlock') işlətmirik.
	 *
	 * Seçim bütöv sahəni əhatə edəndə brauzer mövcud bloku əvəz etmir, üstünə
	 * bir qat da qoyur: <h2><h3><p><ul>… Ona görə blokları özümüz dəyişirik —
	 * nəticə həmişə düz olur və siyahı da abzasa çevrilə bilir.
	 */

	/*
	 * Formatı dəyişdirilə bilən mətn blokları.
	 *
	 * Siyahıda <div>, <table>, <figure>, <video> YOXDUR və olmamalıdır:
	 * məzmunda Elementor sarğı <div>-ləri və üst səviyyəli cədvəllər var,
	 * onlara toxunsaq yazının quruluşu dağılır.
	 */
	var TEXT_BLOCKS = 'p, h1, h2, h3, h4, h5, h6, blockquote';

	function currentRange() {
		var sel = window.getSelection();
		return (sel && sel.rangeCount) ? sel.getRangeAt(0) : null;
	}

	/* Seçimlə kəsişən elementləri süzür */
	function intersecting(nodes, range) {
		return Array.prototype.filter.call(nodes, function (el) {
			try {
				return range.intersectsNode(el);
			} catch (err) {
				return false;
			}
		});
	}

	/*
	 * Seçimin toxunduğu mətn blokları.
	 *
	 * Burada area.children ilə işləmək olmaz: yığılmış kursor iç-içə
	 * elementin içində olanda da ən üstdəki övladı “kəsir”, yəni düymə
	 * kursorun olduğu abzasa yox, bütöv cədvələ və ya sarğı <div>-inə
	 * düşür. Ona görə blokları birbaşa axtarırıq.
	 */
	function selectedBlocks(area, range) {
		return intersecting(area.querySelectorAll(TEXT_BLOCKS), range);
	}

	/* Seçimlə kəsişən siyahılar (yuvalanmışın ən içdəkisi) */
	function selectedLists(area, range) {
		return intersecting(area.querySelectorAll('ul, ol'), range).filter(function (list) {
			return !list.querySelector('ul, ol');
		});
	}

	/* Dəyişdirilmiş blokları yenidən seçir ki, ardıcıl düymələr işləsin */
	function reselect(area, blocks) {
		blocks = blocks.filter(function (el) { return el && el.parentNode; });
		if (!blocks.length) {
			return;
		}
		try {
			var range = document.createRange();
			range.setStartBefore(blocks[0]);
			range.setEndAfter(blocks[blocks.length - 1]);
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
		} catch (err) { /* bloklar müxtəlif valideynlərdədir — seçimi buraxırıq */ }
	}

	/* Elementi başqa teqlə əvəz edir; atributlar və məzmun saxlanılır */
	function retag(el, tag) {
		var fresh = document.createElement(tag);
		Array.prototype.forEach.call(el.attributes, function (a) {
			fresh.setAttribute(a.name, a.value);
		});
		while (el.firstChild) {
			fresh.appendChild(el.firstChild);
		}
		el.parentNode.replaceChild(fresh, el);
		return fresh;
	}

	/* Siyahını abzaslara açır, hər <li> ayrıca blok olur */
	function unlist(list, tag) {
		var made = [];
		var frag = document.createDocumentFragment();
		Array.prototype.forEach.call(list.children, function (li) {
			if (li.tagName !== 'LI') {
				return;
			}
			// Bənd onsuz da tək blokdan ibarətdirsə, onu olduğu kimi çıxarırıq
			var only = li.children.length === 1 ? li.children[0] : null;
			if (only && only.matches(TEXT_BLOCKS) && only.textContent === li.textContent) {
				frag.appendChild(only);
				made.push(only);
				return;
			}
			var block = document.createElement(tag);
			while (li.firstChild) {
				block.appendChild(li.firstChild);
			}
			frag.appendChild(block);
			made.push(block);
		});
		list.parentNode.replaceChild(frag, list);
		return made;
	}

	/* Blok formatı. Eyni düyməyə ikinci dəfə basanda başlıq abzasa qayıdır. */
	function setBlock(area, tag) {
		var range = currentRange();
		if (!range) {
			return;
		}
		// Dəyişiklikdən əvvəl hər iki siyahını hazırlayırıq — DOM dəyişəndən
		// sonra köhnə seçim etibarsız olur
		var lists  = selectedLists(area, range);
		var blocks = selectedBlocks(area, range);

		var made = [];
		lists.forEach(function (list) {
			made = made.concat(unlist(list, tag));
		});
		blocks.forEach(function (el) {
			if (!el.parentNode) {
				return;   // siyahı açılarkən artıq yerini dəyişib
			}
			var want = (el.tagName === tag && tag !== 'P') ? 'P' : tag;
			made.push(el.tagName === want ? el : retag(el, want));
		});

		reselect(area, made);
	}

	/* Formatı tam götürür: siyahı açılır, keçid və işarələr silinir */
	function clearAll(area) {
		document.execCommand('removeFormat', false, null);
		document.execCommand('unlink', false, null);
		setBlock(area, 'P');
	}

	/*
	 * Siyahı düyməsi.
	 *
	 * insertUnorderedList seçim bütöv abzası tutanda siyahını onun içinə
	 * qoyur — <p><ul>…</ul></p> alınır, bu isə düzgün HTML deyil və yenidən
	 * oxunanda boş abzasa parçalanır. Ona görə siyahını özümüz yığırıq.
	 * Eyni düyməyə ikinci dəfə basanda siyahı abzaslara açılır.
	 */
	function toList(area, tag) {
		var range = currentRange();
		if (!range) {
			return;
		}
		var lists  = selectedLists(area, range);
		var blocks = selectedBlocks(area, range).filter(function (el) {
			return !el.closest('li');
		});

		// Yalnız siyahı seçilib: ya növünü dəyişirik, ya da abzasa açırıq
		if (lists.length && !blocks.length) {
			if (lists.every(function (l) { return l.tagName === tag; })) {
				var opened = [];
				lists.forEach(function (l) {
					opened = opened.concat(unlist(l, 'P'));
				});
				reselect(area, opened);
			} else {
				reselect(area, lists.map(function (l) { return retag(l, tag); }));
			}
			return;
		}

		if (!blocks.length) {
			return;
		}

		// Yalnız eyni valideyndəki bloklar — yoxsa məzmun bir qabdan başqasına keçər
		var parent = blocks[0].parentNode;
		blocks = blocks.filter(function (el) {
			return el.parentNode === parent;
		});

		var list = document.createElement(tag);
		blocks.forEach(function (el) {
			var li = document.createElement('li');
			while (el.firstChild) {
				li.appendChild(el.firstChild);
			}
			list.appendChild(li);
		});

		parent.insertBefore(list, blocks[0]);
		blocks.forEach(function (el) {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
		reselect(area, [list]);
	}

	/* ------------------------------------------------------------ qurmaq */

	try {
		document.execCommand('defaultParagraphSeparator', false, 'p');
		document.execCommand('styleWithCSS', false, false);
	} catch (err) { /* köhnə brauzer — vacib deyil */ }

	Array.prototype.forEach.call(fields, function (textarea) {
		var wrap = document.createElement('div');
		wrap.className = 'rich';

		var bar = document.createElement('div');
		bar.className = 'rich__bar';

		var area = document.createElement('div');
		area.className = 'rich__area';
		area.setAttribute('contenteditable', 'true');
		area.setAttribute('role', 'textbox');
		area.setAttribute('aria-multiline', 'true');
		area.setAttribute('aria-label', textarea.getAttribute('data-rich') || 'Mətn');
		if (textarea.classList.contains('textarea--tall')) {
			area.className += ' rich__area--tall';
		}

		// Boş sahədə yazılan mətn abzassız qalmasın deyə hazır bir <p> qoyulur
		area.innerHTML = textarea.value.trim() === '' ? '<p><br></p>' : textarea.value;
		unfold(area);

		textarea.parentNode.insertBefore(wrap, textarea);
		wrap.appendChild(bar);
		wrap.appendChild(area);
		wrap.appendChild(textarea);
		textarea.className += ' rich__code';

		var state = { dirty: false, code: false };

		/* --- düymələr --- */
		TOOLS.forEach(function (tool) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'rich__btn' + (tool.cls ? ' ' + tool.cls : '') + (tool.right ? ' rich__btn--right' : '');
			btn.textContent = tool.label;
			btn.title = tool.title;
			btn.addEventListener('mousedown', function (e) {
				e.preventDefault(); // seçim itməsin
			});
			btn.addEventListener('click', function () {
				run(tool, btn);
			});
			bar.appendChild(btn);
		});

		function touched() {
			state.dirty = true;
			textarea.value = toStore(area);
		}

		function run(tool, btn) {
			if (tool.act === 'code') {
				toggleCode(btn);
				return;
			}
			if (state.code) {
				return; // HTML rejimində düymələr işləmir
			}
			area.focus();
			if (tool.list) {
				toList(area, tool.list);
			} else if (tool.cmd) {
				document.execCommand(tool.cmd, false, null);
			} else if (tool.block) {
				setBlock(area, tool.block);
			} else if (tool.act === 'link') {
				var url = window.prompt('Keçidin ünvanı:', 'https://');
				if (url) {
					document.execCommand('createLink', false, url);
				}
			} else if (tool.act === 'unlink') {
				document.execCommand('unlink', false, null);
			} else if (tool.act === 'clear') {
				clearAll(area);
			}
			touched();
		}

		/* --- HTML / vizual arasında keçid --- */
		function toggleCode(btn) {
			state.code = !state.code;
			wrap.className = 'rich' + (state.code ? ' rich--code' : '');
			btn.className = 'rich__btn rich__btn--right' + (state.code ? ' is-on' : '');
			if (state.code) {
				// vizualdan koda: yalnız redaktə olunubsa yenilə
				if (state.dirty) {
					textarea.value = toStore(area);
				}
				textarea.focus();
			} else {
				// koddan vizuala: yazılanı göstəririk
				area.innerHTML = textarea.value;
				unfold(area);
				area.focus();
			}
		}

		/* --- yazmaq --- */
		area.addEventListener('input', touched);

		area.addEventListener('paste', function (e) {
			var data = e.clipboardData;
			if (!data) {
				return;
			}
			var html = data.getData('text/html');
			var text = data.getData('text/plain');
			e.preventDefault();
			if (html) {
				document.execCommand('insertHTML', false, clean(html));
				unfold(area);   // yeni gələn şəkillər də göstərilə bilsin
			} else if (text) {
				document.execCommand('insertText', false, text);
			}
			touched();
		});

		// HTML rejimində əl ilə yazılan kod da nəzərə alınsın
		textarea.addEventListener('input', function () {
			state.dirty = true;
		});

		/* --- göndərməzdən əvvəl --- */
		var form = textarea.form;
		if (form) {
			form.addEventListener('submit', function () {
				// Kod rejimindədirsə textarea onsuz da əsl mənbədir.
				// Toxunulmayıbsa da ona dəymirik — məzmun olduğu kimi qalsın.
				if (!state.code && state.dirty) {
					textarea.value = toStore(area);
				}
			});
		}
	});
})();
