<?php
/**
 * Yüklənən faylın məzmun yoxlaması / upload content check.
 *
 * Şəkillər bölməsi faylı yalnız uzantısına görə qəbul etsəydi, adı “.jpg”
 * olan HTML səhifəsi və ya skriptli SVG də saytın öz ünvanından açılardı.
 * Burada faylın içi uzantı ilə tutuşdurulur, SVG isə DOM səviyyəsində
 * yoxlanılır: skript, hadisə atributları, javascript:/data: ünvanları və
 * kənar saytdan yüklənən fayllar rədd edilir.
 *
 * SVG-nin ölçüsü, element/atribut sayı, dərinliyi və stildəki url() sayı
 * məhduddur — yoxlamanın yaddaşı və vaxtı fayldan asılı olmayaraq kiçik qalır
 * (libxml-in yaddaşı PHP-nin memory_limit-inə daxil deyil, ona görə həddlər
 * parserə verməzdən əvvəl xam mətndə yoxlanılır).
 *
 * Fayl heç vaxt icra olunmur və include edilmir — yalnız oxunur.
 *
 * İstifadə:
 *   $problem = upload_content_problem($tmpFile, 'svg');
 *   if ($problem !== null) { ... “$name ($problem)” ... }
 */

const UPLOAD_SVG_NS   = 'http://www.w3.org/2000/svg';
const UPLOAD_XHTML_NS = 'http://www.w3.org/1999/xhtml';

/** Şəkil uzantısı => [gözlənilən IMAGETYPE_*, mesajdakı adı] */
const UPLOAD_IMAGE_TYPES = [
    'jpg'  => [IMAGETYPE_JPEG, 'JPEG'],
    'jpeg' => [IMAGETYPE_JPEG, 'JPEG'],
    'png'  => [IMAGETYPE_PNG, 'PNG'],
    'gif'  => [IMAGETYPE_GIF, 'GIF'],
    'webp' => [IMAGETYPE_WEBP, 'WebP'],
];

/**
 * SVG-də olmamalı elementlər (kiçik hərflə, ad fəzasından asılı olmayaraq).
 * Hamısı ya kod işlədir, ya da başqa sənəd/plagin yükləyir.
 */
const UPLOAD_SVG_BAD_TAGS = [
    'script', 'foreignobject', 'iframe', 'frame', 'frameset', 'embed', 'object',
    'applet', 'portal', 'handler', 'listener', 'animation', 'base', 'meta', 'link',
];

/** Atribut dəyərində heç yerdə olmamalı sxemlər */
const UPLOAD_SCRIPT_SCHEMES = ['javascript:', 'vbscript:', 'livescript:'];

/** Yalnız bu data: şəkilləri icazəlidir (rastr, skriptsiz) */
const UPLOAD_DATA_IMAGE = '#^data:image/(png|jpeg|gif|webp);base64,#';

/** SVG-də qəbul olunan kodlaşdırmalar — hamısı ASCII ilə uyğundur */
const UPLOAD_SVG_ENCODINGS = '/^(utf-8|us-ascii|ascii|iso-8859-\d{1,2}|latin1|windows-125\d)$/';

/** SVG animasiya elementləri (href-i dəyişə bilərlər) */
const UPLOAD_SVG_ANIMATE = ['set', 'animate', 'animatecolor', 'animatemotion', 'animatetransform'];

/*
 * SVG həddləri. Loqo və ikonlar bunlardan dəfələrlə kiçikdir; həddlər
 * yoxlamanın (və libxml-in) yaddaşını/vaxtını əvvəlcədən məlum saxlayır.
 */
const UPLOAD_SVG_MAX_BYTES       = 2097152;  // 2 MB
const UPLOAD_SVG_MAX_NODES       = 50000;    // element + şərh + PI + CDATA
const UPLOAD_SVG_MAX_ATTRS       = 200000;   // bütün sənəddə (libxml-də hər biri ~250 bayt)
const UPLOAD_SVG_MAX_ELEM_ATTRS  = 256;      // bir elementdə (xmlns daxil); libxml təkrarları kvadratik yoxlayır
const UPLOAD_SVG_MAX_NS_IN_SCOPE = 256;      // eyni anda qüvvədə olan xmlns bəyannamələri
const UPLOAD_SVG_MAX_DEPTH       = 256;      // iç-içəlik; libxml-in öz həddi 256-dan azca çoxdur
const UPLOAD_CSS_MAX_URLS        = 10000;    // bir stil mətnində url( sayı
const UPLOAD_SVG_MAX_ANIM_VALUES = 10000;    // href/src/style animasiyalarında “;” ilə ayrılmış dəyərlər (bütün sənəddə)


