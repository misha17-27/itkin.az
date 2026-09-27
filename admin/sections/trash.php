<?php
/**
 * Zibil qutusu bölməsi / trash.
 *
 * Silinmiş xəbərlər, kitablar, itkinlər, kateqoriyalar və qalereyadakı fayllar:
 * nə, kim və nə vaxt sildi. «Bərpa et» elementi əvvəlki yerinə (qeydi ingiliscə
 * versiyası ilə birlikdə) qaytarır, «Birdəfəlik sil» isə tamamilə silir.
 * 30 gündən köhnələr avtomatik silinir — admin/inc/trash.php.
 * Hər iki rol (administrator və redaktor) üçün açıqdır.
 */

/** Növ => süzgəcdəki ad (cəm) */
const TRASH_TABS = [
    'posts'      => 'Xəbərlər',
    'kitabxana'  => 'Kitablar',
    'itkinlr'    => 'İtkinlər',
    'categories' => 'Kateqoriyalar',
    'media'      => 'Fayllar',
];

$filter = is_string($_GET['type'] ?? null) && isset(TRASH_TABS[$_GET['type']]) ? (string) $_GET['type'] : '';
$back   = ['section' => 'trash'] + ($filter !== '' ? ['type' => $filter] : []);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/* ---------------------------------------------------------------- önizləmə */

/*
 * Zibil qutusundakı şəkil: storage/ brauzerdən açılmır, ona görə şəkli panel
 * özü verir — yalnız daxil olmuş istifadəçiyə və yalnız şəkil növlərini.
 */
if ($action === 'preview') {
    $item  = trash_get((string) ($_GET['id'] ?? ''));
    $abs   = $item !== null && $item['type'] === 'media' ? trash_file_abs((string) ($item['file'] ?? '')) : null;
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
              'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
    $ext   = $abs !== null ? strtolower(pathinfo($abs, PATHINFO_EXTENSION)) : '';
    if (!isset($mimes[$ext])) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mimes[$ext]);
    header('Content-Length: ' . (string) filesize($abs));
    header('Cache-Control: private, max-age=300');
    // SVG ayrıca açılsa belə içində heç nə işə düşməsin
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    readfile($abs);
    exit;
}

/* ---------------------------------------------------------------- əməliyyatlar */

if ($action === 'restore') {
    if (!$isPost || !admin_token_ok()) {
        admin_redirect($back, 'Bərpa təsdiqlənmədi. Yenidən cəhd edin.', 'error');
    }
    $result = trash_restore(post_str('id'));
    admin_redirect($back, $result['message'], $result['ok'] ? 'ok' : 'error');
}

if ($action === 'destroy') {
    admin_require_delete('trash');
    $item = trash_destroy(post_str('id'));
    if ($item === null) {
        admin_redirect($back, 'Bu element zibil qutusunda yoxdur — yəqin artıq silinib və ya bərpa olunub.', 'error');
    }
    admin_redirect($back, '“' . ($item['title'] ?? '') . '” birdəfəlik silindi.');
}

if ($action === 'empty') {
    admin_require_delete('trash');
    $n = trash_empty();
    admin_redirect(['section' => 'trash'], $n > 0
        ? 'Zibil qutusu boşaldıldı: ' . $n . ' element birdəfəlik silindi.'
        : 'Zibil qutusu onsuz da boşdur.');
}

/* ---------------------------------------------------------------- siyahı */

// Açılanda 30 gündən köhnələr silinir (köhnə qaydada silinmiş fayllar da siyahıya düşür)
trash_purge();

$items  = trash_all();
$counts = ['' => count($items)];
foreach ($items as $it) {
    $counts[$it['type']] = ($counts[$it['type']] ?? 0) + 1;
}
$shown = $filter === '' ? $items : array_values(array_filter($items, static function (array $it) use ($filter) {
    return $it['type'] === $filter;
}));

$root = dirname(__DIR__, 2);

/** Qeydin kiçik şəkli (uploads/-da hələ varsa) */
$thumbOf = static function (array $item) use ($root): string {
    $row = (array) ($item['row'] ?? []);
    foreach (['card_thumb', 'thumb'] as $key) {
        $url = (string) ($row[$key]['url'] ?? '');
        if ($url !== '' && page_path_ok($url) && is_file($root . '/' . $url)) {
            return asset($url);
        }
    }
    return '';
};

$sizeOf = static function (int $bytes): string {
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
};

admin_shell_start('trash', 'Zibil qutusu', [
    ['href' => admin_url(['section' => 'media']), 'label' => 'Qalereya'],
]);
?>
<div class="card">
	<div class="card__body trash-intro">
		<p>
			Silinən xəbərlər, kitablar, itkinlər, kateqoriyalar və qalereyadakı fayllar burada
			<strong><?= TRASH_DAYS ?> gün</strong> saxlanılır, sonra avtomatik birdəfəlik silinir.
			«Bərpa et» elementi əvvəlki yerinə qaytarır — qeyd ingiliscə versiyası ilə birlikdə, fayl isə əvvəlki qovluğuna.
		</p>
<?php if ($items): ?>
		<form method="post" action="<?= e(admin_url(['section' => 'trash', 'action' => 'empty'])) ?>">
			<?= admin_token_field() ?>
			<button class="btn btn--sm btn--danger" type="submit"
			        data-confirm="Zibil qutusundakı bütün <?= count($items) ?> element birdəfəlik silinsin? Bunu geri qaytarmaq olmur.">Zibil qutusunu boşalt</button>
		</form>
<?php endif; ?>
	</div>
