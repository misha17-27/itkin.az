<?php
/**
 * Xəbərlər / news listing page
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'page', 'slug' => 'xeberler', 'categories' => []]);

$info   = page_setup('xeberler', $meta);
$crumbs = [['label' => $info['title'], 'href' => null]];

$pager      = paginate(all_posts(), (int) cfg('per_page')['xeberler'], $page);
$items      = $pager['items'];
$pager_base = 'xeberler';
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-xeberler.body.php'; ?>
<?php main_close($info); ?>