/**
 * Faylın məzmunu uzantıya uyğundursa və saytdan təhlükəsiz verilə bilərsə
 * null, yoxsa qısa səbəb qaytarır (məs. “fayl JPEG deyil”, “SVG-də skript var”).
 *
 * @param string $file müvəqqəti fayl (tmp_name)
 * @param string $ext  kiçik hərflə, artıq icazə verilmiş uzantı
 */
function upload_content_problem(string $file, string $ext): ?string
{
    // phar:// və s. axın ünvanları — yalnız adi fayl yolu qəbul olunur
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $file) || !is_file($file) || !is_readable($file)) {
        return 'fayl oxunmadı';
    }
    clearstatcache(true, $file);
    if ((int) @filesize($file) === 0) {
        return 'fayl boşdur';
    }

    $ext    = strtolower($ext);
    $images = UPLOAD_IMAGE_TYPES;
    if (isset($images[$ext])) {
        return upload_image_problem($file, $images[$ext][0], $images[$ext][1]);
    }
    if ($ext === 'pdf') {
        return upload_pdf_problem($file);
    }
    if ($ext === 'mp4') {
        return upload_mp4_problem($file);
    }
    if ($ext === 'svg') {
        return upload_svg_problem($file);
    }
    return 'icazə verilməyən format';
}

/** Faylın əvvəlindən $len bayt oxuyur (oxunmasa false) */
function upload_head(string $file, int $len)
{
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return false;
    }
    $head = (string) fread($fh, $len);
    fclose($fh);
    return $head;
}

/** Mesajlar üçün: 50000 => “50 000” */
function upload_num(int $n): string
{
    return number_format($n, 0, '', ' ');
}

/* ---------------------------------------------------------------- rastr, PDF, MP4 */

/** getimagesize() şəkli tanımalı və növü uzantı ilə eyni olmalıdır */
function upload_image_problem(string $file, int $type, string $label): ?string
{
    $info = @getimagesize($file);
    if (!is_array($info) || empty($info[2])) {
        return 'fayl ' . $label . ' deyil';
    }
    if ((int) $info[2] !== $type) {
        $real = strtoupper((string) image_type_to_extension((int) $info[2], false));
        return 'fayl ' . $label . ' deyil' . ($real !== '' ? ' (əslində ' . $real . ')' : '');
    }
    return null;
}

/** PDF “%PDF-” ilə başlamalıdır; spesifikasiyaya görə əvvəldə 1024 bayta qədər zibil ola bilər */
function upload_pdf_problem(string $file): ?string
{
    $head = upload_head($file, 1024 + 5);
    if ($head === false) {
        return 'fayl oxunmadı';
    }
    $pos = strpos($head, '%PDF-');
    return ($pos === false || $pos > 1024) ? 'fayl PDF deyil' : null;
}

