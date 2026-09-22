<?php
/**
 * İtkinlər arxivi / missing persons archive.
 * Hello Elementor mövzusunun standart arxiv şablonu (Elementor şablonu yoxdur).
 */

require_once dirname(__DIR__) . '/inc/nav.php';

nav_context(['type' => 'itkinlr', 'slug' => 'itkinlr', 'categories' => []]);

$pager = paginate(all_missing(), (int) cfg('per_page')['itkinlr'], $page ?? 1);
$items = $pager['items'];

$base = data_load('archives')['itkinlr']['title'] ?? ('İtkinlər Archive - ' . cfg('site_name'));
// WordPress səhifələnmiş arxivə " - Page N of M" əlavə edir
if ($pager['page'] > 1) {
    $base = str_replace(' - ' . cfg('site_name'),
        ' - Page ' . $pager['page'] . ' of ' . $pager['pages'] . ' - ' . cfg('site_name'), $base);
}
$meta['title']      = $base;
$meta['og_title']   = $base;
$meta['og_type']    = 'website';
$meta['canonical']  = abs_url($pager['page'] > 1 ? 'itkinlr/page/' . $pager['page'] : 'itkinlr');
$meta['body_class'] = body_class('archive post-type-archive post-type-archive-itkinlr');
$meta['head_meta']  = data_load('archives')['itkinlr']['head_meta'] ?? [];
// səhifələnmiş arxivdə og:title da başlıqla birlikdə dəyişir
if ($pager['page'] > 1) {
    foreach ($meta['head_meta'] as $i => $tag) {
        if ($tag[1] === 'og:title') {
            $meta['head_meta'][$i][2] = $base;
        }
    }
}
$meta['schema']     = data_load('archives')['itkinlr']['schema'] ?? '';
$meta['rel_prev']   = $pager['page'] > 1
    ? abs_url($pager['page'] - 1 > 1 ? 'itkinlr/page/' . ($pager['page'] - 1) : 'itkinlr')
    : '';
$meta['rel_next']   = $pager['page'] < $pager['pages'] ? abs_url('itkinlr/page/' . ($pager['page'] + 1)) : '';
$meta['elementor_post'] = elementor_post_json(0, $meta['title']);

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
