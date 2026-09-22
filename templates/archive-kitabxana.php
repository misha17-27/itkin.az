<?php
/**
 * Kitabxana arxivi / library archive (kitabxana-blog).
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => 'kitabxana-blog', 'slug' => 'kitabxana-blog', 'categories' => []]);

$crumbs = [['label' => 'Kitabxana', 'href' => null]];
$items  = all_books();

$meta['title']      = 'Kitabxana Archive - ' . cfg('site_name');
$meta['og_type']    = 'website';
$meta['canonical']  = abs_url('kitabxana-blog');
$meta['body_class'] = body_class('archive post-type-archive post-type-archive-kitabxana-blog', true, 'elementor-page-968');
$meta['head_meta']  = data_load('archives')['kitabxana-blog']['head_meta'] ?? [];
$meta['description'] = (string) (data_load('archives')['kitabxana-blog']['description'] ?? '');
$meta['schema']     = data_load('archives')['kitabxana-blog']['schema'] ?? '';
$meta['elementor_post'] = elementor_post_json(0, $meta['title']);
?>
<?php include __DIR__ . '/archive-kitabxana.body.php'; ?>