/** MP4 (ISO BMFF): 4–7-ci baytlar “ftyp” */
function upload_mp4_problem(string $file): ?string
{
    $head = upload_head($file, 12);
    if ($head === false) {
        return 'fayl oxunmadı';
    }
    return (strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') ? 'fayl MP4 deyil' : null;
}

/* ---------------------------------------------------------------- SVG */

/**
 * SVG düzgün XML olmalı, kök elementi <svg> olmalı və içində işə düşə
 * biləcək heç nə olmamalıdır. Yoxlama DOM üzərində aparılır: atribut
 * dəyərlərini parser özü açır (&#x61; => a), biz isə boşluqları silirik.
 */
function upload_svg_problem(string $file): ?string
{
    $tooBig = 'SVG çox böyükdür (ən çoxu ' . (UPLOAD_SVG_MAX_BYTES >> 20) . ' MB)';
    if ((int) @filesize($file) > UPLOAD_SVG_MAX_BYTES) {
        return $tooBig;
    }
    $raw = @file_get_contents($file, false, null, 0, UPLOAD_SVG_MAX_BYTES + 1);
    if ($raw === false) {
        return 'fayl oxunmadı';
    }
    if (strlen($raw) > UPLOAD_SVG_MAX_BYTES) {
        return $tooBig;
    }
    if (trim($raw) === '') {
        return 'fayl boşdur';
    }

    // UTF-16/32, EBCDIC və s.: xam mətni və brauzerin gördüyünü eyni saxlamaq üçün
    // yalnız ASCII ilə uyğun kodlaşdırmalar qəbul olunur
    if (strpos($raw, "\0") !== false) {
        return 'SVG UTF-8 kodlaşdırmasında olmalıdır';
    }
    if (!preg_match('/^(\xEF\xBB\xBF)?\s*</', $raw)) {
        return 'fayl SVG deyil';
    }
    $enc = upload_svg_declared_encoding($raw);
    if ($enc === false) {
        return 'SVG düzgün XML deyil (sətir 1)';
    }
    if ($enc !== null && !preg_match(UPLOAD_SVG_ENCODINGS, strtolower(trim($enc)))) {
        return 'SVG UTF-8 kodlaşdırmasında olmalıdır';
    }

    // DTD ümumiyyətlə olmamalıdır: ENTITY ilə həm XXE, həm də brauzerdə açılan
    // gizli <script> qurmaq olur. Ölçü həddləri ilə birlikdə parserə verməzdən
    // əvvəl yoxlanılır (şərhlərin içindəki “<!DOCTYPE” mətni sayılmır).
    $problem = upload_svg_prescan($raw);
    if ($problem !== null) {
        return $problem;
    }

    $doc = upload_svg_parse($raw, $error);
    if ($doc === null) {
        return 'SVG düzgün XML deyil' . ($error !== '' ? ' (' . $error . ')' : '');
    }

    if ($doc->doctype !== null) {
        return 'SVG-də DOCTYPE var';
    }
    $enc = strtolower((string) $doc->xmlEncoding);
    if ($enc !== '' && !preg_match(UPLOAD_SVG_ENCODINGS, $enc)) {
        return 'SVG UTF-8 kodlaşdırmasında olmalıdır';
    }

    $root = $doc->documentElement;
    if (!$root || $root->localName !== 'svg'
        || ($root->namespaceURI !== null && $root->namespaceURI !== UPLOAD_SVG_NS)) {
        return 'fayl SVG deyil (kök element <svg> deyil)';
    }

    // Bütün düyünlər (sənəd səviyyəsindəki PI-lər daxil) sənəd sırası ilə gəzilir.
    // Yığın saxlanılmır: yaddaşda hər an yalnız cari düyün olur.
    $animValues = 0;
    $node = $doc->firstChild;
    while ($node !== null) {
        $problem = upload_svg_node_problem($node, $animValues);
        if ($problem !== null) {
            return $problem;
        }
        if ($node->firstChild !== null) {
            $node = $node->firstChild;
            continue;
        }
        while ($node !== null && $node->nextSibling === null) {
            $parent = $node->parentNode;
            $node   = ($parent === null || $parent->nodeType === XML_DOCUMENT_NODE) ? null : $parent;
        }
        if ($node !== null) {
            $node = $node->nextSibling;
        }
    }

    return null;
}

/**
 * <?xml … encoding="…"?> bəyannaməsindəki kodlaşdırma: yoxdursa null,
 * bəyannamə bağlanmayıbsa və ya oxunmursa false.
 */
function upload_svg_declared_encoding(string $raw)
{
    $start = strncmp($raw, "\xEF\xBB\xBF", 3) === 0 ? 3 : 0;
    if (substr($raw, $start, 5) !== '<?xml' || strspn($raw, " \t\r\n", $start + 5, 1) !== 1) {
        return null;
    }
    $end = strpos($raw, '?>', $start);
    if ($end === false) {
        return false;
    }
    // libxml “encoding”-i qabağında boşluq olmasa da oxuyur (xəta verə-verə), ona görə \b
    $found = preg_match('/\bencoding\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', substr($raw, $start, $end - $start), $m);
    if ($found === false) {
        return false;
    }
    return $found ? ($m[1] ?? '') . ($m[2] ?? '') : null;
}

/**
 * Xam mətnin parserdən əvvəlki yoxlaması. Mətni XML-in öz qaydası ilə
 * hissələrə ayırır — şərh, CDATA, PI, bəyannamə, bağlanan və açılan teq
 * (atributları ilə) — və:
 *  - DOCTYPE/ENTITY-ni rədd edir (şərhin, CDATA-nın, PI-nin içindəki mətni yox);
 *  - düyün, atribut, xmlns sayını və dərinliyi həddlərlə tutuşdurur;
 *  - bu bölgüyə sığmayan hər şeyi (məs. atribut dəyərində “<”) düzgün XML
 *    saymır. Belə olanda libxml mətni bizdən fərqli oxuya bilərdi — onda
 *    saydıqlarımız onun gördüyü ilə üst-üstə düşməzdi.
 * Düzgün XML-də bu bölgü libxml-inki ilə eynidir.
 */
function upload_svg_prescan(string $raw): ?string
{
    $len   = strlen($raw);
    $ws    = " \t\r\n";
    $stop  = " \t\r\n/><\"'=";   // ad bu simvollarda bitir
    $nodes = 0;
    $attrs = 0;
    $open  = [];                  // açıq elementlərin hər biri üçün onun xmlns sayı
    $ns    = 0;                   // qüvvədə olan xmlns bəyannamələri
    $pos   = 0;

    $bad = static function (int $at) use ($raw): string {
        return 'SVG düzgün XML deyil (sətir ' . (substr_count(substr($raw, 0, $at), "\n") + 1) . ')';
    };
    $tooMany = 'SVG-də çox element var (ən çoxu ' . upload_num(UPLOAD_SVG_MAX_NODES) . ')';

    while (($lt = strpos($raw, '<', $pos)) !== false) {
        $next = $raw[$lt + 1] ?? '';

        if ($next === '!') {
            if (substr($raw, $lt, 4) === '<!--') {
                $end = strpos($raw, '-->', $lt + 4);
                if ($end === false) {
                    return $bad($lt);
                }
                if (++$nodes > UPLOAD_SVG_MAX_NODES) {
                    return $tooMany;
                }
                $pos = $end + 3;
                continue;
            }
            if (substr($raw, $lt, 9) === '<![CDATA[') {
                $end = strpos($raw, ']]>', $lt + 9);
                if ($end === false || !$open) {      // CDATA yalnız elementin içində ola bilər
                    return $bad($lt);
                }
                if (++$nodes > UPLOAD_SVG_MAX_NODES) {
                    return $tooMany;
                }
                $pos = $end + 3;
                continue;
            }
            $word = strtoupper(substr($raw, $lt + 2, 7));
            if ($word === 'DOCTYPE') {
                return stripos($raw, '<!ENTITY', $lt) !== false ? 'SVG-də ENTITY bəyannaməsi var' : 'SVG-də DOCTYPE var';
            }
            if (strncmp($word, 'ENTITY', 6) === 0) {
                return 'SVG-də ENTITY bəyannaməsi var';
            }
            return $bad($lt);
        }

        if ($next === '?') {
            /* PI “?>”-də bitir; içində “<” olmamalıdır (bəyannamə xətasında libxml “>”-ə qədər atlayır) */
            $end = strpos($raw, '?>', $lt + 2);
            if ($end === false || strcspn($raw, '<', $lt + 2, $end - $lt - 2) !== $end - $lt - 2) {
                return $bad($lt);
            }
            if (++$nodes > UPLOAD_SVG_MAX_NODES) {
                return $tooMany;
            }
            $pos = $end + 2;
            continue;
        }

        if ($next === '/') {
            // </ad boşluq? >
            $name = strcspn($raw, $stop, $lt + 2);
            $p    = $lt + 2 + $name;
            $p   += strspn($raw, $ws, $p);
            if ($name === 0 || ($raw[$p] ?? '') !== '>') {
                return $bad($lt);
            }
            if ($open) {
                $ns -= array_pop($open);
            }
            $pos = $p + 1;
            continue;
        }

        // Açılan teq: <ad (boşluq ad boşluq? = boşluq? "dəyər")* boşluq? /? >
        if (++$nodes > UPLOAD_SVG_MAX_NODES) {
            return $tooMany;
        }
        $name = strcspn($raw, $stop, $lt + 1);
        if ($name === 0) {
            return $bad($lt);
        }
        $p       = $lt + 1 + $name;
        $count   = 0;
        $ownNs   = 0;
        $closed  = false;
        while (true) {
            $gap = strspn($raw, $ws, $p);
            $p  += $gap;
            $c   = $raw[$p] ?? '';
            if ($c === '>') {
                $p++;
                break;
            }
            if ($c === '/') {
                if (($raw[$p + 1] ?? '') !== '>') {
                    return $bad($p);
                }
                $p     += 2;
                $closed = true;
                break;
            }
            if ($c === '' || $gap === 0) {           // fayl bitdi və ya atributlar arasında boşluq yoxdur
                return $bad($p);
            }

            $an = strcspn($raw, $stop, $p);
            if ($an === 0) {
                return $bad($p);
            }
            if (++$count > UPLOAD_SVG_MAX_ELEM_ATTRS) {
                return 'SVG elementində çox atribut var (ən çoxu ' . UPLOAD_SVG_MAX_ELEM_ATTRS . ')';
            }
            if (++$attrs > UPLOAD_SVG_MAX_ATTRS) {
                return 'SVG-də çox atribut var (ən çoxu ' . upload_num(UPLOAD_SVG_MAX_ATTRS) . ')';
            }
            $aname = substr($raw, $p, $an);
            if ($aname === 'xmlns' || strncmp($aname, 'xmlns:', 6) === 0) {
                $ownNs++;
            }
            $p += $an;
            $p += strspn($raw, $ws, $p);
            if (($raw[$p] ?? '') !== '=') {
                return $bad($p);
            }
            $p++;
            $p += strspn($raw, $ws, $p);
            $q  = $raw[$p] ?? '';
            if ($q !== '"' && $q !== "'") {
                return $bad($p);
            }
            $p += 1 + strcspn($raw, '<' . $q, $p + 1);
            if (($raw[$p] ?? '') !== $q) {            // bağlanmayıb və ya dəyərdə “<” var
                return $bad($p);
            }
            $p++;
        }

        if ($ns + $ownNs > UPLOAD_SVG_MAX_NS_IN_SCOPE) {
            return 'SVG-də çox xmlns bəyannaməsi var (ən çoxu ' . UPLOAD_SVG_MAX_NS_IN_SCOPE . ')';
        }
        if (!$closed) {
            if (count($open) >= UPLOAD_SVG_MAX_DEPTH) {
                return 'SVG-də elementlər çox dərin iç-içədir (ən çoxu ' . UPLOAD_SVG_MAX_DEPTH . ' səviyyə)';
            }
            $open[] = $ownNs;
            $ns    += $ownNs;
        }
        $pos = $p;
    }

    return null;
}

/**
 * XML-i şəbəkəsiz və xarici entity-siz oxuyur.
 * Uğursuz olsa null qaytarır, $error-a ilk xətanın sətrini yazır.
 */
function upload_svg_parse(string $raw, ?string &$error): ?DOMDocument
{
    $error = '';
    $prevErrors = libxml_use_internal_errors(true);
    libxml_clear_errors();

    // PHP 8-də xarici entity-lər onsuz da yüklənmir, funksiya isə köhnəlib
    $prevLoader = null;
    if (PHP_VERSION_ID < 80000) {
        $prevLoader = libxml_disable_entity_loader(true);
    }

    $doc = new DOMDocument();
    // LIBXML_NOENT / DTDLOAD / XINCLUDE / PARSEHUGE qəsdən verilmir
    $ok = $doc->loadXML($raw, LIBXML_NONET);

    $bad = !$ok;
    foreach (libxml_get_errors() as $err) {
        // Xəbərdarlıqlar keçir; xətalar (məs. elan olunmamış “xlink:” prefiksi) yox —
        // brauzer də belə faylı açmır
        if ($err->level >= LIBXML_ERR_ERROR) {
            $bad = true;
            if ($error === '') {
                $error = 'sətir ' . (int) $err->line;
            }
        }
    }
    libxml_clear_errors();

    if (PHP_VERSION_ID < 80000) {
        libxml_disable_entity_loader($prevLoader);
    }
    libxml_use_internal_errors($prevErrors);

    return $bad ? null : $doc;
}

/**
 * Bir düyünün (element, PI, entity) problemi.
 * $animValues — sənəddə indiyədək yoxlanmış animasiya dəyərlərinin sayı.
 */
function upload_svg_node_problem(DOMNode $node, int &$animValues = 0): ?string
{
    switch ($node->nodeType) {
        case XML_ELEMENT_NODE:
            return upload_svg_element_problem($node, $animValues);

        case XML_PI_NODE:
            $target = strtolower((string) $node->target);
            if ($target === 'xml-stylesheet') {
                return 'SVG-də <?xml-stylesheet?> var';
            }
            if (strpos($target, 'php') === 0) {
                return 'SVG-də PHP kodu var';
            }
            return null;

        case XML_DOCUMENT_TYPE_NODE:
        case XML_DTD_NODE:
            return 'SVG-də DOCTYPE var';

        case XML_ENTITY_REF_NODE:
        case XML_ENTITY_NODE:
        case XML_ENTITY_DECL_NODE:
            return 'SVG-də ENTITY bəyannaməsi var';
    }
    return null;
}

/** Element: adı, atributları, <style> məzmunu və animasiyanın dəyişdirdiyi href */
function upload_svg_element_problem(DOMElement $el, int &$animValues = 0): ?string
{
    $tag = upload_local_name($el);

    // SVG-nin içində HTML elementi (<foreignObject>-siz də) brauzerdə HTML kimi işləyir
    if ($el->namespaceURI === UPLOAD_XHTML_NS) {
        return 'SVG-də HTML elementi var: <' . $tag . '>';
    }
    if ($tag === 'script') {
        return 'SVG-də skript var';
    }
    if ($tag === 'foreignobject') {
        return 'SVG-də <foreignObject> var';
    }
    if (in_array($tag, UPLOAD_SVG_BAD_TAGS, true)) {
        return 'SVG-də icazəsiz element var: <' . $tag . '>';
    }

    if ($tag === 'style') {
        // <style> içində element olmur; olsaydı, iç-içə <style>-ların hər biri
        // bütün alt mətni təkrar yoxlayardı
        for ($child = $el->firstChild; $child !== null; $child = $child->nextSibling) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return 'SVG-də <style> içində element var';
            }
        }
        $problem = upload_css_problem((string) $el->textContent);
        if ($problem !== null) {
            return $problem;
        }
    }

    $isLink = $tag === 'a';
    foreach ($el->attributes as $attr) {
        $problem = upload_svg_attr_problem($attr, $isLink);
        if ($problem !== null) {
            return $problem;
        }
    }

    // <set attributeName="href" to="…"> — href-i sonradan dəyişir
    if (in_array($tag, UPLOAD_SVG_ANIMATE, true)) {
        $target = upload_flat((string) $el->getAttribute('attributeName'));
        $pos    = strrpos($target, ':');
        $target = $pos === false ? $target : substr($target, $pos + 1);
        if (strpos($target, 'on') === 0) {
            return 'SVG-də hadisə atributunu dəyişən animasiya var';
        }
        if ($target === 'href' || $target === 'src' || $target === 'base' || $target === 'style') {
            foreach (['to', 'from', 'by', 'values'] as $name) {
                // “;” ilə ayrılmış siyahı — massivə bölmədən, bir-bir
                $list = (string) $el->getAttribute($name);
                if ($list === '') {
                    continue;
                }
                $animValues += substr_count($list, ';') + 1;
                if ($animValues > UPLOAD_SVG_MAX_ANIM_VALUES) {
                    return 'SVG-də çox animasiya dəyəri var (ən çoxu ' . upload_num(UPLOAD_SVG_MAX_ANIM_VALUES) . ')';
                }
                $start = 0;
                do {
                    $end   = strpos($list, ';', $start);
                    $value = (string) ($end === false ? substr($list, $start) : substr($list, $start, $end - $start));
                    $problem = $target === 'style' ? upload_css_problem($value) : upload_url_problem($value);
                    if ($problem !== null) {
                        return $problem;
                    }
                    $start = (int) $end + 1;
                } while ($end !== false);
            }
        }
    }

    return null;
}

