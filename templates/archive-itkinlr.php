<?php
/**
 * İtkinlər arxivi / missing persons archive.
 * Hello Elementor mövzusunun standart arxiv şablonu (Elementor şablonu yoxdur).
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => 'itkinlr', 'slug' => 'itkinlr', 'categories' => []]);

$pager = paginate(all_missing(), (int) cfg('per_page')['itkinlr'], $page ?? 1);
$items = $pager['items'];

$meta['title']      = 'İtkinlər Archive - ' . cfg('site_name');
$meta['og_type']    = 'website';
$meta['canonical']  = abs_url($pager['page'] > 1 ? 'itkinlr/page/' . $pager['page'] : 'itkinlr');
$meta['body_class'] = body_class('archive post-type-archive post-type-archive-itkinlr');
$meta['elementor_post'] = elementor_post_json(0, 'İtkinlər');

$crumbs = [['label' => 'İtkinlər', 'href' => null]];
?>
<main id="content" class="site-main">

			<header class="page-header">
			<h1 class="entry-title">Archives: <span>İtkinlər</span></h1>		</header>

	<div class="page-content">
<?php foreach ($items as $item): $t = $item['thumb'] ?? null; ?>
					<article class="post">
				<h2 class="entry-title"><a href="<?= url('itkinlr/' . $item['slug']) ?>"><?= e($item['title']) ?></a></h2><?php if ($t): ?><a href="<?= url('itkinlr/' . $item['slug']) ?>"><img width="<?= e($t['width']) ?>" height="<?= e($t['height']) ?>" src="<?= asset($t['url']) ?>" class="<?= e($t['class']) ?>" alt="<?= e($t['alt']) ?>" decoding="async"<?php if ($t['srcset']): ?> srcset="<?= e(srcset_urls($t['srcset'])) ?>"<?php endif; ?><?php if ($t['sizes']): ?> sizes="<?= e($t['sizes']) ?>"<?php endif; ?> /></a><?php endif; ?>			</article>
<?php endforeach; ?>
			</div>


<?php if ($pager['pages'] > 1): ?>
			<nav class="pagination">
						<div class="nav-previous"><?php if ($pager['page'] < $pager['pages']): ?><a href="<?= url('itkinlr/page/' . ($pager['page'] + 1)) ?>" ><span class="meta-nav">&larr;</span> older</a><?php endif; ?></div>
						<div class="nav-next"><?php if ($pager['page'] > 1): ?><a href="<?= $pager['page'] - 1 > 1 ? url('itkinlr/page/' . ($pager['page'] - 1)) : url('itkinlr') ?>" >newer <span class="meta-nav">&rarr;</span></a><?php endif; ?></div>
		</nav>
<?php endif; ?>

</main>
