<?php
/**
 * Köməkçi funksiyalar / helper functions
 */

/**
 * Ayarlar iki yerdən gəlir:
 *   config.php      — quraşdırma ayarları (ünvan, admin girişi)
 *   data/settings.php — admin panelindən dəyişdirilə bilənlər
 * İkincisi birincinin üstünə yazılır.
 */
function cfg(string $key, $default = null)
{
    $all = cfg_all();
    return $all[$key] ?? $default;
}

/** @param bool $reload ayar dəyişdikdən sonra yenidən oxumaq üçün */
function cfg_all(bool $reload = false): array
{
    static $cfg = null;
    if ($reload) {
        $cfg = null;
    }
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config.php';
        $file = dirname(__DIR__) . '/data/settings.php';
        if (is_file($file)) {
            $saved = include $file;
            if (is_array($saved)) {
                $cfg = array_replace($cfg, $saved);
            }
        }
    }
    return $cfg;
}

/** Ayar dəyişdikdən sonra keşi sıfırlayır */
function cfg_reset(): void
{
    cfg_all(true);
}

/** Saytın kök ünvanı (alt qovluqda işləməsi üçün) */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) cfg('site_url'));
    if ($configured !== '') {
        return $base = rtrim(parse_url($configured, PHP_URL_PATH) ?: '', '/');
    }
    $dir = strtr(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), DIRECTORY_SEPARATOR, '/');
    $dir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');

    // İdarə paneli /admin/ qovluğundan işləyir — sayt kökü bir səviyyə yuxarıdadır
    if (substr($dir, -6) === '/admin' || $dir === '/admin') {
        $dir = substr($dir, 0, -6);
    }

    return $base = $dir;
}

/** Daxili ünvan qurur: url('xeberler') => /xeberler/ */
function url(string $path = ''): string
{
    $path = trim($path, '/');
    return base_path() . '/' . ($path === '' ? '' : $path . '/');
}