/** Atribut: on*, javascript:, href/src ünvanı, style və url(...) */
function upload_svg_attr_problem(DOMAttr $attr, bool $isLink): ?string
{
    $name  = upload_local_name($attr);
    $value = (string) $attr->value;
    $flat  = upload_flat($value);

    if (strpos($name, 'on') === 0) {
        return 'SVG-də “' . $attr->nodeName . '” hadisə atributu var';
    }
    foreach (UPLOAD_SCRIPT_SCHEMES as $scheme) {
        if (strpos($flat, $scheme) !== false) {
            return 'SVG-də ' . $scheme . ' ünvanı var';
        }
    }

    // href, xlink:href, src və xml:base — ünvan kimi yoxlanılır
    if ($name === 'href' || $name === 'src' || $name === 'base') {
        $problem = upload_url_problem($value, $isLink && $name === 'href');
        if ($problem !== null) {
            return $problem;
        }
    }

    // style="…" və fill="url(…)" kimi dəyərlər — CSS kimi (fill="u\72l(…)" də url-dir)
    if ($name === 'style' || strpos($flat, 'url(') !== false || strpos($value, '\\') !== false) {
        return upload_css_problem($value);
    }

    return null;
}

/**
 * Ünvanı yoxlayır.
 * $link = true yalnız <a href> üçündür: keçid başqa sayta apara bilər, amma
 * heç nə yükləmir. Qalan hallarda ünvan saytın özündə olmalıdır.
 */
