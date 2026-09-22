<?php
/**
 * Milli qanunvericilik / national legislation page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'page', 'slug' => 'milli-qanunvericilik', 'categories' => []]);

$info   = page_setup('milli-qanunvericilik', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-milli-qanunvericilik.body.php'; ?>
<?php main_close($info); ?>
