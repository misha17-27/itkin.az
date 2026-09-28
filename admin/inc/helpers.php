<?php
/**
 * Admin paneli üçün köməkçilər.
 */

/** Panel daxilində ünvan: admin_url(['section' => 'posts']) */
function admin_url(array $params = []): string
{
    $base = base_path() . '/' . admin_path() . '/';
    return $params ? $base . '?' . http_build_query($params) : $base;
}

/** Sonrakı səhifəyə mesajla birlikdə yönləndirir */
function admin_redirect(array $params = [], string $flash = '', string $type = 'ok'): void
{
    if ($flash !== '') {
        $_SESSION['itkin_admin_flash'] = ['text' => $flash, 'type' => $type];
    }
    header('Location: ' . admin_url($params));
    exit;
}

function admin_flash(): ?array
{
    if (empty($_SESSION['itkin_admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['itkin_admin_flash'];
    unset($_SESSION['itkin_admin_flash']);
    return $flash;
}

/** POST-dan sadə mətn */
function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    if (!is_string($value)) {
        return $default;
    }
    // yanlış UTF-8 baytları (əl ilə düzəldilmiş sorğu) faylları və JSON-u pozmasın.
    // mbstring-siz də işləyir: hostinqdə modul olmasa, heç olmasa giriş açılsın.
    if (preg_match('//u', $value) !== 1) {
        // yanlış ardıcıllıqlar «�» ilə əvəz olunur, düz hərflər qalır (yalnız PHP-nin özü ilə)
        $value = htmlspecialchars_decode(htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_NOQUOTES);
    }
    return trim(admin_eol($value));
}

/**
 * Brauzer <textarea>-dan gələn sətir sonlarını CRLF kimi göndərir.
 * Məzmunda LF saxlanılır — yoxsa toxunulmamış çoxsətirli mətn də
 * orijinaldan “fərqli” görünür və boş yerə yenidən yazılır.
 */
function admin_eol(string $text): string
{
    return str_replace(["\r\n", "\r"], "\n", $text);
}

/** POST-dan HTML (məzmun sahələri) — yalnız təhlükəsiz teqlər saxlanılır */
function post_html(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? admin_clean_html(trim(admin_eol($value))) : '';
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

/** @return int[] */
function post_ints(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        return [];
    }
    return array_values(array_unique(array_map('intval', $value)));
}

/** Tam silinən elementlər (içindəkilərlə birlikdə) */
const ADMIN_HTML_DROP = [
    'script', 'style', 'frame', 'frameset', 'object', 'embed', 'applet', 'param',
    'form', 'input', 'button', 'select', 'option', 'textarea', 'base', 'meta', 'link', 'title',
    // “xam mətn” elementləri: brauzer onları PHP-nin parserindən fərqli oxuyur —
    // təmiz görünən kod brauzerdə skriptə çevrilə bilər (mXSS)
    'noscript', 'noembed', 'noframes', 'xmp', 'plaintext', 'template',
    'svg', 'math',
];

/**
 * Yalnız bu ünvanlardan gələn video çərçivəsi (iframe) saxlanılır — YouTube və Vimeo;
 * qalan bütün iframe-lər atılır.
 */
const ADMIN_HTML_IFRAME_SRC = '#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/[A-Za-z0-9_-]{6,}([?][A-Za-z0-9_=&;.%-]*)?$|^https://player\.vimeo\.com/video/[0-9]+([?][A-Za-z0-9_=&;.%-]*)?$#';

/** Video çərçivəsində saxlanılan atributlar */
const ADMIN_HTML_IFRAME_ATTRS = ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'frameborder', 'loading', 'referrerpolicy', 'class', 'style'];

/** Ünvan saxlayan atributlar — sxemi yoxlanılır */
const ADMIN_HTML_URL_ATTRS = ['href', 'src', 'poster', 'cite', 'background', 'longdesc', 'action', 'formaction', 'data', 'ping'];

/**
 * Məzmun HTML-ini təmizləyir (xəbər, kitab, itkin, səhifə mətni — AZ və EN).
 *
 * Paneldə redaktor rolu olduğu üçün bu, təhlükəsizlik sərhədidir: redaktorun
 * yazdığı kod administratorun brauzerində (panelə baxanda) və sayt ziyarətçisində
 * işə düşməməlidir. Ona görə kod mətn kimi yox, DOM ağacı kimi oxunur:
 *   - skript, çərçivə, forma, “xam mətn” elementləri, şərhlər atılır;
 *   - hadisə atributları (on…, ayırıcısı nə olursa olsun), srcdoc, xmlns/xlink atılır;
 *   - ünvanlarda yalnız http(s), mailto, tel, nisbi yol və (şəkillər üçün) data:image qalır —
 *     entity və boşluqla gizlədilmiş javascript: da tutulur;
 *   - qalan hər şey (class, style, srcset, video, cədvəl …) saxlanılır —
 *     orijinal saytdan gələn məzmun dəyişmir.
 * Nəticə DOM-dan yenidən yazılır: brauzer onu PHP ilə eyni şəkildə oxuyur.
 */
function admin_clean_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return e(strip_tags($html));   // DOM yoxdursa təhlükəsiz tərəf: yalnız mətn
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><html><body><div id="itkin-clean-root">' . $html . '</div></body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = null;
    foreach ($doc->getElementsByTagName('div') as $div) {
        if ($div->getAttribute('id') === 'itkin-clean-root') {
            $root = $div;
            break;
        }
    }
    if ($root === null) {
        return e(strip_tags($html));
    }

    admin_clean_node($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

/** admin_clean_html() üçün: elementi və övladlarını təmizləyir */
function admin_clean_node(DOMNode $node): void
{
    // canlı siyahı dəyişəcəyi üçün əvvəlcə kopyası
    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = $child;
    }
    foreach ($children as $child) {
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction
            || $child instanceof DOMCdataSection) {
            $node->removeChild($child);
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($child->nodeName);
        if (in_array($tag, ADMIN_HTML_DROP, true) || strpos($tag, ':') !== false) {
            $node->removeChild($child);
            continue;
        }
        if ($tag === 'iframe') {
            // yalnız YouTube / Vimeo videosu; içi boşaldılır, artıq atributlar atılır
            if (!preg_match(ADMIN_HTML_IFRAME_SRC, trim($child->getAttribute('src')))) {
                $node->removeChild($child);
                continue;
            }
            while ($child->firstChild) {
                $child->removeChild($child->firstChild);
            }
            $drop = [];
            foreach ($child->attributes as $attr) {
                if (!in_array(strtolower($attr->nodeName), ADMIN_HTML_IFRAME_ATTRS, true)) {
                    $drop[] = $attr->nodeName;
                }
            }
            foreach ($drop as $name) {
                $child->removeAttribute($name);
            }
            continue;
        }

        $remove = [];
        foreach ($child->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            if (strpos($name, 'on') === 0 || in_array($name, ['srcdoc', 'formaction', 'is'], true)
                || strpos($name, 'xmlns') === 0 || strpos($name, 'xlink') === 0 || strpos($name, ':') !== false) {
                $remove[] = $attr->nodeName;
                continue;
            }
            $value = (string) $attr->nodeValue;
            if (in_array($name, ADMIN_HTML_URL_ATTRS, true)) {
                if (!admin_safe_url($value, $tag === 'img' || $tag === 'source')) {
                    $remove[] = $attr->nodeName;
                }
            } elseif ($name === 'srcset') {
                foreach (explode(',', $value) as $candidate) {
                    $url = trim(preg_split('/\s+/', trim($candidate))[0] ?? '');
                    if ($url !== '' && !admin_safe_url($url, true)) {
                        $remove[] = $attr->nodeName;
                        break;
                    }
                }
            } elseif ($name === 'style' && preg_match('/expression\s*\(|javascript\s*:|vbscript\s*:|-moz-binding|behavior\s*:/i',
                    preg_replace('/[\x00-\x20]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) {
                $remove[] = $attr->nodeName;
            }
        }
        foreach ($remove as $name) {
            $child->removeAttribute($name);
        }

        admin_clean_node($child);
    }
}

/**
 * Ünvan təhlükəsizdirmi: nisbi yol, #, ?, http(s), mailto, tel və
 * (şəkil üçün) data:image/… . Boşluq və idarəetmə simvolları atılıb yoxlanılır —
 * “java\tscript:” və “&#106;avascript:” kimi gizlətmələr də tutulur (DOM entity-ləri açır).
 */
function admin_safe_url(string $url, bool $image = false): bool
{
    $clean = strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $url));
    if ($clean === '' || !preg_match('/^([a-z][a-z0-9+.-]*):/', $clean, $m)) {
        return true;   // nisbi ünvan, #lövbər, ?sorğu
    }
    if (in_array($m[1], ['http', 'https', 'mailto', 'tel'], true)) {
        return true;
    }
    return $image && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);#', $clean) === 1;
}