function upload_url_problem(string $url, bool $link = false): ?string
{
    // Brauzer də ünvandakı tab/sətir sonunu atır, “\”-ni “/” kimi oxuyur
    $flat = strtr(upload_flat($url), '\\', '/');
    if ($flat === '' || $flat[0] === '#') {
        return null;
    }

    if (strpos($flat, 'data:') === 0) {
        return preg_match(UPLOAD_DATA_IMAGE, $flat) ? null : 'SVG-də icazəsiz data: ünvanı var';
    }

    if (preg_match('#^([a-z][a-z0-9+.\-]*):#', $flat, $m)) {
        if (in_array($m[0], UPLOAD_SCRIPT_SCHEMES, true)) {
            return 'SVG-də ' . $m[0] . ' ünvanı var';
        }
        if ($m[1] === 'http' || $m[1] === 'https') {
            return ($link || upload_is_own_url($flat)) ? null : 'SVG kənar ünvandan fayl yükləyir';
        }
        if ($link && ($m[1] === 'mailto' || $m[1] === 'tel')) {
            return null;
        }
        return 'SVG-də icazəsiz ünvan var (' . $m[0] . ')';
    }

    // “//host/…” — sxemsiz, amma yenə kənar sayt
    if (strpos($flat, '//') === 0) {
        return ($link || upload_is_own_url('https:' . $flat)) ? null : 'SVG kənar ünvandan fayl yükləyir';
    }

    // Nisbi yol — saytın özündədir
    return null;
}