/** Statik fayl ünvanı: asset('assets/css/a.css') => /assets/css/a.css */
function asset(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Tam (mütləq) ünvan — paylaşma düymələri və meta teqlər üçün */
function abs_url(string $path = ''): string
{
    $configured = trim((string) cfg('site_url'));
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path === '' ? '' : trim($path, '/') . '/', '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'itkin.az';
    return $scheme . '://' . $host . url($path);
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Azərbaycan dilində tarix: 21 Sentyabr 2026 */
function az_date(string $iso): string
{
    static $months = [
        1 => 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun',
        'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr',
    ];
    $ts = strtotime($iso);
    if ($ts === false) {
        return '';
    }
    return date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** HTML-dən təmizlənmiş qısa mətn */
function excerpt(string $html, int $words = 22): string
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    $parts = preg_split('/\s+/u', $text, $words + 1);
    if (count($parts) > $words) {
        array_pop($parts);
        return implode(' ', $parts) . '…';
    }
    return $text;
}

/** Şəkil teqi üçün srcset/sizes atributlarını hazırlayır */
function img_attrs(array $img): string
{
    $out = '';
    if (!empty($img['srcset'])) {
        $out .= ' srcset="' . e(srcset_urls($img['srcset'])) . '"';
    }
    if (!empty($img['sizes'])) {
        $out .= ' sizes="' . e($img['sizes']) . '"';
    }
    return $out;
}

/** "uploads/a.jpg 1000w, uploads/b.jpg 300w" -> tam yollarla */
function srcset_urls(string $srcset): string
{
    $out = [];
    foreach (explode(',', $srcset) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $bits = preg_split('/\s+/', $part, 2);
        $out[] = asset($bits[0]) . (isset($bits[1]) ? ' ' . $bits[1] : '');
    }
    return implode(', ', $out);
}

/**
 * Saxlanılmış məzmundakı nisbi fayl yollarını sayt ünvanına uyğunlaşdırır.
 * Məzmun data/ fayllarında "uploads/..." şəklində saxlanılır.
 */
function post_content(string $html): string
{
    // srcset-də bir neçə ünvan olur — hər birini ayrıca düzəldirik
    $html = preg_replace_callback(
        '#\bsrcset="([^"]*)"#',
        static function (array $m) {
            return 'srcset="' . srcset_urls($m[1]) . '"';
        },
        $html
    );

    // qalan tək ünvanlı atributlar
    return preg_replace_callback(
        '#\b(src|href|content|data-src|data-large_image|poster)="(uploads/|assets/vendor/)([^"]*)"#',
        static function (array $m) {
            return $m[1] . '="' . asset($m[2] . $m[3]) . '"';
        },
        $html
    );
}

/**
 * <body> siniflərini WordPress + Elementor ardıcıllığı ilə yığır.
 *
 * @param string $lead      səhifəyə xas siniflər (məsələn "single single-post postid-651")
 * @param bool   $fullWidth Elementor "tam en" şablonu
 * @param string $page      "elementor-page-581" kimi şablon sinfi (varsa)
 * @param bool   $isPage    WordPress səhifəsidirsə "elementor-page" də əlavə olunur
 */
function body_class(string $lead, bool $fullWidth = false, string $page = '', bool $isPage = false): string
{
    $c = array_filter(explode(' ', $lead));
    $c[] = 'wp-custom-logo';
    $c[] = 'wp-theme-hello-elementor';
    $c[] = 'elementor-default';
    if ($fullWidth) {
        $c[] = 'elementor-template-full-width';
    }
    $c[] = 'elementor-kit-7';
    if ($isPage) {
        $c[] = 'elementor-page';
    }
    if ($page !== '') {
        $c[] = $page;
    }
    return implode(' ', $c);
}

/**
 * Elementor-un frontend konfiqurasiyasındakı "post" obyekti.
 * Tək səhifədə başlıq faiz-kodlaşdırılır və featuredImage verilir,
 * arxivdə isə adi mətn olur və featuredImage ümumiyyətlə olmur.
 */
function elementor_post_json(int $id, string $title, string $image = ''): string
{
    if ($id === 0) {
        return json_encode(['id' => 0, 'title' => $title, 'excerpt' => ''], JSON_UNESCAPED_SLASHES);
    }
    return json_encode([
        'id'            => $id,
        'title'         => rawurlencode($title . ' - ' . cfg('site_name')),
        'excerpt'       => '',
        'featuredImage' => $image !== '' ? abs_url_file($image) : false,
    ], JSON_UNESCAPED_UNICODE);
}

/** Xarici ünvan olduğu kimi, daxili yol isə sayt köküylə qaytarılır */
function link_url(string $url): string
{
    return preg_match('#^(?:https?:)?//#', $url) ? $url : asset($url);
}

/** Faylın tam ünvanı (og:image və Elementor konfiqurasiyası üçün) */
function abs_url_file(string $path): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $configured = trim((string) cfg('site_url'));
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'itkin.az';
    return $scheme . '://' . $host . asset($path);
}

/**
 * Statik səhifələrdəki redaktə oluna bilən mətnlər.
 *
 * Şablonlarda mətnin orijinal variantı olduğu kimi qalır, admin panelindən
 * dəyişdiriləni isə data/page-texts.php faylından götürülür. Beləliklə fayl
 * silinsə belə sayt orijinal mətnlə işləməyə davam edir.
 */
function page_text(string $key, string $default = ''): string
{
    $value = page_override($key);
    if (!is_string($value)) {
        return $default;
    }
    // Şəkil və fayl yolları saytın ünvanına uyğunlaşdırılır
    return strpos($value, 'uploads/') === 0 ? asset($value) : $value;
}

/**
 * Admin panelindən dəyişdirilmiş dəyər; açar yoxdursa null (orijinal qalır).
 *
 * Boş sətir və boş siyahı da dəyərdir — “saytda boş qalsın” deməkdir
 * (abzası, şəkli, qalereyanı götürmək üçün). Orijinala qayıtmaq üçün
 * panel açarı tamamilə silir.
 */
function page_override(string $key)
{
    static $texts = null;
    if ($texts === null) {
        $texts = data_load('page-texts');
    }
    return array_key_exists($key, $texts) ? $texts[$key] : null;
}

/** Mətn bloku (HTML). Paneldən gələn mətndə yollar nisbidir — post_content() düzəldir. */
function page_html(string $key, string $default): string
{
    $value = page_override($key);
    return is_string($value) ? post_content($value) : $default;
}

/**
 * Faylın ünvanı: dəyişdirilibsə yenisi, yoxsa orijinal (məsələn fon videosu).
 * Boş dəyər — fayl götürülüb, ünvan da boş qalır.
 */
function page_asset(string $key, string $default): string
{
    $value = page_override($key);
    if ($value === '') {
        return '';
    }
    return asset(is_string($value) && page_path_ok($value) ? $value : $default);
}

/**
 * Paneldən gələn yol uploads/ altında sadə fayl yoludurmu.
 * “.” ilə başlayan hissə (./, ../, .htaccess) və “//” qəbul edilmir —
 * eyni fayla fərqli yazılışla müraciət etmək olmasın.
 */
function page_path_ok(string $path): bool
{
    return (bool) preg_match('#^uploads/[A-Za-z0-9._/-]+$#', $path)
        && strpos($path, '//') === false
        && strpos($path, '/.') === false;
}

/**
 * Şəklin eni və hündürlüyü (fayl yoxdursa 0).
 *
 * Telefon şəkilləri çox vaxt yan saxlanılır, düz vəziyyət isə EXIF-dəki
 * “Orientation” ilə verilir. Brauzer şəkli çevrilmiş göstərir, ona görə
 * 5–8 oriyentasiyada en və hündürlük yerini dəyişir — yoxsa qalereyada
 * portret şəkil üfüqi kafel alır və kəsilir.
 */
function page_image_size(string $path): array
{
    static $cache = [];
    if (!isset($cache[$path])) {
        $file = dirname(__DIR__) . '/' . $path;
        $info = is_file($file) ? @getimagesize($file) : false;
        $size = $info ? [(int) $info[0], (int) $info[1]] : [0, 0];
        if ($info && ($info[2] ?? 0) === IMAGETYPE_JPEG && image_orientation($file) >= 5) {
            $size = [$size[1], $size[0]];
        }
        $cache[$path] = $size;
    }
    return $cache[$path];
}

/** JPEG-in EXIF oriyentasiyası (1–8), tapılmasa 1. exif modulu olmasa da işləyir. */
function image_orientation(string $file): int
{
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($file);
        $o = (int) ($exif['Orientation'] ?? 1);
        return $o >= 1 && $o <= 8 ? $o : 1;
    }

    // exif modulu yoxdursa APP1 seqmentini özümüz oxuyuruq
    $data = (string) @file_get_contents($file, false, null, 0, 131072);
    $pos = 2;
    $len = strlen($data);
    while ($pos + 4 <= $len && $data[$pos] === "\xFF") {
        $marker = ord($data[$pos + 1]);
        $size   = unpack('n', substr($data, $pos + 2, 2))[1];
        if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
            $tiff = $pos + 10;
            $le   = substr($data, $tiff, 2) === 'II';
            $u16  = static function ($at) use ($data, $le) {
                return unpack($le ? 'v' : 'n', substr($data, $at, 2))[1] ?? 0;
            };
            $u32  = static function ($at) use ($data, $le) {
                return unpack($le ? 'V' : 'N', substr($data, $at, 4))[1] ?? 0;
            };
            $ifd = $tiff + $u32($tiff + 4);
            $n   = $u16($ifd);
            for ($i = 0; $i < $n && $ifd + 2 + ($i + 1) * 12 <= $len; $i++) {
                $entry = $ifd + 2 + $i * 12;
                if ($u16($entry) === 0x0112) {
                    $o = $u16($entry + 8);
                    return $o >= 1 && $o <= 8 ? $o : 1;
                }
            }
            return 1;
        }
        if ($marker === 0xDA) {
            break;   // şəkil məlumatı başladı — EXIF yoxdur
        }
        $pos += 2 + $size;
    }
    return 1;
}

