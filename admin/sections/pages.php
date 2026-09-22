<?php
/**
 * Səhifələr bölməsi.
 *
 * Statik səhifələr Elementor markupudur — burada onların mətnləri,
 * düymələri və rəqəmləri redaktə olunur. Dəyişikliklər data/page-texts.php
 * faylına yazılır, şablonlardakı orijinal mətn isə toxunulmaz qalır.
 */

const ADMIN_PAGE_TITLES = [
    'home'       => 'Ana səhifə',
    'haqqimizda' => 'Haqqımızda',
    'elaqe'      => 'Əlaqə',
    'senedler'   => 'Beynəlxalq sənədlər',
    'qanun'      => 'Milli qanunvericilik',
    'sekiller'   => 'Şəkillər',
    'kitabxana'  => 'Kitabxana səhifəsi',
    'xeberler'   => 'Xəbərlər səhifəsi',
];

const ADMIN_PAGE_URLS = [
    'home'       => '',
    'haqqimizda' => 'haqqimizda',
    'elaqe'      => 'elaqe',
    'senedler'   => 'beynelxalq-senedler',
    'qanun'      => 'milli-qanunvericilik',
    'sekiller'   => 'sekiller',
    'kitabxana'  => 'kitabxana',
    'xeberler'   => 'xeberler',
];

$fields = data_load('page-fields');
$texts  = data_load('page-texts');

// sahələri səhifəyə görə qruplaşdırırıq
$byPage = [];
foreach ($fields as $field) {
    $byPage[$field['page']][] = $field;
}

$which  = (string) ($_GET['page'] ?? 'home');
if (!isset(ADMIN_PAGE_TITLES[$which])) {
    $which = 'home';
}

$errors = [];

/* ---------------------------------------------------------------- yazmaq */

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } else {
        $given = (array) ($_POST['field'] ?? []);
        $next  = $texts;

        foreach ($byPage[$which] ?? [] as $field) {
            $key = $field['key'];
            if (!array_key_exists($key, $given)) {
                continue;
            }
            $value = trim((string) $given[$key]);

            if ($field['type'] === 'heading') {
                $value = admin_clean_html($value);
            } elseif ($field['type'] === 'number') {
                $value = preg_replace('/[^0-9]/', '', $value);
            }

            // Orijinal dəyərə qayıdıbsa, saxlamağa ehtiyac yoxdur
            if ($value === trim((string) $field['value'])) {
                unset($next[$key]);
            } else {
                $next[$key] = $value;
            }
        }

        store_save('page-texts', $next, 'Səhifə mətnləri / page text overrides');
        admin_redirect(['section' => 'pages', 'page' => $which], 'Səhifə yadda saxlanıldı.');
    }
}

/* ---------------------------------------------------------------- forma */

admin_shell_start('pages', 'Səhifələr', [
    ['href' => url(ADMIN_PAGE_URLS[$which]), 'label' => 'Saytda bax ↗'],
]);
?>
<div class="card"><div class="card__body" style="display:flex;gap:8px;flex-wrap:wrap">
<?php foreach (ADMIN_PAGE_TITLES as $key => $label): ?>
	<a class="btn<?= $key === $which ? ' btn--primary' : '' ?>" href="<?= e(admin_url(['section' => 'pages', 'page' => $key])) ?>">
		<?= e($label) ?><?php $n = count($byPage[$key] ?? []); ?><?= $n ? ' · ' . $n : '' ?>
	</a>
<?php endforeach; ?>
</div></div>

<?php f_errors($errors); ?>

<?php if (empty($byPage[$which])): ?>
<div class="card"><div class="card__body">
	<p style="margin:0">
		Bu səhifədə sabit mətn yoxdur — məzmunu avtomatik yığılır
		(<?= $which === 'xeberler' ? 'xəbərlər siyahısı' : ($which === 'kitabxana' ? 'kitab siyahısı' : 'qalereya') ?>).
		Dəyişmək üçün müvafiq bölmədən istifadə edin.
	</p>
</div></div>
<?php else: ?>

<?php f_open(['section' => 'pages', 'action' => 'edit', 'page' => $which]); ?>
<div class="card">
	<div class="card__head"><?= e(ADMIN_PAGE_TITLES[$which]) ?></div>
	<div class="card__body">
		<p class="field__hint" style="margin-top:0">
			Sahəni boşaltsanız orijinal mətn geri qayıdır. Başlıqlarda sətri bölmək üçün
			<code>&lt;br&gt;</code> işlədə bilərsiniz.
		</p>

<?php foreach ($byPage[$which] as $field): ?>
<?php
    $key     = $field['key'];
    $current = array_key_exists($key, $texts) ? (string) $texts[$key] : (string) $field['value'];
    $changed = array_key_exists($key, $texts);
    $name    = 'field[' . $key . ']';
    $multi   = $field['type'] === 'heading' && (strlen($current) > 60 || strpos($current, "\n") !== false);
?>
		<div class="field">
			<label for="f-<?= e($key) ?>">
				<?= e($field['label']) ?>
				<span class="field__hint" style="font-weight:400"><?= e($key) ?><?= $changed ? ' · dəyişdirilib' : '' ?></span>
			</label>
<?php if ($multi): ?>
			<textarea class="textarea" id="f-<?= e($key) ?>" name="<?= e($name) ?>" rows="3"><?= e($current) ?></textarea>
<?php else: ?>
			<input class="input" type="<?= $field['type'] === 'number' ? 'text' : 'text' ?>"
			       id="f-<?= e($key) ?>" name="<?= e($name) ?>" value="<?= e($current) ?>"
			       <?= $field['type'] === 'number' ? 'inputmode="numeric"' : '' ?>>
<?php endif; ?>
<?php if ($changed): ?>
			<span class="field__hint">Orijinal: <?= e(mb_strimwidth((string) $field['value'], 0, 120, '…')) ?></span>
<?php endif; ?>
		</div>
<?php endforeach; ?>
	</div>
</div>

<div class="actions">
	<button class="btn btn--primary" type="submit">Yadda saxla</button>
	<a class="btn" href="<?= e(admin_url(['section' => 'pages', 'page' => $which])) ?>">Ləğv et</a>
</div>
<?php f_close(); ?>
<?php endif; ?>

<?php
admin_shell_end();
