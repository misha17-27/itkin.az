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

// Səhifələnmiş siyahının öz ünvanı (/xeberler/page/2/), başlığı və qonşu səhifələri —
// kateqoriya arxivindəki kimi; axtarış sistemi hər səhifəni ayrıca görür
if ($pager['page'] > 1) {
    $meta['canonical'] = abs_url($pager_base . '/page/' . $pager['page']);
    $meta['title']     = preg_replace(
        '/ - ' . preg_quote((string) cfg('site_name'), '/') . '$/u',
        ' - Page ' . $pager['page'] . ' of ' . $pager['pages'] . ' - ' . cfg('site_name'),
        $meta['title']
    );
    $meta['schema'] = schema_paged((string) ($meta['schema'] ?? ''), $pager_base,
        $pager_base . '/page/' . $pager['page'], $meta['title']);
}
$meta['rel_prev'] = $pager['page'] > 1
    ? abs_url($pager['page'] - 1 > 1 ? $pager_base . '/page/' . ($pager['page'] - 1) : $pager_base)
    : '';
$meta['rel_next'] = $pager['page'] < $pager['pages']
    ? abs_url($pager_base . '/page/' . ($pager['page'] + 1))
    : '';
?>
<?php main_open($info); ?>
<?php include __DIR__ . '/page-xeberler.body.php'; ?>
<?php main_close($info); ?>
