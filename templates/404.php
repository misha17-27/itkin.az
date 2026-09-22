<?php
/**
 * 404 — səhifə tapılmadı.
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => '404', 'slug' => '', 'categories' => []]);

$meta['title']      = 'Səhifə tapılmadı - ' . cfg('site_name');
$meta['body_class'] = body_class('error404');
$meta['elementor_post'] = elementor_post_json(0, 'Səhifə tapılmadı');
?>
<main id="content" class="site-main">

			<header class="page-header">
			<h1 class="entry-title">Səhifə tapılmadı</h1>		</header>

	<div class="page-content">
		<p>Axtardığınız səhifə mövcud deyil və ya ünvanı dəyişib.</p>
		<p>
			<a href="<?= url() ?>">Ana səhifəyə qayıt</a> &nbsp;·&nbsp;
			<a href="<?= url('xeberler') ?>">Xəbərlər</a> &nbsp;·&nbsp;
			<a href="<?= url('elaqe') ?>">Əlaqə</a>
		</p>
	</div>


</main>
