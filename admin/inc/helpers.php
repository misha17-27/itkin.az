<?php
/**
 * Admin paneli üçün köməkçilər.
 */

/** Panel daxilində ünvan: admin_url(['section' => 'posts']) */
function admin_url(array $params = []): string
{
    $base = base_path() . '/admin/';
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
    return is_string($value) ? trim($value) : $default;
}

/** POST-dan HTML (məzmun sahələri) — yalnız təhlükəsiz teqlər saxlanılır */
function post_html(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? admin_clean_html(trim($value)) : '';
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

/**
 * Məzmun HTML-ini təmizləyir.
 * Skriptlər, çərçivələr və hadisə atributları (onclick və s.) atılır —
 * redaktor səhvən yapışdırsa da sayta düşməsin.
 */
function admin_clean_html(string $html): string
{
    if ($html === '') {
        return '';
    }

    // <script>, <style>, <iframe>, <object>, <embed> tam silinir
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|form)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*/?\s*(script|style|iframe|object|embed|form)\b[^>]*>#i', '', $html);

    // on*="..." hadisə atributları
    $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);

    // javascript: ünvanları
    $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*#i', '$1=$2#', $html);

    return (string) $html;
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