/** Tarix sahəsi üçün: 2026-09-21T07:13:58 -> 2026-09-21T07:13 */
function admin_datetime_value(string $iso): string
{
    $ts = strtotime($iso);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/**
 * Formadan gələn tarixi saxlama formatına çevirir.
 *
 * <input type="datetime-local"> yalnız dəqiqəyə qədər dəyər verir, ona görə
 * dəqiqə dəyişməyibsə köhnə saniyələr saxlanılır — yoxsa hər redaktədə
 * <time datetime> və schema datePublished dəyəri xırda-xırda sürüşür.
 */
function admin_datetime_store(string $value, string $fallback): string
{
    $ts = strtotime($value);
    if (!$ts) {
        return $fallback;
    }
    $new = date('Y-m-d\TH:i:s', $ts);
    $old = strtotime($fallback);
    if ($old && substr($new, 0, 16) === substr(date('Y-m-d\TH:i:s', $old), 0, 16)) {
        return date('Y-m-d\TH:i:s', $old);
    }
    return $new;
}

/**
 * Silmə sorğusunu buraxır, yoxsa geri yönləndirir.
 *
 * Silmək yalnız POST və düzgün token ilə mümkündür. Adi keçid (GET) kifayət
 * etsəydi, panelə girmiş istifadəçinin baxdığı kənar səhifədəki gizli şəkil
 * və ya forma onun seansı ilə qeydi silə bilərdi.
 */
function admin_require_delete(string $section): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !admin_token_ok()) {
        admin_redirect(['section' => $section], 'Silmə təsdiqlənmədi. Yenidən cəhd edin.', 'error');
    }
}