/**
 * Şəkil. Dəyişdirilməyibsə null qaytarır və şablon orijinal <img> teqini
 * olduğu kimi çap edir. Dəyişdirilibsə yeni teq qurulur: srcset atılır
 * (köhnə şəklin kiçik nüsxələrinə aiddir), en və hündürlük yeni fayldan.
 * Boş dəyər — şəkil götürülüb, heç nə çap olunmur.
 */
function page_img(string $key, string $class, string $alt): ?string
{
    $path = page_override($key);
    if ($path === '') {
        return '';
    }
    if (!is_string($path) || !page_path_ok($path)) {
        return null;
    }
    [$w, $h] = page_image_size($path);
    return '<img loading="lazy" decoding="async"'
        . ($w ? ' width="' . $w . '" height="' . $h . '"' : '')
        . ' src="' . e(asset($path)) . '"'
        . ($class !== '' ? ' class="' . e($class) . '"' : '')
        . ' alt="' . e($alt) . '" />';
}

/**
 * Qalereya. Dəyişdirilməyibsə null — şablon orijinal elementləri çap edir.
 * Boş siyahı — qalereya boşaldılıb. Elementor-un qalereya skripti hər
 * element üçün data-thumbnail, data-width və data-height gözləyir; ölçüsü
 * oxunmayan fayl buraxılır.
 */
