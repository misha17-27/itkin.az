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
		{ act: 'image', label: 'Şəkil', title: 'Şəkil əlavə et', cls: 'rich__btn--media' },
		{ act: 'video', label: 'Video', title: 'Video əlavə et', cls: 'rich__btn--media rich__btn--video' },
		{ act: 'clear', label: 'təmizlə', title: 'Formatı götür' },
		{ act: 'code', label: 'HTML', title: 'HTML kodunu göstər', right: true }
	];

	/* Saytın kök ünvanı — layout.php window.ITKIN-də verir (panelin ünvanı dəyişə bilər) */
	function base() {
		return (window.ITKIN && window.ITKIN.base) || '/';
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
				var fresh = el.ownerDocument.createElement(to);
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

	/*
	 * Nüsxə “ölü” sənəddə qurulur. Canlı sənəddəki nüsxədə fold() yolları nisbi
	 * hala salan kimi brauzer video və şəkli panelin ünvanına görə
	 * (/mguliyev/uploads/…) yükləməyə çalışırdı — hər hərfdə boş yerə 404 sorğusu.
	 */
	var SCRATCH = document.implementation.createHTMLDocument('');

	/* Redaktordakı görüntünü saxlanacaq HTML-ə çevirir */
	function toStore(area) {
		var clone = SCRATCH.importNode(area, true);
		fold(clone);
		dropEmptySpans(clone);
		unify(clone);
		var html = clone.innerHTML.trim();
		// boş redaktorun brauzerdən qalan qalığı
		return (html === '<br>' || html === '<p><br></p>' || html === '<p></p>') ? '' : html;
	}

	/* ------------------------------------------------------ yapışdırmaq */

	var OK_TAGS = ('p br strong b em i u sub sup ul ol li a h2 h3 h4 blockquote '
		+ 'table thead tbody tr td th img figure figcaption video source iframe div').split(' ');
	var OK_ATTRS = 'href src alt title target rel colspan rowspan width height srcset sizes'.split(' ');
	/* Redaktorun öz bloklarının (şəkil, video, YouTube/Vimeo) əlavə atributları —
	   kəsib-yapışdıranda bloklar itməsin */
	var TAG_ATTRS = {
		img: ['class', 'loading', 'decoding'],
		figure: ['class'],
		div: ['class'],
		video: ['controls', 'preload', 'poster', 'playsinline', 'muted', 'loop'],
		source: ['type'],
		iframe: ['allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'frameborder', 'class']
	};

	/*
	 * Kod “ölü” sənəddə (DOMParser) oxunur: orada brauzer şəkil yükləmir,
	 * onerror və skriptlər işə düşmür. Canlı səhifəyə yalnız təmizlənmiş düyünlər
	 * keçir — redaktorun (və ya yapışdırılan saytın) yazdığı zərərli kod
	 * administratorun brauzerində işləməsin. Qaydalar serverdəki
	 * admin_clean_html() ilə eynidir (admin/inc/helpers.php).
	 */
	var DROP_TAGS = 'script,style,frame,frameset,object,embed,applet,param,form,input,button,select,option,'
		+ 'textarea,base,meta,link,title,noscript,noembed,noframes,xmp,plaintext,template,svg,math';
	var IFRAME_OK = /^https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com)\/embed\/[A-Za-z0-9_-]{6,}([?][A-Za-z0-9_=&;.%-]*)?$|^https:\/\/player\.vimeo\.com\/video\/[0-9]+([?][A-Za-z0-9_=&;.%-]*)?$/;
	// Adı URL_ATTRS-dan fərqlidir: eyni adlı “var” yuxarıdakı siyahını (unfold/fold) əvəz edirdi
	// və srcset redaktorda tam ünvana çevrilmirdi — belə şəkillər paneldə görünmürdü.
	var SAFE_URL_ATTRS = ['href', 'src', 'poster', 'cite', 'background', 'longdesc', 'action', 'formaction', 'data', 'ping'];

	function safeUrl(value, image) {
		var v = String(value).replace(/[\u0000-\u0020\u007f]+/g, '').toLowerCase();
		var m = v.match(/^([a-z][a-z0-9+.-]*):/);
		if (!m || ['http', 'https', 'mailto', 'tel'].indexOf(m[1]) >= 0) {
			return true;
		}
		return !!image && /^data:image\/(png|jpe?g|gif|webp|avif);/.test(v);
	}

	function badAttr(tag, name, value) {
		if (name.indexOf('on') === 0 || name === 'srcdoc' || name === 'formaction'
			|| name.indexOf('xmlns') === 0 || name.indexOf('xlink') === 0 || name.indexOf(':') >= 0) {
			return true;
		}
		if (SAFE_URL_ATTRS.indexOf(name) >= 0) {
			return !safeUrl(value, tag === 'img' || tag === 'source');
		}
		if (name === 'srcset') {
			return value.split(',').some(function (c) {
				var u = c.trim().split(/\s+/)[0];
				return u && !safeUrl(u, true);
			});
		}
		if (name === 'style') {
			return /expression\(|javascript:|vbscript:|-moz-binding|behavior:/i.test(value.replace(/[\u0000-\u0020]+/g, ''));
		}
		return false;
	}

	/** Kodu ölü sənəddə oxuyub təmizləyir, <body>-ni qaytarır */
	function inert(html) {
		var doc = new DOMParser().parseFromString('<!doctype html><body>' + html, 'text/html');
		var body = doc.body;
		Array.prototype.forEach.call(body.querySelectorAll(DROP_TAGS), function (el) {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
		Array.prototype.forEach.call(body.querySelectorAll('iframe'), function (el) {
			if (!IFRAME_OK.test((el.getAttribute('src') || '').trim())) {
				el.parentNode.removeChild(el);
			} else {
				while (el.firstChild) {
					el.removeChild(el.firstChild);
				}
			}
		});
		Array.prototype.forEach.call(body.querySelectorAll('*'), function (el) {
			var tag = el.tagName.toLowerCase();
			for (var i = el.attributes.length - 1; i >= 0; i--) {
				var a = el.attributes[i];
				if (badAttr(tag, a.name.toLowerCase(), a.value)) {
					el.removeAttribute(a.name);
				}
			}
		});
		var walker = doc.createTreeWalker(body, NodeFilter.SHOW_COMMENT);
		var comments = [];
		while (walker.nextNode()) {
			comments.push(walker.currentNode);
		}
		comments.forEach(function (c) {
			c.parentNode.removeChild(c);
		});
		return body;
	}

	/** Redaktorun sahəsinə kod yükləyir — yalnız ölü sənəddə təmizlənmiş düyünlər */
	function setHtml(area, html) {
		var body = inert(html);
		area.textContent = '';
		while (body.firstChild) {
			area.appendChild(document.adoptNode(body.firstChild));
		}
	}

	/* Kənardan (Word, sayt) gələn kodu sadələşdirir */
	function clean(html) {
		var box = inert(html);
		Array.prototype.forEach.call(box.querySelectorAll('script, style, meta, link'), function (el) {
			el.parentNode.removeChild(el);
		});
		var all = box.querySelectorAll('*');
		// sondan gəlirik ki, əvəz olunan element sonrakı addımı pozmasın
		for (var i = all.length - 1; i >= 0; i--) {
			var el = all[i];
			var tag = el.tagName.toLowerCase();
			// div yalnız video çərçivəsinin sarğısı kimi saxlanılır
			if (OK_TAGS.indexOf(tag) < 0 || (tag === 'div' && el.className !== 'wp-block-embed__wrapper')) {
				while (el.firstChild) {
					el.parentNode.insertBefore(el.firstChild, el);
				}
				el.parentNode.removeChild(el);
				continue;
			}
			for (var a = el.attributes.length - 1; a >= 0; a--) {
				var name = el.attributes[a].name.toLowerCase();
				var value = el.attributes[a].value;
				if ((OK_ATTRS.indexOf(name) < 0 && (TAG_ATTRS[tag] || []).indexOf(name) < 0)
					|| /^\s*javascript:/i.test(value)) {
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
		// təmizlənəndən sonra içi boş qalan figure yapışdırılmasın
		Array.prototype.forEach.call(box.querySelectorAll('figure'), function (f) {
			if (!f.querySelector('img, video, iframe') && !/\S/.test(f.textContent)) {
				f.parentNode.removeChild(f);
			}
		});
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

	/* ------------------------------------------------------ şəkil və video */

	/*
	 * Şəkil və video məzmuna WordPress-in blok quruluşu ilə düşür (figure …),
	 * saytın köhnə məzmunu da belədir. Yollar nisbi qalır ("uploads/…") —
	 * redaktorda göstərmək üçün unfold() onları tam ünvana çevirir, saxlayanda
	 * toStore() geri qaytarır. Elementlər DOM ilə yığılır: mətn heç vaxt HTML
	 * kimi oxunmur (alt mətni, link).
	 */

	/* Kursor bunların içindədirsə, yeni blok onlardan SONRA qoyulur */
	var MEDIA_BLOCKS = 'figure, video, iframe, audio';
	/* İçinə blok qoymaq olan qablar (qalanları kursorun yerində ikiyə bölünür) */
	var HOSTS = 'div, li, td, th, blockquote, section, article, aside, dd, details';

	/* Elementdə görünən nəsə varmı (mətn və ya şəkil/video/cədvəl) */
	function hasContent(node) {
		if (node.nodeType === 3) {
			return /[^\s ​]/.test(node.data);
		}
		if (node.nodeType !== 1) {
			return false;
		}
		if (/^(IMG|VIDEO|IFRAME|AUDIO|TABLE|HR|FIGURE)$/.test(node.tagName)) {
			return true;
		}
		return /[^\s ​]/.test(node.textContent)
			|| !!node.querySelector('img, video, iframe, audio, table, hr, figure');
	}

	/*
	 * Bloku (figure) kursorun yerinə qoyur və ondan sonrakı abzası qaytarır —
	 * kursor ora keçir ki, yazmağa davam etmək olsun.
	 *
	 * Abzasın ortasındadırsa, abzas ikiyə bölünür: “Salam |dünya” ->
	 * <p>Salam</p><figure>…</figure><p>dünya</p>. Kursordan sonra heç nə
	 * yoxdursa, boş abzas (<p><br></p>) əlavə olunur. Boş qalan abzas silinir.
	 * Kursor redaktorda deyilsə (hələ basılmayıb) — mətnin sonuna.
	 */
	function placeBlock(area, block, range) {
		var next = document.createElement('p');
		next.appendChild(document.createElement('br'));

		if (!range || !area.contains(range.endContainer)) {
			area.appendChild(block);
			area.appendChild(next);
			return next;
		}

		// seçim varsa, onun sonuna qoyulur — seçilmiş mətn silinmir
		range = range.cloneRange();
		range.collapse(false);

		var node = range.endContainer;
		var el = node.nodeType === 1 ? node : node.parentNode;

		// şəklin, videonun içindədir — ondan sonra
		var media = el.closest(MEDIA_BLOCKS);
		if (media && media !== area && area.contains(media)) {
			media.parentNode.insertBefore(block, media.nextSibling);
			media.parentNode.insertBefore(next, block.nextSibling);
			return next;
		}

		// kursor cədvəlin və ya siyahının özündədir (xananın/bəndin içində yox) — bölmə, ondan sonra qoy
		if (/^(TABLE|THEAD|TBODY|TFOOT|TR|COLGROUP|UL|OL|DL)$/.test(el.tagName)) {
			var boxEl = /^(UL|OL|DL)$/.test(el.tagName) ? el : el.closest('table');
			if (boxEl && boxEl !== area && area.contains(boxEl)) {
				boxEl.parentNode.insertBefore(block, boxEl.nextSibling);
				boxEl.parentNode.insertBefore(next, block.nextSibling);
				return next;
			}
		}

		var host = el.closest(HOSTS);
		if (!host || !area.contains(host)) {
			host = area;
		}

		// host-un kursoru saxlayan birbaşa övladı (abzas, başlıq, mətn …)
		var top = node === host ? null : node;
		while (top && top.parentNode !== host) {
			top = top.parentNode;
		}

		var ref;
		if (!top) {
			ref = host.childNodes[range.endOffset] || null;
		} else {
			// kursordan sonrakı hissə eyni teqli yeni elementə keçir
			var cut = document.createRange();
			cut.setStart(range.endContainer, range.endOffset);
			cut.setEndAfter(top);
			var tail = cut.extractContents().firstChild;
			ref = top.nextSibling;
			if (!hasContent(top)) {
				host.removeChild(top);
			}
			if (tail && hasContent(tail)) {
				next = tail;
			}
		}
		host.insertBefore(block, ref);
		host.insertBefore(next, ref);
		return next;
	}

	/* Kursoru elementin əvvəlinə qoyur */
	function caretAt(node) {
		try {
			var range = document.createRange();
			range.setStart(node, 0);
			range.collapse(true);
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
		} catch (err) { /* vacib deyil */ }
	}

	/*
	 * Təmizlənmiş HTML-i kursorun yerinə yapışdırır.
	 *
	 * Şəkil/video blokları (figure) insertHTML ilə abzasın içinə düşəndə
	 * brauzer onları açıb abzasa yayır və YouTube çərçivəsi sarğısını itirir.
	 * Ona görə figure-lar düyməylə qoyulan bloklar kimi ayrıca yerləşdirilir,
	 * aradakı mətn isə adi qaydada yapışdırılır.
	 */
	function pasteHtml(area, markup) {
		var nodes = Array.prototype.slice.call(inert(markup).childNodes);
		var isFigure = function (n) {
			return n.nodeType === 1 && n.tagName === 'FIGURE';
		};
		if (!nodes.some(isFigure)) {
			document.execCommand('insertHTML', false, markup);
			return;
		}
		var chunk = document.createElement('div');
		var flush = function () {
			if (chunk.firstChild) {
				document.execCommand('insertHTML', false, chunk.innerHTML);
				chunk = document.createElement('div');
			}
		};
		nodes.forEach(function (n) {
			if (isFigure(n)) {
				flush();
				caretAt(placeBlock(area, document.adoptNode(n), currentRange()));
			} else {
				chunk.appendChild(document.adoptNode(n));
			}
		});
		flush();
	}

	/* Kitabxanadan gələn yol: yalnız öz uploads/ qovluğumuz */
	function uploadPath(path) {
		path = typeof path === 'string' ? path.trim() : '';
		return (path.indexOf('uploads/') === 0 && path.indexOf('..') < 0) ? path : '';
	}

	/*
	 * Redaktorda fayl tam ünvanla göstərilir, nisbi yol isə yanında saxlanılır —
	 * unfold() ilə eyni iş; toStore() saxlayanda "uploads/…" yolunu geri qoyur.
	 */
	function keepUrl(el, attr, path) {
		el.setAttribute(KEEP + '-' + attr, path);
	}

	/* <figure class="wp-block-image size-large"><img …></figure> */
	function imageFigure(path, alt) {
		var fig = document.createElement('figure');
		fig.className = 'wp-block-image size-large';
		var img = document.createElement('img');
		img.setAttribute('src', absolutise(path));
		img.setAttribute('alt', alt);
		img.setAttribute('loading', 'lazy');
		img.setAttribute('decoding', 'async');
		keepUrl(img, 'src', path);
		fig.appendChild(img);
		return fig;
	}

	/* <figure class="wp-block-video"><video controls …></video></figure> */
	function videoFigure(path) {
		var fig = document.createElement('figure');
		fig.className = 'wp-block-video';
		var video = document.createElement('video');
		video.setAttribute('controls', '');
		video.setAttribute('preload', 'metadata');
		video.setAttribute('src', absolutise(path));
		keepUrl(video, 'src', path);
		fig.appendChild(video);
		return fig;
	}

	function makeButton(label, cls) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = cls;
		btn.textContent = label;
		return btn;
	}

	/* YouTube / Vimeo çərçivəsi — WordPress-in “embed” bloku kimi */
	function embedFigure(embed) {
		var fig = document.createElement('figure');
		fig.className = 'wp-block-embed is-type-video is-provider-' + embed.provider + ' wp-block-embed-' + embed.provider;
		var box = document.createElement('div');
		box.className = 'wp-block-embed__wrapper';
		var frame = document.createElement('iframe');
		frame.setAttribute('src', embed.src);
		frame.setAttribute('width', '560');
		frame.setAttribute('height', '315');
		frame.setAttribute('title', embed.title);
		frame.setAttribute('allow', embed.allow);
		frame.setAttribute('allowfullscreen', '');
		frame.setAttribute('loading', 'lazy');
		// Panel “Referrer-Policy: same-origin” göndərir; YouTube isə ünvansız
		// (referrer-siz) çərçivədə videonu açmır (xəta 153)
		frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		box.appendChild(frame);
		fig.appendChild(box);
		return fig;
	}

	/* “90”, “90s”, “1m30s”, “1h2m3s” -> saniyə */
	function seconds(value) {
		value = String(value || '').trim();
		if (/^\d+s?$/.test(value)) {
			return parseInt(value, 10);
		}
		var m = /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/.exec(value);
		return m ? (+m[1] || 0) * 3600 + (+m[2] || 0) * 60 + (+m[3] || 0) : 0;
	}

	/*
	 * YouTube / Vimeo linkindən çərçivənin ünvanı. Tanınmayan link — null.
	 *   youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID, /embed/ID, /live/ID
	 *   vimeo.com/ID, vimeo.com/ID/HASH (gizli video), player.vimeo.com/video/ID
	 * Bütöv <iframe …> kodu yapışdırılsa, onun src-i götürülür.
	 */
	function videoEmbed(raw) {
		var text = String(raw || '').trim();
		var src = /^<iframe[\s>]/i.test(text) && text.match(/\ssrc\s*=\s*["']([^"']+)["']/i);
		if (src) {
			text = src[1].replace(/&amp;/g, '&');
		}
		if (/^\/\//.test(text)) {
			text = 'https:' + text;
		} else if (!/^[a-z][a-z0-9+.-]*:/i.test(text)) {
			text = 'https://' + text;
		}

		var url;
		try {
			url = new URL(text);
		} catch (err) {
			return null;
		}
		if (url.protocol !== 'https:' && url.protocol !== 'http:') {
			return null;
		}
		var host = url.hostname.toLowerCase().replace(/^(www|m|music)\./, '');
		var parts = url.pathname.split('/').filter(Boolean);
		var id = null;
		var out = null;

		if (host === 'youtu.be') {
			id = parts[0];
		} else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
			if (parts[0] === 'watch') {
				id = url.searchParams.get('v');
			} else if (['shorts', 'embed', 'live', 'v'].indexOf(parts[0]) >= 0) {
				id = parts[1];
			}
		}

		if (id !== null) {
			// /embed/videoseries (pleylist) və /embed/live_stream — tək videonun id-si deyil
			if (!/^[A-Za-z0-9_-]{11}$/.test(id || '') || id === 'videoseries' || id === 'live_stream') {
				return null;
			}
			var hash = /(?:^#|&)t=([0-9hms]+)/.exec(url.hash);
			var start = seconds(url.searchParams.get('t') || url.searchParams.get('start') || (hash && hash[1]));
			out = {
				provider: 'youtube',
				src: 'https://www.' + (host === 'youtube-nocookie.com' ? 'youtube-nocookie.com' : 'youtube.com')
					+ '/embed/' + id + (start ? '?start=' + start : ''),
				title: 'YouTube video',
				allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
			};
		} else if ((host === 'vimeo.com' || host === 'player.vimeo.com')
			&& ['showcase', 'album', 'user', 'event'].indexOf(parts[0]) < 0) {
			var at = -1;
			for (var i = 0; i < parts.length; i++) {
				if (/^\d+$/.test(parts[i])) {
					at = i;
					break;
				}
			}
			if (at < 0) {
				return null;
			}
			// gizli (unlisted) videonun açarı: vimeo.com/ID/HASH və ya ?h=HASH
			var key = url.searchParams.get('h') || parts[at + 1] || '';
			key = /^[0-9a-f]{6,}$/i.test(key) ? key : '';
			out = {
				provider: 'vimeo',
				src: 'https://player.vimeo.com/video/' + parts[at] + (key ? '?h=' + key : ''),
				title: 'Vimeo video',
				allow: 'autoplay; fullscreen; picture-in-picture; clipboard-write'
			};
		}

		// serverdəki admin_clean_html() də eyni qaydanı yoxlayır — keçməyən çərçivə saxlanılmaz
		return out && IFRAME_OK.test(out.src) ? out : null;
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
		setHtml(area, textarea.value.trim() === '' ? '<p><br></p>' : textarea.value);
		unfold(area);

		textarea.parentNode.insertBefore(wrap, textarea);
		wrap.appendChild(bar);
		wrap.appendChild(area);
		wrap.appendChild(textarea);
		textarea.className += ' rich__code';

		var state = { dirty: false, code: false, last: null };

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
			if (tool.act === 'image' || tool.act === 'video') {
				remember();
				if (tool.act === 'image') {
					closeVideo(false);
					pickImage();
				} else if (pop.hidden) {
					openVideo();
				} else {
					closeVideo(true);
				}
				return;
			}
			closeVideo(false);
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

		/* --- kursorun son yeri --- */

		/*
		 * Kitabxana pəncərəsi və ya video linki sahəsi açılanda seçim redaktordan
		 * çıxır. Şəkil/video kursorun sonuncu yerinə düşsün deyə onu yadda
		 * saxlayırıq (Range canlıdır — mətn dəyişəndə özü uyğunlaşır).
		 */
		function remember() {
			var range = currentRange();
			if (range && area.contains(range.commonAncestorContainer)) {
				state.last = range.cloneRange();
			}
		}
		document.addEventListener('selectionchange', remember);

		/* Bloku yadda qalan kursorun yerinə qoyur, kursoru ondan sonrakı abzasa aparır */
		function insertBlock(block) {
			var next = placeBlock(area, block, state.last);
			area.focus();
			caretAt(next);
			remember();
			if (block.scrollIntoView) {
				block.scrollIntoView({ block: 'nearest' });
			}
			touched();
		}

		/* --- şəkil: kitabxanadan (orada yükləmək də olur) --- */
		function pickImage() {
			var open = window.ITKIN && window.ITKIN.openLibrary;
			if (typeof open !== 'function') {
				window.alert('Şəkil kitabxanası açılmadı. Səhifəni yeniləyib yenidən cəhd edin.');
				return;
			}
			open({
				kind: 'image',
				multi: false,
				done: function (paths) {
					var path = uploadPath(paths && paths[0]);
					if (!path) {
						return;
					}
					var alt = window.prompt('Şəklin qısa təsviri (alt mətni) — görmə imkanı məhdud olanlar və '
						+ 'axtarış sistemləri üçün. Boş buraxmaq olar:', '');
					insertBlock(imageFigure(path, (alt || '').trim()));
				}
			});
		}

		/* --- video: kitabxanadan MP4 və ya YouTube / Vimeo linki --- */
		var pop = document.createElement('div');
		pop.className = 'rich__pop';
		pop.hidden = true;

		var popRow = document.createElement('div');
		popRow.className = 'rich__pop-row';
		var fromLib = makeButton('Kitabxanadan MP4 seç', 'btn btn--sm');
		var popOr = document.createElement('span');
		popOr.className = 'rich__pop-or';
		popOr.textContent = 'və ya';
		// type="url" deyil: yarımçıq link formanın özünün göndərilməsini dayandırardı.
		// name yoxdur — sahə formayla birlikdə göndərilmir.
		var link = document.createElement('input');
		link.type = 'text';
		link.className = 'input rich__pop-input';
		link.placeholder = 'YouTube və ya Vimeo linki, məs. https://youtu.be/…';
		link.setAttribute('inputmode', 'url');
		link.setAttribute('autocomplete', 'off');
		link.setAttribute('spellcheck', 'false');
		link.setAttribute('aria-label', 'YouTube və ya Vimeo linki');
		var addLink = makeButton('Əlavə et', 'btn btn--sm btn--primary');
		var closeBtn = makeButton('Bağla', 'btn btn--sm');
		[fromLib, popOr, link, addLink, closeBtn].forEach(function (el) {
			popRow.appendChild(el);
		});

		var popHint = document.createElement('div');
		popHint.className = 'field__hint rich__pop-hint';
		popHint.textContent = 'YouTube: youtube.com/watch?v=…, youtu.be/…, youtube.com/shorts/… · Vimeo: vimeo.com/…';
		var popMsg = document.createElement('div');
		popMsg.className = 'rich__pop-msg';
		popMsg.setAttribute('role', 'alert');

		pop.appendChild(popRow);
		pop.appendChild(popHint);
		pop.appendChild(popMsg);
		wrap.insertBefore(pop, area);

		var videoBtn = bar.querySelector('.rich__btn--video');

		function say(text) {
			popMsg.textContent = text;
		}

		function openVideo() {
			pop.hidden = false;
			if (videoBtn) {
				videoBtn.classList.add('is-on');
			}
			say('');
			link.focus();
		}

		/* back — kursoru redaktora qaytarmaq (Bağla, Esc) */
		function closeVideo(back) {
			if (pop.hidden) {
				return;
			}
			pop.hidden = true;
			if (videoBtn) {
				videoBtn.classList.remove('is-on');
			}
			link.value = '';
			say('');
			if (back) {
				var last = state.last;
				area.focus();
				if (last && area.contains(last.startContainer)) {
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(last);
				}
			}
		}

		function addVideoLink() {
			var value = link.value.trim();
			if (value === '') {
				say('Videonun linkini yapışdırın (YouTube və ya Vimeo).');
				link.focus();
				return;
			}
			var embed = videoEmbed(value);
			if (!embed) {
				say('Bu link tanınmadı. Yalnız YouTube (youtube.com/watch?v=…, youtu.be/…, youtube.com/shorts/…) '
					+ 'və Vimeo (vimeo.com/…) videolarını əlavə etmək olar. Başqa video üçün MP4 faylını kitabxanadan seçin.');
				link.focus();
				link.select();
				return;
			}
			closeVideo(false);
			insertBlock(embedFigure(embed));
		}

		fromLib.addEventListener('click', function () {
			var open = window.ITKIN && window.ITKIN.openLibrary;
			closeVideo(false);
			if (typeof open !== 'function') {
				window.alert('Kitabxana açılmadı. Səhifəni yeniləyib yenidən cəhd edin.');
				return;
			}
			open({
				kind: 'video',
				multi: false,
				done: function (paths) {
					var path = uploadPath(paths && paths[0]);
					if (path) {
						insertBlock(videoFigure(path));
					}
				}
			});
		});

		addLink.addEventListener('click', addVideoLink);

		closeBtn.addEventListener('click', function () {
			closeVideo(true);
		});

		link.addEventListener('input', function () {
			say('');
		});

		link.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();   // Enter bütöv formanı göndərməsin
				addVideoLink();
			} else if (e.key === 'Escape') {
				e.preventDefault();
				closeVideo(true);
			}
		});

		/* --- HTML / vizual arasında keçid --- */
		function toggleCode(btn) {
			state.code = !state.code;
			wrap.className = 'rich' + (state.code ? ' rich--code' : '');
			btn.className = 'rich__btn rich__btn--right' + (state.code ? ' is-on' : '');
			closeVideo(false);
			if (state.code) {
				// vizualdan koda: yalnız redaktə olunubsa yenilə
				if (state.dirty) {
					textarea.value = toStore(area);
				}
				textarea.focus();
			} else {
				// koddan vizuala: yazılanı göstəririk
				setHtml(area, textarea.value);
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
				pasteHtml(area, clean(html));
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
