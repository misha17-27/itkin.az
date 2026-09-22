<?php
/**
 * Kateqoriya arxivi / category archive.
 * $category — data/categories.php-dən bir sətir, $page — cari səhifə.
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => 'category', 'slug' => $category['slug'], 'categories' => []]);

$crumbs = [['label' => $category['name'], 'href' => null]];

$pager      = paginate(posts_in_category((int) $category['id']), (int) cfg('per_page')['category'], $page);
$items      = $pager['items'];
$pager_base = 'category/' . $category['slug'];

// Səhifələnmiş arxivin öz ünvanı və başlığı olur
$paged = $pager['page'] > 1;
$path  = $paged ? $pager_base . '/page/' . $pager['page'] : $pager_base;
$title = $category['doc_title'] ?: ($category['name'] . ' Archives - ' . cfg('site_name'));
if ($paged) {
    $title = str_replace(
        ' - ' . cfg('site_name'),
        ' - Page ' . $pager['page'] . ' of ' . $pager['pages'] . ' - ' . cfg('site_name'),
        $title
    );
}

$meta['title']       = $title;
$meta['og_title']    = preg_replace('/ - ' . preg_quote((string) cfg('site_name'), '/') . '$/u', '', $title);
$meta['description'] = $category['description'];
$meta['canonical']   = abs_url($path);
$meta['body_class']  = body_class(
    'archive category category-' . $category['slug'] . ' category-' . $category['id']
    . ($paged ? ' paged paged-' . $pager['page'] : ''),
    true,
    'elementor-page-588'
);
$meta['head_meta']   = $category['head_meta'] ?? [];
$meta['schema']      = $category['schema'] ?? '';
$meta['elementor_post'] = elementor_post_json(0, $category['name']);

if ($paged) {
    foreach ($meta['head_meta'] as $i => $tag) {
        if ($tag[1] === 'og:title') {
            $meta['head_meta'][$i][2] = $meta['og_title'];
        }
    }
}

// WordPress qonşu arxiv səhifələrinə rel="prev" / rel="next" verir
$meta['rel_prev'] = $pager['page'] > 1
    ? abs_url($pager['page'] - 1 > 1 ? $pager_base . '/page/' . ($pager['page'] - 1) : $pager_base)
    : '';
$meta['rel_next'] = $pager['page'] < $pager['pages']
    ? abs_url($pager_base . '/page/' . ($pager['page'] + 1))
    : '';
?>
<?php include __DIR__ . '/archive-category.body.php'; ?>