</div>

<?php if ($items): ?>
<nav class="filter-tabs" aria-label="Növə görə süzgəc">
	<a class="filter-tabs__tab<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(admin_url(['section' => 'trash'])) ?>">Hamısı <span class="filter-tabs__n"><?= $counts[''] ?></span></a>
<?php foreach (TRASH_TABS as $key => $label): ?>
<?php   if (empty($counts[$key]) && $filter !== $key) { continue; } ?>
	<a class="filter-tabs__tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= e(admin_url(['section' => 'trash', 'type' => $key])) ?>"><?= e($label) ?> <span class="filter-tabs__n"><?= (int) ($counts[$key] ?? 0) ?></span></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>

<table class="table trash-table">
	<thead>
		<tr>
			<th style="width:70px"></th>
			<th>Ad</th>
			<th style="width:110px">Növ</th>
			<th style="width:160px">Kim sildi</th>
			<th style="width:190px">Nə vaxt</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
<?php foreach ($shown as $item): ?>
<?php
    $type    = $item['type'];
    $isMedia = $type === 'media';
    $row     = (array) ($item['row'] ?? []);
    $ts      = (int) ($item['deleted_at'] ?? 0);
    $left    = trash_days_left($item);
    $who     = trash_who_label($item);
    $ext     = $isMedia ? strtolower(pathinfo((string) ($item['path'] ?? ''), PATHINFO_EXTENSION)) : '';
?>
		<tr>
			<td>
<?php if ($isMedia && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)): ?>
				<img class="table__thumb" src="<?= e(admin_url(['section' => 'trash', 'action' => 'preview', 'id' => $item['id']])) ?>" alt="" loading="lazy">
<?php elseif ($isMedia): ?>
				<span class="table__icon"><?= e(strtoupper($ext)) ?></span>
<?php elseif (($thumb = $thumbOf($item)) !== ''): ?>
				<img class="table__thumb" src="<?= e($thumb) ?>" alt="" loading="lazy">
<?php endif; ?>
			</td>
			<td>
				<span class="table__title"><?= e((string) ($item['title'] ?? '') !== '' ? (string) $item['title'] : '(adsız)') ?></span>
<?php if (!empty($item['en'])): ?>
				<span class="lang-flag" title="İngiliscə versiyası da bərpa olunacaq">EN</span>
<?php endif; ?>
				<div class="table__meta">
<?php if ($isMedia): ?>
					<?= e((string) ($item['path'] ?? '')) ?><?= !empty($item['size']) ? ' · ' . e($sizeOf((int) $item['size'])) : '' ?>
<?php   if (trash_file_abs((string) ($item['file'] ?? '')) === null): ?>
					· <span class="trash-left is-soon">faylın özü tapılmadı</span>
<?php   endif; ?>
<?php else: ?>
					/<?= e(TRASH_TYPES[$type][3] . (string) ($row['slug'] ?? '')) ?>/
<?php endif; ?>
				</div>
<?php if ($isMedia && !empty($item['used_in'])): ?>
				<div class="table__meta" title="<?= e(implode("\n", (array) $item['used_in'])) ?>">
					Silinəndə istifadə olunurdu: <?= e(implode(', ', array_slice((array) $item['used_in'], 0, 3))) ?><?= count((array) $item['used_in']) > 3 ? ' …' : '' ?>
				</div>
<?php endif; ?>
			</td>
			<td><span class="badge trash-type trash-type--<?= e($type) ?>"><?= e(TRASH_TYPES[$type][0]) ?></span></td>
			<td class="table__meta"><?= $who !== '' ? e($who) : '—' ?><?php
                $login = (string) ($item['deleted_by']['login'] ?? '');
                if ($login !== '' && $login !== $who): ?><br><small><?= e($login) ?></small><?php endif; ?></td>
			<td class="table__meta">
				<?= e(az_date(date('Y-m-d\TH:i:s', $ts))) ?>, <?= e(date('H:i', $ts)) ?>
				<div class="trash-left<?= $left <= 3 ? ' is-soon' : '' ?>">
					<?= $left > 0 ? e($left . ' gün sonra birdəfəlik silinəcək') : 'Bu gün birdəfəlik silinəcək' ?>
				</div>
			</td>
			<td class="is-right">
				<div class="table__actions">
					<form method="post" action="<?= e(admin_url(['section' => 'trash', 'action' => 'restore'] + ($filter !== '' ? ['type' => $filter] : []))) ?>">
						<?= admin_token_field() ?>
						<input type="hidden" name="id" value="<?= e($item['id']) ?>">
						<button class="btn btn--sm btn--primary" type="submit">Bərpa et</button>
					</form>
					<form method="post" action="<?= e(admin_url(['section' => 'trash', 'action' => 'destroy'] + ($filter !== '' ? ['type' => $filter] : []))) ?>">
						<?= admin_token_field() ?>
						<input type="hidden" name="id" value="<?= e($item['id']) ?>">
						<button class="btn btn--sm btn--danger" type="submit"
						        data-confirm="&#8220;<?= e(mb_strimwidth((string) ($item['title'] ?? ''), 0, 70, '…')) ?>&#8221; birdəfəlik silinsin? Bunu geri qaytarmaq olmur.">Birdəfəlik sil</button>
					</form>
				</div>
			</td>
		</tr>
<?php endforeach; ?>
<?php if (!$shown): ?>
		<tr><td colspan="6" class="empty"><?= $items ? 'Bu növdə silinmiş element yoxdur.' : 'Zibil qutusu boşdur.' ?></td></tr>
<?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
