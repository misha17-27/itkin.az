<?php
/**
 * Sənədin başlığı / document head.
 * $meta, $styles və $content dəyişənləri render() tərəfindən verilir.
 */
?>
<!doctype html>
<html lang="<?= e(cfg('locale', 'az')) ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
	<link rel="alternate" hreflang="<?= e(cfg('locale', 'az')) ?>" href="<?= e($meta['canonical']) ?>">
	<link rel="alternate" hreflang="x-default" href="<?= e($meta['canonical']) ?>">
	<title><?= e($meta['title']) ?></title>
	<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php if ($meta['description'] !== ''): ?>
	<meta name="description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
	<meta property="og:locale" content="az_AZ">
	<meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
	<meta property="og:title" content="<?= e($meta['og_title'] !== '' ? $meta['og_title'] : $meta['title']) ?>">
<?php if ($meta['description'] !== ''): ?>
	<meta property="og:description" content="<?= e($meta['description']) ?>">
<?php endif; ?>
	<meta property="og:url" content="<?= e($meta['canonical']) ?>">
	<meta property="og:site_name" content="<?= e(cfg('site_name')) ?>">
<?php if ($meta['image'] !== ''): ?>
	<meta property="og:image" content="<?= e($meta['image']) ?>">
	<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
	<script type="application/ld+json" class="yoast-schema-graph"><?= schema_graph($meta, $crumbs ?? [], $meta['schema_entry'] ?? null) ?></script>

	<link rel="icon" href="<?= asset('uploads/2023/11/fav-150x150.png') ?>" sizes="32x32">
	<link rel="icon" href="<?= asset('uploads/2023/11/fav.png') ?>" sizes="192x192">
	<link rel="apple-touch-icon" href="<?= asset('uploads/2023/11/fav.png') ?>">
	<meta name="msapplication-TileImage" content="<?= asset('uploads/2023/11/fav.png') ?>">

<?php foreach ($styles as $href): ?>
	<link rel="stylesheet" href="<?= asset($href) ?>" media="all">
<?php endforeach; ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic%7CRoboto+Slab:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic%7CMontserrat:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic%7CInter:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic%7CPoppins:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic&display=swap" media="all">
</head>
<body class="<?= e($meta['body_class']) ?>">


<a class="skip-link screen-reader-text" href="#content">Skip to content</a>