/** Tam ünvan saytın öz domenindədirmi (www. fərqi nəzərə alınmır) */
function upload_is_own_url(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '' || parse_url($url, PHP_URL_USER) !== null) {
        return false;
    }

    $own = function_exists('cfg') ? trim((string) cfg('site_url', '')) : '';
    $own = $own !== '' ? (string) parse_url($own, PHP_URL_HOST) : (string) ($_SERVER['HTTP_HOST'] ?? '');
    $own = strtolower(preg_replace('/:\d+$/', '', $own));
    if ($own === '') {
        return false;
    }

    $strip = static function (string $h): string {
        return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
    };
    return $strip($host) === $strip($own);
}

/**
 * CSS (<style> və ya style="…") yoxlaması: javascript:, expression(),
 * köhnə IE/Firefox “behavior”/“-moz-binding” və kənar sayta url()/@import.
 */
function upload_css_problem(string $css): ?string
{
    // Şərhlər həm silinmiş, həm də saxlanılmış halda yoxlanılır: “ex/**/pression(”
    // birləşsin, sətir içindəki “/*” isə şərh kimi götürülüb nəyisə gizlətməsin
    $variants = [$css];
    if (strpos($css, '/*') !== false) {
        $variants[] = upload_css_strip_comments($css);
    }

    foreach ($variants as $text) {
        $text = upload_css_unescape($text);
        if ($text === null) {
            return 'SVG stili yoxlanıla bilmədi';
        }
        $flat = upload_flat($text);

        foreach (UPLOAD_SCRIPT_SCHEMES as $scheme) {
            if (strpos($flat, $scheme) !== false) {
                return 'SVG stilində ' . $scheme . ' var';
            }
        }
        if (strpos($flat, 'expression(') !== false) {
            return 'SVG stilində expression() var';
        }
        if (strpos($flat, 'behavior:') !== false || strpos($flat, '-moz-binding') !== false) {
            return 'SVG stilində icazəsiz xassə var';
        }

        $problem = upload_css_url_problem($text);
        if ($problem !== null) {
            return $problem;
        }
    }

    return null;
}

