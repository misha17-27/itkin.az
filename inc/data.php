<?php
/**
 * Məzmun anbarı / content store.
 * Bütün məzmun data/ qovluğundakı PHP massivlərində saxlanılır.
 */

function data_load(string $name): array
{
    static $cache = [];
    if (isset($cache[$name])) {
        return $cache[$name];
    }
    $file = dirname(__DIR__) . '/data/' . $name . '.php';
    return $cache[$name] = is_file($file) ? (array) require $file : [];
}

/** Bütün yazılar — tarixə görə yenidən köhnəyə */
function all_posts(): array
{
    static $sorted = null;
    if ($sorted !== null) {
        return $sorted;
    }
    $posts = data_load('posts');
    usort($posts, static function (array $a, array $b) {
        return strcmp($b['date'], $a['date']);
    });
    return $sorted = $posts;
}

function all_books(): array
{
    return data_load('kitabxana');
}

function all_missing(): array
{
    return data_load('itkinlr');
}

function all_categories(): array
{
    return data_load('categories');
}

function all_pages(): array
{
    return data_load('pages');
}

function find_by_slug(array $items, string $slug): ?array
{
    foreach ($items as $item) {
        if ($item['slug'] === $slug) {
            return $item;
        }
    }
    return null;
}

function find_category(string $slug): ?array
{
    return find_by_slug(all_categories(), $slug);
}

function category_by_id(int $id): ?array
{
    foreach (all_categories() as $cat) {
        if ((int) $cat['id'] === $id) {
            return $cat;
        }
    }
    return null;
}

/** Yazının ilk kateqoriyası */
function post_category(array $post): ?array
{
    foreach ($post['categories'] ?? [] as $id) {
        $cat = category_by_id((int) $id);
        if ($cat) {
            return $cat;
        }
    }
    return null;
}

function posts_in_category(int $id): array
{
    return array_values(array_filter(all_posts(), static function (array $p) use ($id) {
        return in_array($id, $p['categories'] ?? [], true);
    }));
}

/**
 * Karusel üçün əlaqəli yazılar: cari yazı ilə ən azı bir ortaq kateqoriyası olan
 * ən yeni yazılar (saytdakı Elementor Loop Carousel ilə eyni qayda).
 */
function related_posts(array $post, int $limit = 6): array
{
    $own = array_map('intval', $post['categories'] ?? []);

    $pool = array_values(array_filter(all_posts(), static function (array $p) use ($post, $own) {
        if ($p['id'] === $post['id']) {
            return false;
        }
        return (bool) array_intersect($own, array_map('intval', $p['categories'] ?? []));
    }));

    // Kateqoriyası olmayan yazılar üçün sadəcə ən yeniləri göstəririk
    if (!$pool) {
        $pool = array_values(array_filter(all_posts(), static function (array $p) use ($post) {
            return $p['id'] !== $post['id'];
        }));
    }

    return array_slice($pool, 0, $limit);
}

/** Əvvəlki / sonrakı yazı */
function post_siblings(array $post): array
{
    $posts = all_posts();
    $idx = null;
    foreach ($posts as $i => $p) {
        if ($p['id'] === $post['id']) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return [null, null];
    }
    // Siyahı yenidən köhnəyə düzülüb: əvvəlki = daha yeni
    $prev = $posts[$idx - 1] ?? null;
    $next = $posts[$idx + 1] ?? null;
    return [$prev, $next];
}

/**
 * Səhifələmə köməkçisi.
 * Diapazondan kənar səhifə nömrəsi boş siyahı qaytarır — orijinal saytda da
 * Elementor siyahısı belə davranır (200 cavab, boş şəbəkə).
 */
function paginate(array $items, int $perPage, int $page): array
{
    $total = count($items);
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = max(1, $page);
    return [
        'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
    ];
}
