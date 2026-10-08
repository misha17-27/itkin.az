<?php
/**
 * itkin.az — ön nəzarətçi / front controller
 */

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/data.php';
require_once __DIR__ . '/inc/schema.php';
require_once __DIR__ . '/inc/render.php';

site_timezone();           // tarix və saatlar Bakı vaxtı ilə (config.php: timezone)

// İdarə paneli: /<admin_path>/ (config.php) — admin/index.php işləyir
if (admin_request()) {
    require __DIR__ . '/admin/index.php';
    exit;
}

production_errors();       // hostinqdə xəta mətni (yollarla) ekrana çıxmasın
force_https();             // yalnız ayarlarda https:// ünvan yazılıbsa
send_security_headers();

// ---------------------------------------------------------------- marşrutlaşdırma
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}
$path  = trim(rawurldecode($uri), '/');
$parts = $path === '' ? [] : explode('/', $path);

// Dil: /en/... ingiliscə versiyadır; prefiks çıxılır, qalan marşrut eynidir
$raw = ltrim($uri, '/');
if ($parts !== [] && $parts[0] !== LANG_DEFAULT && isset(LANGS[$parts[0]])) {
    lang(array_shift($parts));
    $raw = (string) substr($raw, strcspn($raw, '/'));   // kodlaşdırılmış olsa da (/%65n/)
}
route_path($raw);

// Bu səhifənin ingiliscə versiyası yoxdursa (tərcümə olunmayıb) — azərbaycancaya
if (lang() !== LANG_DEFAULT && !lang_has($raw, lang())) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . url_lang('', LANG_DEFAULT) . route_path() . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
}

// Səhifə nömrəsi: köhnə ?sehife=2 və ya Elementor-un ?e-page-XXXX=2 parametri
$page      = (int) ($_GET['sehife'] ?? 0);
$pageParam = isset($_GET['sehife']);
if ($page < 1) {
    foreach ($_GET as $key => $value) {
        if (strpos((string) $key, 'e-page-') === 0) {
            $page      = is_string($value) ? (int) $value : 0;
            $pageParam = true;
            break;
        }
    }
}
$page = min(max(1, $page), 100000);   // nəhəng rəqəm tam ədəd daşmasına səbəb olmasın

/*
 * Səhifələnən siyahılar (Xəbərlər və kateqoriyalar) gözəl ünvanla açılır:
 * /xeberler/page/2/, /category/tedbirler/page/2/ — WordPress-in standart forması.
 * Köhnə ?e-page-…=2 / ?sehife=2 ünvanları və /page/1/ oraya daimi yönləndirilir
 * (paylaşılmış keçidlər qırılmasın, axtarış sistemləri dublikat görməsin).
 */
$listBase = null;
if ($parts === ['xeberler']
    || (count($parts) === 3 && $parts[0] === 'xeberler' && $parts[1] === 'page')) {
    $listBase = 'xeberler';
} elseif (($parts[0] ?? '') === 'category' && isset($parts[1])
    && (count($parts) === 2 || (count($parts) === 4 && $parts[2] === 'page'))
    && ($listCat = find_category($parts[1])) !== null) {
    // yalnız mövcud kateqoriya — ünvan sorğudan yox, kateqoriyanın öz slug-ından qurulur
    $listBase = 'category/' . $listCat['slug'];
}
if ($listBase !== null) {
    $onPage = count($parts) > 2 && $parts[count($parts) - 2] === 'page' ? $parts[count($parts) - 1] : null;
    $target = null;
    if ($onPage === null && $pageParam) {
        $target = $page;                            // köhnə parametrli ünvan
    } elseif ($onPage === '1') {
        $target = 1;                                // /page/1/ — siyahının özü
    }
    if ($target !== null) {
        header('Location: ' . abs_url($target > 1 ? $listBase . '/page/' . $target : $listBase), true, 301);
        exit;
    }
}

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
    $vars = ['slug' => $parts[0], 'page' => 1];

} elseif (count($parts) === 3 && $parts[0] === 'xeberler' && $parts[1] === 'page' && ctype_digit($parts[2])) {
    // Xəbərlərin səhifələri: /xeberler/page/2/; mövcud olmayan səhifə — 404
    $wanted   = (int) $parts[2];
    $lastPage = max(1, (int) ceil(count(all_posts()) / max(1, (int) cfg('per_page')['xeberler'])));
    if ($wanted >= 2 && $wanted <= $lastPage) {
        $view = 'page-xeberler';
        $vars = ['slug' => 'xeberler', 'page' => $wanted];
    }

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
        $vars = ['category' => $cat, 'page' => 1];
    } elseif ($cat && count($parts) === 4 && $parts[2] === 'page' && ctype_digit($parts[3])) {
        // WordPress-in standart səhifələmə ünvanı: /category/<slug>/page/2/
        $wanted   = (int) $parts[3];
        $lastPage = max(1, (int) ceil(count(posts_in_category((int) $cat['id'])) / max(1, (int) cfg('per_page')['category'])));
        if ($wanted >= 2 && $wanted <= $lastPage) {
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
