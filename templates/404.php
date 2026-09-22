<?php
/**
 * 404 — səhifə tapılmadı.
 * Mövzunun standart 404 şablonu; meta məlumat orijinaldan götürülüb
 * (noindex, canonical yoxdur, qrafda yalnız WebSite + Organization).
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => '404', 'slug' => '', 'categories' => []]);

$info = data_load('archives')['404'] ?? [];

$meta['title']      = $info['title'] ?? ('Səhifə tapılmadı - ' . cfg('site_name'));
$meta['robots']     = $info['robots'] ?? 'noindex, follow';
$meta['canonical']  = '';                       // orijinalda 404-də canonical verilmir
$meta['head_meta']  = $info['head_meta'] ?? [];
$meta['schema']     = $info['schema'] ?? '';
$meta['body_class'] = body_class('error404');
$meta['elementor_post'] = elementor_post_json(0, $meta['title']);
?>
<main id="content" class="site-main">

			<header class="page-header">
			<h1 class="entry-title">The page can&rsquo;t be found.</h1>
		</header>
	
	<div class="page-content">
		<p>It looks like nothing was found at this location.</p>
	</div>

</main>
