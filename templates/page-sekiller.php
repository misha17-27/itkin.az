<?php
/**
 * Şəkillər / photo gallery page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'page', 'slug' => 'sekiller', 'categories' => []]);

$info   = page_setup('sekiller', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-sekiller.body.php'; ?>
<?php main_close($info); ?>
