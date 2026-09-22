<?php
/**
 * Beynəlxalq sənədlər / international documents page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'page', 'slug' => 'beynelxalq-senedler', 'categories' => []]);

$info   = page_setup('beynelxalq-senedler', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-beynelxalq-senedler.body.php'; ?>
<?php main_close($info); ?>