/**
 * Paneldən dəyişdirilən ayarlar (data/settings.php) — config.php-nin üstünə düşür.
 * Bu fayl .gitignore-dadır: şifrənin hash-i, SMTP və kapça açarları burada saxlanılır.
 */
function admin_settings(): array
{
    $file = dirname(__DIR__, 2) . '/data/settings.php';
    $saved = is_file($file) ? include $file : [];
    return is_array($saved) ? $saved : [];
}

/** Ayarların bir hissəsini dəyişib yazır ($changes-də null — açarı silmək) */
function admin_settings_save(array $changes): void
{
    $next = admin_settings();
    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($next[$key]);
        } else {
            $next[$key] = $value;
        }
    }
    // ehtiyat nüsxə yoxdur: fayldakı şifrə və açarlar silinəndə həqiqətən silinsin
    store_save('settings', $next, 'Sayt ayarları / site settings', false);
    cfg_reset();
}

/**
 * SEO başlığı: istifadəçi yazıbsa onu, yoxsa avtomatik variantı qaytarır.
 *
 * Boş buraxmaq şüurlu seçimdir — bu halda başlıq yenidən addan qurulur,
 * yəni adı dəyişəndə SEO başlığı da özü yenilənir.
 */
function admin_seo_title(string $fallback): string
{
    $given = post_str('doc_title');
    return $given !== '' ? $given : $fallback;
}

