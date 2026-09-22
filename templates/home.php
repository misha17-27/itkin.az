<?php
/**
 * Ana səhifə / home page.
 */

require_once dirname(__DIR__) . '/inc/nav.php';
require_once dirname(__DIR__) . '/inc/page.php';

nav_context(['type' => 'home', 'slug' => '', 'categories' => []]);

$info = page_setup('ana-sehife', $meta);

// İtkin şəxslər karuseli
$missing = array_slice(all_missing(), 0, 6);

// "Tədbirlər" kateqoriyasından son yazılar
$cat    = find_category('tedbirler');
$events = $cat ? array_slice(posts_in_category((int) $cat['id']), 0, 6) : array_slice(all_posts(), 0, 6);
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/home.body.php'; ?>
<?php main_close($info); ?>
