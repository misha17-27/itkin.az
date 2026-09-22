<?php
/** İcmal / dashboard */

$posts = data_load('posts');
$books = data_load('kitabxana');
$miss  = data_load('itkinlr');
$cats  = data_load('categories');

$latest = $posts;
usort($latest, static function (array $a, array $b) { return strcmp($b['date'], $a['date']); });
$latest = array_slice($latest, 0, 6);

/* yazma icazələrini yoxlayırıq — cPanel-də ən çox rast gəlinən problem */
$root = dirname(__DIR__, 2);
$checks = [
    'data/'            => is_writable($root . '/data'),
    'uploads/'         => is_writable($root . '/uploads'),
    'storage/'         => is_dir($root . '/storage') ? is_writable($root . '/storage') : is_writable($root),
];

admin_shell_start('dashboard', 'İcmal', [
    ['href' => admin_url(['section' => 'posts', 'action' => 'edit']), 'label' => '+ Yeni xəbər', 'primary' => true],
]);
?>
<div class="media-grid" style="grid-template-columns:repeat(auto-fill,minmax(190px,1fr));margin-bottom:18px">
<?php
$tiles = [
    ['Xəbərlər',      count($posts), 'posts'],
    ['Kitablar',      count($books), 'kitabxana'],
    ['İtkinlər',      count($miss),  'itkinlr'],
    ['Kateqoriyalar', count($cats),  'categories'],
];
foreach ($tiles as [$label, $n, $section]): ?>
	<a class="card" style="text-decoration:none;display:block" href="<?= e(admin_url(['section' => $section])) ?>">
		<div class="card__body">
			<div style="font-size:30px;font-weight:700;line-height:1.1"><?= $n ?></div>
			<div class="table__meta"><?= e($label) ?></div>
		</div>
	</a>
<?php endforeach; ?>
</div>

<?php if (in_array(false, $checks, true)): ?>
<div class="errors">
	<strong>Diqqət: bəzi qovluqlara yazmaq olmur.</strong>
	<ul>
<?php foreach ($checks as $dir => $ok): if ($ok) { continue; } ?>
		<li><code><?= e($dir) ?></code> — hostinqdə bu qovluğa yazma icazəsi verin (755 və ya 775).</li>
<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<div class="card">
	<div class="card__head">Son xəbərlər</div>
	<table class="table" style="border:0;border-radius:0">
		<tbody>
<?php foreach ($latest as $row): ?>
			<tr>
				<td style="width:70px"><?php if (!empty($row['thumb']['url'])): ?><img class="table__thumb" src="<?= asset($row['thumb']['url']) ?>" alt=""><?php endif; ?></td>
				<td><a class="table__title" href="<?= e(admin_url(['section' => 'posts', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['title']) ?></a></td>
				<td class="table__meta" style="width:130px"><?= e(az_date($row['date'])) ?></td>
			</tr>
<?php endforeach; ?>
<?php if (!$latest): ?><tr><td class="empty">Hələ xəbər yoxdur.</td></tr><?php endif; ?>
		</tbody>
	</table>
</div>

<div class="card">
	<div class="card__head">Nədən başlamaq</div>
	<div class="card__body">
		<ul style="margin:0;padding-left:18px;line-height:1.9">
			<li><strong>Xəbər əlavə etmək:</strong> “Xəbərlər” → “+ Yeni xəbər”. Şəkli əvvəlcə “Şəkillər” bölməsinə yükləyin.</li>
			<li><strong>Səhifə mətnini dəyişmək:</strong> “Səhifələr” → lazım olan səhifə. Sahəni boşaltsanız orijinal mətn qayıdır.</li>
			<li><strong>Menyunu dəyişmək:</strong> “Menyular”. Bəndi silmək üçün adını boşaldın.</li>
			<li><strong>Şifrəni dəyişmək:</strong> “Ayarlar” → “Şifrə”.</li>
		</ul>
	</div>
</div>
<?php
admin_shell_end();
