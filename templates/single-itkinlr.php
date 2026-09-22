<?php
/**
 * İtkin şəxsin səhifəsi / single missing person.
 * Hello Elementor mövzusunun standart şablonu — Elementor şablonu yoxdur.
 * $item — data/itkinlr.php-dən bir sətir.
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => 'itkinlr', 'slug' => 'itkinlr/' . $item['slug'], 'categories' => []]);

$meta['title']       = $item['title'] . ' - ' . cfg('site_name');
$meta['og_title']    = $item['title'];
$meta['description'] = $item['description'];
$meta['image']       = !empty($item['thumb']['url']) ? abs_url_file($item['thumb']['url']) : '';
$meta['canonical']   = abs_url('itkinlr/' . $item['slug']);
$meta['og_type']     = 'article';
$meta['body_class']  = body_class('wp-singular itkinlr-template-default single single-itkinlr postid-' . $item['id']);
$meta['schema_entry'] = $item + ['categories' => []];
$meta['elementor_post'] = elementor_post_json($item['id'], $item['title'], $item['thumb']['url'] ?? '');
?>
<main id="content" class="site-main post-<?= $item['id'] ?> itkinlr type-itkinlr status-publish<?= !empty($item['thumb']['url']) ? ' has-post-thumbnail' : '' ?> hentry">

			<header class="page-header">
			<h1 class="entry-title"><?= e($item['title']) ?></h1>		</header>

	<div class="page-content">
		<?= post_content($item['content']) ?>		<div class="post-tags">
					</div>
			</div>


</main>