/** CSS şərhlərini (“/* … *\/”, bağlanmayanı mətnin sonuna qədər) silir */
function upload_css_strip_comments(string $css): string
{
    $out = '';
    $pos = 0;
    while (($start = strpos($css, '/*', $pos)) !== false) {
        $out .= substr($css, $pos, $start - $pos);
        $end  = strpos($css, '*/', $start + 2);
        if ($end === false) {
            return $out;
        }
        $pos = $end + 2;
    }
    return $out . substr($css, $pos);
}

/**
 * CSS-dəki ünvanları yoxlayır: url(…) arqumentləri və ünvana oxşayan
 * dırnaqlı sətirlər (@import "…", image-set("…") və s.). İlk problemi qaytarır.
 *
 * Hər url( arqumenti ən çoxu növbəti “url(”-a qədər oxunur, ona görə
 * arqumentlər üst-üstə düşmür və iş mətnin uzunluğu ilə mütənasibdir.
 * Növbəti url(-da kəsilən arqument (CSS-də belə ünvan yüklənmir) yalnız
 * “#…” və ya data:image ilə başlayırsa qəbul olunur.
 */
function upload_css_url_problem(string $css): ?string
{
    $len   = strlen($css);
    $lower = strtolower($css);   // stripos PHP 7.4-də hər çağırışda bütün mətni kiçildir
    $count = 0;
    $pos   = strpos($lower, 'url(');
    while ($pos !== false) {
        if (++$count > UPLOAD_CSS_MAX_URLS) {
            return 'SVG stilində çox url() var (ən çoxu ' . upload_num(UPLOAD_CSS_MAX_URLS) . ')';
        }
        $next  = strpos($lower, 'url(', $pos + 4);
        $limit = $next === false ? $len : $next;

        // url(  boşluq  "…" | '…' | dırnaqsız )
        $start = $pos + 4;
        $start += strspn($css, " \t\r\n\f", $start, $limit - $start);
        $q = $start < $limit ? $css[$start] : '';
        if ($q === '"' || $q === "'") {
            $start++;
            $stopAt = $q;
        } else {
            $stopAt = ") \t\r\n\f";
        }
        $n   = strcspn($css, $stopAt, $start, max(0, $limit - $start));
        $url = (string) substr($css, $start, $n);
        $cut = $next !== false && $start + $n >= $limit;

        $problem = upload_url_problem($url);
        if ($problem !== null) {
            return $problem;
        }
        if ($cut) {
            $flat = strtr(upload_flat($url), '\\', '/');
            if (($flat === '' || $flat[0] !== '#') && !preg_match(UPLOAD_DATA_IMAGE, $flat)) {
                return 'SVG stilində bağlanmamış url( var';
            }
        }
        $pos = $next;
    }

    // Dırnaqlı sətirlər. Bağlanmayan dırnaq (CSS-də sətir faylın sonunda bitir)
    // mətnin sonuna qədər yoxlanılır; hər dırnaq növündən ən çoxu biri belə olar.
    $pos = 0;
    while (($pos += strcspn($css, '"\'', $pos)) < $len) {
        $q   = $css[$pos];
        $end = strpos($css, $q, $pos + 1);
        if ($end === $pos + 1) {                   // boş sətir
            $pos += 2;
            continue;
        }
        $str = (string) ($end === false ? substr($css, $pos + 1) : substr($css, $pos + 1, $end - $pos - 1));
        $flat = strtr(upload_flat($str), '\\', '/');
        if (preg_match('#^(//|(https?|ftp|file|data|blob|filesystem):)#', $flat)) {
            $problem = upload_url_problem($str);
            if ($problem !== null) {
                return $problem;
            }
        }
        $pos = $end === false ? $pos + 1 : $end + 1;
    }

    return null;
}

