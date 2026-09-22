<?php
/**
 * Köməkçi funksiyalar / helper functions
 */

function cfg(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config.php';
    }
    return $cfg[$key] ?? $default;
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
    return $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
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
