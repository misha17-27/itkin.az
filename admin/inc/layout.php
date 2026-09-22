<?php
/**
 * Admin panelinin çərçivəsi / layout.
 */

const ADMIN_SECTIONS = [
    'dashboard' => ['İcmal',        'M3 12l9-8 9 8v8a2 2 0 0 1-2 2h-4v-6H9v6H5a2 2 0 0 1-2-2z'],
    'posts'     => ['Xəbərlər',     'M4 4h16v4H4zM4 10h10v10H4zM16 10h4v4h-4zM16 16h4v4h-4z'],
    'kitabxana' => ['Kitabxana',    'M4 4h7v16H4zM13 4h7v16h-7z'],
    'itkinlr'   => ['İtkinlər',     'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0z'],
    'categories'=> ['Kateqoriyalar','M4 6h16M4 12h16M4 18h10'],
    'pages'     => ['Səhifələr',    'M6 3h8l4 4v14H6zM14 3v4h4'],
    'menus'     => ['Menyular',     'M4 6h16M4 12h16M4 18h16'],
    'media'     => ['Şəkillər',     'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5'],
    'settings'  => ['Ayarlar',      'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM4 12h2M18 12h2M12 4v2M12 18v2'],
];

function admin_head(string $title): void
{
    $flash = admin_flash();
    ?>
<!doctype html>
<html lang="az">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e($title) ?> — İtkin admin</title>
	<link rel="icon" href="<?= asset('uploads/2023/11/fav.png') ?>">
	<link rel="stylesheet" href="<?= asset('admin/assets/admin.css') ?>">
</head>
<body>
<?php if ($flash): ?>
<div class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['text']) ?></div>
<?php endif; ?>
<?php
}

function admin_shell_start(string $section, string $title, array $actions = []): void
{
    admin_head($title);
    ?>
<div class="shell">
	<aside class="side">
		<a class="side__brand" href="<?= e(admin_url()) ?>">
			<img src="<?= asset('uploads/2023/11/fav.png') ?>" alt="" width="34" height="34">
			<span>İtkin<small>idarə paneli</small></span>
		</a>
		<nav class="side__nav">
<?php foreach (ADMIN_SECTIONS as $key => [$label, $path]): ?>
			<a class="side__link<?= $key === $section ? ' is-active' : '' ?>" href="<?= e(admin_url($key === 'dashboard' ? [] : ['section' => $key])) ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($path) ?>"/></svg>
				<?= e($label) ?>
			</a>
<?php endforeach; ?>
		</nav>
		<div class="side__foot">
			<a class="side__out" href="<?= e(base_path() . '/') ?>" target="_blank" rel="noopener">Saytı aç ↗</a>
			<a class="side__out" href="<?= e(admin_url(['action' => 'logout'])) ?>">Çıxış</a>
		</div>
	</aside>

	<main class="main">
		<header class="top">
			<h1><?= e($title) ?></h1>
			<div class="top__actions">
<?php foreach ($actions as $action): ?>
				<a class="btn<?= !empty($action['primary']) ? ' btn--primary' : '' ?>" href="<?= e($action['href']) ?>"><?= e($action['label']) ?></a>
<?php endforeach; ?>
			</div>
		</header>
		<div class="content">
<?php
}

function admin_shell_end(): void
{
    ?>
		</div>
	</main>
</div>
<script src="<?= asset('admin/assets/admin.js') ?>"></script>
<script src="<?= asset('admin/assets/editor.js') ?>"></script>
</body>
</html>
<?php
}
