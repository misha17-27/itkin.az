<?php
/**
 * Sənədin başlığı / document head.
 * $meta, $styles və $content dəyişənləri render() tərəfindən verilir.
 *
 * Open Graph / Twitter teqləri data/ fayllarında orijinaldakı ardıcıllıqla
 * saxlanılır ($meta['head_meta']) və burada olduğu kimi çap olunur.
 */
?>
<!doctype html>
<html lang="<?= e(cfg('locale', 'az')) ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<meta name="robots" content="<?= e($meta['robots']) ?>">
<?php if ($meta['canonical'] !== '' && $hreflang): ?>
	<link rel="alternate" hreflang="<?= e(cfg('locale', 'az')) ?>" href="<?= e($meta['canonical']) ?>">
	<link rel="alternate" hreflang="x-default" href="<?= e($meta['canonical']) ?>">
<?php endif; ?>
	<title><?= e($meta['title']) ?></title>
<?php if ($meta['canonical'] !== ''): ?>
	<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php endif; ?>
<?php if (!empty($meta['head_meta'])): ?>
<?php   foreach ($meta['head_meta'] as $tag): ?>
<?php       list($kind, $key, $val) = $tag;
            if ($key === 'og:url') {
                $val = $meta['canonical'];
            } elseif ($key === 'og:image' && $val !== '') {
                $val = abs_url_file($val);
            }
            if ($val === '') { continue; } ?>
	<meta <?= $kind === 'p' ? 'property' : 'name' ?>="<?= e($key) ?>" content="<?= e($val) ?>">
<?php   endforeach; ?>
<?php else: ?>
<?php   if ($meta['description'] !== ''): ?>
	<meta name="description" content="<?= e($meta['description']) ?>">
<?php   endif; ?>
	<meta property="og:locale" content="az_AZ">
	<meta property="og:type" content="<?= e($meta['og_type']) ?>">
	<meta property="og:title" content="<?= e($meta['og_title'] !== '' ? $meta['og_title'] : $meta['title']) ?>">
<?php   if ($meta['description'] !== ''): ?>
	<meta property="og:description" content="<?= e($meta['description']) ?>">
<?php   endif; ?>
	<meta property="og:url" content="<?= e($meta['canonical']) ?>">
	<meta property="og:site_name" content="<?= e(cfg('site_name')) ?>">
<?php   if ($meta['image'] !== ''): ?>
	<meta property="og:image" content="<?= e($meta['image']) ?>">
	<meta name="twitter:card" content="summary_large_image">
<?php   endif; ?>
<?php endif; ?>
	<script type="application/ld+json" class="yoast-schema-graph"><?= schema_graph($meta, $crumbs ?? []) ?></script>

	<link rel="icon" href="<?= asset('uploads/2023/11/fav-150x150.png') ?>" sizes="32x32">
	<link rel="icon" href="<?= asset('uploads/2023/11/fav.png') ?>" sizes="192x192">
	<link rel="apple-touch-icon" href="<?= asset('uploads/2023/11/fav.png') ?>">
	<meta name="msapplication-TileImage" content="<?= asset('uploads/2023/11/fav.png') ?>">

<?php foreach ($styles as $href): ?>
	<link rel="stylesheet" href="<?= asset($href) ?>" media="<?= e(CSS_MEDIA[$href] ?? 'all') ?>">
<?php endforeach; ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php $ff = implode('%7C', array_map(static function ($f) { return $f . ':100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic'; }, $fonts)); ?>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=<?= $ff ?>&display=swap" media="all">

<script id="wpml-cookie-js-extra">
var wpml_cookies = {"wp-wpml_current_language":{"value":"<?= e(cfg('locale', 'az')) ?>","expires":1,"path":"/"}};
</script>
<script id="wpml-cookie-js" src="<?= asset('assets/vendor/plugins/sitepress-multilingual-cms/res/js/cookies/language-cookie.js') ?>"></script>
<?php $ga = trim((string) cfg('ga_id')); if ($ga !== ''): ?>
<!-- Google tag (gtag.js) snippet added by Site Kit -->
<script id="google_gtagjs-js" src="https://www.googletagmanager.com/gtag/js?id=<?= rawurlencode($ga) ?>" async></script>
<script id="google_gtagjs-js-after">
window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}
gtag("set","linker",{"domains":["<?= e(parse_url(abs_url(), PHP_URL_HOST) ?: 'itkin.az') ?>"]});
gtag("js", new Date());
gtag("set", "developer_id.dZTNiMT", true);
gtag("config", "<?= e($ga) ?>");
</script>
<!-- End Google tag (gtag.js) snippet added by Site Kit -->
<?php endif; ?>
</head>
<body class="<?= e($meta['body_class']) ?>">


<a class="skip-link screen-reader-text" href="#content">Skip to content</a>
