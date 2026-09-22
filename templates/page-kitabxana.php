<?php
/**
 * Kitabxana / library page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'page', 'slug' => 'kitabxana', 'categories' => []]);

$info   = page_setup('kitabxana', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];

$items = all_books();
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-kitabxana.body.php'; ?>
<?php main_close($info); ?>