/** Siyahıda id-yə görə sətri tapır */
function admin_find(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

/** Sətri id-yə görə əvəz edir, yoxdursa əlavə edir */
function admin_upsert(array $rows, array $item): array
{
    foreach ($rows as $i => $row) {
        if ((int) ($row['id'] ?? 0) === (int) $item['id']) {
            $rows[$i] = $item;
            return $rows;
        }
    }
    array_unshift($rows, $item);
    return $rows;
}

function admin_delete(array $rows, int $id): array
{
    return array_values(array_filter($rows, static function (array $row) use ($id) {
        return (int) ($row['id'] ?? 0) !== $id;
    }));
}

/** Boş şəkil quruluşu */
function admin_empty_thumb(): array
{
    return ['url' => '', 'srcset' => '', 'sizes' => '', 'width' => '', 'height' => '',
            'alt' => '', 'class' => 'attachment-full size-full'];
}

/**
 * Formadan gələn şəkil yolundan thumb quruluşu qurur.
 * Ölçülər faylın özündən oxunur ki, markup düzgün olsun.
 */
function admin_thumb_from_path(string $path, array $previous = []): array
{
    $path = ltrim(trim($path), '/');
    if ($path === '') {
        return admin_empty_thumb();
    }

    $thumb = $previous ?: admin_empty_thumb();
    $thumb['url'] = $path;

    // Ölçüsü dəyişibsə srcset köhnə qalmasın
    if (($previous['url'] ?? '') !== $path) {
        $thumb['srcset'] = '';
        $thumb['sizes']  = '';
    }

    $file = dirname(__DIR__, 2) . '/' . $path;
    if (is_file($file)) {
        $size = @getimagesize($file);
        if ($size) {
            $thumb['width']  = (string) $size[0];
            $thumb['height'] = (string) $size[1];
            if ($thumb['sizes'] === '') {
                $thumb['sizes'] = '(max-width: ' . $size[0] . 'px) 100vw, ' . $size[0] . 'px';
            }
        }
    }

    if (($thumb['class'] ?? '') === '') {
        $thumb['class'] = 'attachment-full size-full';
    }

    return $thumb;
}

/** Mətndən qısa təsvir */
function admin_excerpt(string $html, int $words = 30): string
{
    return excerpt($html, $words);
}

/* ---------------------------------------------------------------- menyu */

function admin_menu_is_external(string $raw): bool
{
    return (bool) preg_match('#^(?:https?:)?//#i', trim($raw));
}

/** Formadan gələn ünvanı saxlama formatına çevirir ('#' -> null) */
function admin_menu_path(string $raw)
{
    $raw = trim($raw);
    if ($raw === '' || $raw === '#') {
        return null;
    }
    if (admin_menu_is_external($raw)) {
        return $raw;
    }
    return trim($raw, '/');
}

/** Bəndin növü: səhifə, kateqoriya, yoxsa sərbəst keçid */
function admin_menu_object(string $raw): string
{
    $path = admin_menu_path($raw);
    if ($path === null || admin_menu_is_external($raw)) {
        return 'custom';
    }
    if (strpos($path, 'category/') === 0) {
        return 'category';
    }
    foreach (data_load('pages') as $page) {
        if ($page['slug'] === $path || ($path === '' && $page['slug'] === 'ana-sehife')) {
            return 'page';
        }
    }
    return 'custom';
}

function admin_menu_type(string $raw): string
{
    $object = admin_menu_object($raw);
    if ($object === 'page') {
        return 'post_type';
    }
    if ($object === 'category') {
        return 'taxonomy';
    }
    return 'custom';
}

/**
 * Səhifə bəndlərinə page_id əlavə edir — aktiv bəndin siniflərində lazımdır
 * (WordPress orada `page-item-399` kimi sinif verir).
 */
function admin_menu_attach_pages(array $items): array
{
    $pages = [];
    foreach (data_load('pages') as $page) {
        $pages[$page['slug']] = (int) $page['id'];
    }

    foreach ($items as $i => $item) {
        if (($item['object'] ?? '') === 'page' && isset($pages[$item['path']])) {
            $items[$i]['page_id'] = $pages[$item['path']];
        } else {
            unset($items[$i]['page_id']);
        }
        if (!empty($item['children'])) {
            $items[$i]['children'] = admin_menu_attach_pages($item['children']);
        }
    }

    return $items;
}

/**
 * Saxlanılmış <head> meta teqlərini yeni dəyərlərlə uzlaşdırır.
 * Teq varsa dəyəri yenilənir, yoxdursa sona əlavə olunur.
 * Bu sayədə başlıq və ya şəkil dəyişəndə paylaşma önizləməsi köhnə qalmır.
 *
 * @param array $meta    [['p'|'n', açar, dəyər], ...]
 * @param array $updates ['og:title' => '...', ...]
 */
function admin_sync_meta(array $meta, array $updates): array
{
    foreach ($updates as $key => $value) {
        $value = (string) $value;
        $found = false;

        foreach ($meta as $i => $tag) {
            if (($tag[1] ?? '') !== $key) {
                continue;
            }
            $found = true;
            if ($value === '') {
                unset($meta[$i]);
            } else {
                $meta[$i][2] = $value;
            }
        }

        if (!$found && $value !== '') {
            $meta[] = [strpos($key, 'og:') === 0 || strpos($key, 'article:') === 0 ? 'p' : 'n', $key, $value];
        }
    }

    return array_values($meta);
}

/* ---------------------------------------------------------------- ingiliscə versiya */

/**
 * Məzmunun ingiliscə tərcüməsi: data/<ad>-en.php, id => sahələr.
 * Tərcüməsi olmayan sahə saytın ingiliscə versiyasında azərbaycanca göstərilir.
 */
function admin_en_get(string $name, $id): array
{
    $rows = data_load($name . '-en');
    return (array) ($rows[$id] ?? []);
}

/**
 * Tərcüməni yazır. Boş sahələr saxlanılmır; hamısı boşdursa (və ya $fields null)
 * qeyd silinir. Heç nə dəyişməyibsə fayla toxunulmur.
 */
function admin_en_save(string $name, $id, ?array $fields): void
{
    $rows = data_load($name . '-en');
    $next = $rows;
    $clean = [];
    foreach ((array) $fields as $key => $value) {
        if (is_string($value) && trim($value) !== '') {
            $clean[$key] = $value;
        }
    }
    if ($clean) {
        $next[$id] = $clean;
    } else {
        unset($next[$id]);
    }
    if ($next === $rows) {
        return;
    }
    ksort($next);
    store_save($name . '-en', $next, 'İngiliscə tərcümə / English translation (id => sahələr)');
}

/** Formadakı ingiliscə sahə: en[<açar>] */
function post_en(string $key, bool $html = false): string
{
    $value = $_POST['en'][$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = admin_eol($value);
    return $html ? trim(admin_clean_html($value)) : trim($value);
}
