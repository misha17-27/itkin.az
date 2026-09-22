<?php
/**
 * itkin.az — ön nəzarətçi / front controller
 */

require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/data.php';
require __DIR__ . '/inc/schema.php';
require __DIR__ . '/inc/render.php';

// ---------------------------------------------------------------- marşrutlaşdırma
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}
$path  = trim(rawurldecode($uri), '/');
$parts = $path === '' ? [] : explode('/', $path);

// Səhifə nömrəsi: ?sehife=2 və ya Elementor-un orijinal ?e-page-XXXX=2 parametri
$page = (int) ($_GET['sehife'] ?? 0);
if ($page < 1) {
    foreach ($_GET as $key => $value) {
        if (strpos($key, 'e-page-') === 0) {
            $page = (int) $value;
            break;
        }
    }
}
$page = max(1, $page);

// Statik səhifələr üçün şablon uyğunluğu
$PAGE_TEMPLATES = [
    'xeberler'             => 'page-xeberler',
    'haqqimizda'           => 'page-haqqimizda',
    'elaqe'                => 'page-elaqe',
    'sekiller'             => 'page-sekiller',
    'beynelxalq-senedler'  => 'page-beynelxalq-senedler',
    'milli-qanunvericilik' => 'page-milli-qanunvericilik',
    'kitabxana'            => 'page-kitabxana',
];

$view = null;
$vars = [];

if ($parts === []) {
    $view = 'home';

} elseif (count($parts) === 1 && isset($PAGE_TEMPLATES[$parts[0]])) {
    $view = $PAGE_TEMPLATES[$parts[0]];
    $vars = ['slug' => $parts[0], 'page' => $page];

} elseif ($parts[0] === 'itkinlr') {
    if (count($parts) === 1) {
        $view = 'archive-itkinlr';
        $vars = ['page' => 1];
    } elseif (count($parts) === 3 && $parts[1] === 'page' && ctype_digit($parts[2])) {
        // Mövzunun standart arxiv səhifələməsi: /itkinlr/page/2/
        // Mövcud olmayan səhifə orijinalda olduğu kimi 404 qaytarır
        $wanted = max(1, (int) $parts[2]);
        $lastPage = max(1, (int) ceil(count(all_missing()) / max(1, (int) cfg('per_page')['itkinlr'])));
        if ($wanted <= $lastPage) {
            $view = 'archive-itkinlr';
            $vars = ['page' => $wanted];
        }
    } elseif (count($parts) === 2 && ($item = find_by_slug(all_missing(), $parts[1]))) {
        $view = 'single-itkinlr';
        $vars = ['item' => $item];
    }

} elseif ($parts[0] === 'kitabxana-blog') {
    if (count($parts) === 1) {
        $view = 'archive-kitabxana';
    } elseif (count($parts) === 2 && ($item = find_by_slug(all_books(), $parts[1]))) {
        $view = 'single-kitabxana';
        $vars = ['item' => $item];
    }

} elseif ($parts[0] === 'category' && count($parts) >= 2) {
    $cat = find_category($parts[1]);
    if ($cat && count($parts) === 2) {
        $view = 'archive-category';
        $vars = ['category' => $cat, 'page' => $page];
    } elseif ($cat && count($parts) === 4 && $parts[2] === 'page' && ctype_digit($parts[3])) {
        // WordPress-in standart səhifələmə ünvanı: /category/<slug>/page/2/
        $wanted   = max(1, (int) $parts[3]);
        $lastPage = max(1, (int) ceil(count(posts_in_category((int) $cat['id'])) / max(1, (int) cfg('per_page')['category'])));
        if ($wanted <= $lastPage) {
            $view = 'archive-category';
            $vars = ['category' => $cat, 'page' => $wanted, 'paged_path' => true];
        }
    }

} elseif (count($parts) === 1) {
    if ($post = find_by_slug(all_posts(), $parts[0])) {
        $view = 'single-post';
        $vars = ['post' => $post];
    }
}

if ($view === null) {
    http_response_code(404);
    $view = '404';
}

render($view, $vars);
