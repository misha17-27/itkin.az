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

$meta['title']       = $category['name'] . ' Archives - ' . cfg('site_name');
$meta['og_title']    = $category['name'] . ' Archives';
$meta['description'] = $category['description'];
$meta['canonical']   = abs_url($pager_base);
$meta['body_class']  = body_class('archive category category-' . $category['slug'] . ' category-' . $category['id'], true, 'elementor-page-588');
$meta['elementor_post'] = elementor_post_json(0, $category['name']);
?>
<?php include __DIR__ . '/archive-category.body.php'; ?>