/** CSS qaçış ardıcıllıqlarını açır: “\6a” => “j”, “\@” => “@” (alınmasa null) */
function upload_css_unescape(string $css): ?string
{
    return preg_replace_callback('/\\\\(?:([0-9a-fA-F]{1,6})[ \t\r\n\f]?|(.))/s', static function (array $m) {
        if (isset($m[2]) && $m[2] !== '') {
            return $m[2] === "\n" ? '' : $m[2];
        }
        $cp = hexdec($m[1]);
        if ($cp <= 0 || $cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) {
            return "\u{FFFD}";
        }
        $ch = mb_chr((int) $cp, 'UTF-8');
        return $ch === false ? "\u{FFFD}" : $ch;
    }, $css);
}

/** Müqayisə üçün: kiçik hərflər, bütün boşluq/idarəetmə simvolları silinmiş */
function upload_flat(string $text): string
{
    $flat = preg_replace('/[\p{Z}\p{Cc}\p{Cf}]+/u', '', $text);
    if ($flat === null) {
        // UTF-8 deyil — ən azı ASCII boşluqları silinsin
        $flat = preg_replace('/[\x00-\x20\x7F]+/', '', $text);
    }
    return strtolower((string) $flat);
}

/** Prefikssiz, kiçik hərfli ad: “xlink:href” => “href”, “SCRIPT” => “script” */
function upload_local_name(DOMNode $node): string
{
    $name = (string) ($node->localName ?: $node->nodeName);
    $pos  = strrpos($name, ':');
    return strtolower($pos === false ? $name : substr($name, $pos + 1));
}