function page_gallery(string $key, string $slideshow): ?string
{
    $paths = page_override($key);
    if ($paths === []) {
        return '';
    }
    if (!is_array($paths)) {
        return null;
    }
    $out = '';
    foreach ($paths as $path) {
        if (!is_string($path) || !page_path_ok($path)) {
            continue;
        }
        [$w, $h] = page_image_size($path);
        if (!$w || !$h) {
            continue;
        }
        $settings = json_encode(['url' => abs_url_file($path), 'slideshow' => $slideshow]);
        $hash = '#elementor-action%3Aaction%3Dlightbox%26settings%3D' . rawurlencode(base64_encode((string) $settings));
        $out .= '<a class="e-gallery-item elementor-gallery-item elementor-animated-content" href="' . e(asset($path)) . '"'
              . ' data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="' . e($slideshow) . '"'
              . ' data-e-action-hash="' . e($hash) . '">' . "\n\t\t\t\t\t"
              . '<div class="e-gallery-image elementor-gallery-item__image" data-thumbnail="' . e(asset($path)) . '"'
              . ' data-width="' . $w . '" data-height="' . $h . '" aria-label="" role="img" ></div>'
              . "\n\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t\t";
    }
    // Siyahıdakı fayllar yoxa çıxıbsa (məsələn FTP ilə silinib), orijinal qalsın
    return $out === '' ? null : $out;
}

/**
 * Konteyner fon şəkilləri Elementor-un CSS faylındadır. Dəyişdirilənləri
 * <head>-də kiçik bir <style> ilə üstələyirik; dəyişiklik yoxdursa heç nə
 * çap olunmur. Boş dəyər — fon şəkli götürülüb.
 */
function page_bg_styles(string $page): string
{
    if ($page === '') {
        return '';
    }
    $css = '';
    foreach (data_load('page-fields') as $field) {
        if (($field['page'] ?? '') !== $page || ($field['type'] ?? '') !== 'bg') {
            continue;
        }
        $path = page_override($field['key']);
        if ($path === '') {
            $css .= $field['selector'] . '{background-image:none !important}';
        } elseif (is_string($path) && page_path_ok($path)) {
            $css .= $field['selector'] . '{background-image:url("' . asset($path) . '") !important}';
        }
    }
    return $css === '' ? '' : "\t<style id=\"itkin-page-bg\">" . $css . "</style>\n";
}

/** Yazının kateqoriya CSS sinifləri: category-xeberler category-tedbirler */
function cat_classes(array $post): string
{
    $out = [];
    foreach ($post['categories'] ?? [] as $id) {
        $cat = category_by_id((int) $id);
        if ($cat) {
            $out[] = 'category-' . $cat['slug'];
        }
    }
    return implode(' ', $out);
}

/** MediaElement pleyerinin inline konfiqurasiyası (data/mejs.php) */
function mejs_inline(string $key): string
{
    $cfg = data_load('mejs');
    $js  = (string) ($cfg[$key] ?? '');
    return str_replace('{{MEJS}}', asset('assets/vendor/wp-includes/js/mediaelement/'), $js);
}

/**
 * storage/ altında qovluq: ehtiyat nüsxələr, zibil qutusu, əlaqə jurnalı.
 *
 * Bu qovluq brauzerdən açılmamalıdır — jurnalda müraciət edənlərin adı,
 * telefonu və e-poçtu var. Qoruyucu .htaccess repozitoriyadadır, amma sayt
 * başqa yolla yerləşdirilibsə və fayl yoxdursa, burada yenidən yaradılır.
 */
function storage_dir(string $sub = ''): string
{
    $root = dirname(__DIR__) . '/storage';
    if (!is_dir($root)) {
        @mkdir($root, 0775, true);
    }
    if (is_dir($root) && !is_file($root . '/.htaccess')) {
        @file_put_contents($root . '/.htaccess',
            "# Bu qovluğa birbaşa müraciət bağlıdır\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    $dir = $sub === '' ? $root : $root . '/' . trim($sub, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}
