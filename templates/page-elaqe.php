<?php
/**
 * Əlaqə / contact page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';
require_once dirname(__DIR__) . '/inc/contact.php';
$form = contact_handle();

nav_context(['type' => 'page', 'slug' => 'elaqe', 'categories' => []]);

$info   = page_setup('elaqe', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-elaqe.body.php'; ?>
<?php main_close($info); ?>
