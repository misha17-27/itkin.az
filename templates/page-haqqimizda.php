<?php
/**
 * Haqqımızda / about page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'page', 'slug' => 'haqqimizda', 'categories' => []]);

$info   = page_setup('haqqimizda', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-haqqimizda.body.php'; ?>
<?php main_close($info); ?>
