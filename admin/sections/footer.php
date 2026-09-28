<?php
/**
 * Altlıq (footer) — saytın hər səhifəsinin aşağısındakı hissə.
 *
 * Üç nişan:
 *   Yazılar   — sütun başlıqları və müəllif hüququ sətri (AZ və EN), data/footer.php
 *   2 sütun   — «Yararlı linklər» və «Media» sütunlarının keçidləri; menyu redaktoru
 *               (admin/sections/menus.php) bu bölmənin içində açılır
 * Telefon, e-poçt və sosial şəbəkələr — «Əlaqə və sosial şəbəkələr» bölməsində.
 */

/** Nişan => [sütunun menyusu (yazılar üçün null), altlıqdakı başlığın açarı] */
const FOOTER_TABS = [
    'texts' => [null, ''],
    'links' => ['menu-footer-1', 'links'],
    'media' => ['menu-footer-2', 'media'],
];

/** Sahələr: açar => sahənin adı */
const FOOTER_FIELDS = [
    'links'     => 'Birinci sütunun başlığı',
    'media'     => 'İkinci sütunun başlığı',
    'contact'   => 'Əlaqə sütununun başlığı',
    'copyright' => 'Ən aşağıdakı sətir (müəllif hüququ)',
];

$tab = (string) ($_GET['tab'] ?? 'texts');
if (!isset(FOOTER_TABS[$tab])) {
    $tab = 'texts';
}

/** Sütunun indiki (azərbaycanca) başlığı — nişanın adı */
$footerTitle = static function (string $key): string {
    $saved = trim((string) (data_load('footer')['az'][$key] ?? ''));
    return $saved !== '' ? $saved : FOOTER_TEXTS[$key];
};

// Yuxarıdakı nişanlar və «Əlaqə» bölməsinə keçid
ob_start();
?>
<nav class="filter-tabs" aria-label="Altlığın hissələri">
	<a class="filter-tabs__tab<?= $tab === 'texts' ? ' is-active' : '' ?>" href="<?= e(admin_url(['section' => 'footer'])) ?>">Başlıqlar və yazılar</a>
<?php foreach (FOOTER_TABS as $key => [$menu, $titleKey]): ?>
<?php   if ($menu === null) { continue; } ?>
	<a class="filter-tabs__tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e(admin_url(['section' => 'footer', 'tab' => $key])) ?>">«<?= e($footerTitle($titleKey)) ?>» — keçidlər</a>
<?php endforeach; ?>
</nav>
<p class="field__hint" style="margin-top:0">
	Altlıqdakı telefon, e-poçt və sosial şəbəkələr —
	<a href="<?= e(admin_url(['section' => 'contacts'])) ?>">«Əlaqə və sosial şəbəkələr» bölməsində</a>.
</p>
<?php
$footerTop = (string) ob_get_clean();

/* ---------------------------------------------------------------- sütunların keçidləri */

if (FOOTER_TABS[$tab][0] !== null) {
    $menuSection = 'footer';
    $menuTitle   = 'Altlıq';
    $menuChoices = [FOOTER_TABS[$tab][0]];
    $menuParams  = ['section' => 'footer', 'tab' => $tab];
    $menuTop     = $footerTop;
    require __DIR__ . '/menus.php';
    return;
}

/* ---------------------------------------------------------------- yazılar */

$errors = [];
$saved  = data_load('footer');
$form   = ['az' => (array) ($saved['az'] ?? []), 'en' => (array) ($saved['en'] ?? [])];

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }
    $next = ['az' => [], 'en' => []];
    foreach (FOOTER_FIELDS as $key => $label) {
        foreach (['az', 'en'] as $lang) {
            $value = post_str($lang . '_' . $key);
            if (mb_strlen($value) > 200) {
                $errors[] = $label . ($lang === 'en' ? ' (ingiliscə)' : '') . ': ən çox 200 simvol.';
            }
            // orijinalla eynidirsə qeyd lazım deyil — sayt orijinalı göstərir
            $orig = $key === 'media' ? FOOTER_TEXTS[$key] : t_lang(FOOTER_TEXTS[$key], $lang);
            if ($value !== '' && $value !== $orig) {
                $next[$lang][$key] = $value;
            }
        }
    }
    $next = array_filter($next);
    if (!$errors) {
        $before = data_load('footer');
        if ($next !== $before) {
            store_save('footer', $next, 'Altlığın yazıları / footer texts (boş — orijinal mətn)');
            admin_section_mark('footer');   // «Son dəyişiklik» (admin/inc/authors.php)
        }
        admin_redirect(['section' => 'footer'], 'Altlıq yadda saxlanıldı.');
    }
    $form = ['az' => [], 'en' => []];
    foreach (FOOTER_FIELDS as $key => $label) {
        $form['az'][$key] = post_str('az_' . $key);
        $form['en'][$key] = post_str('en_' . $key);
    }
}

admin_shell_start('footer', 'Altlıq');
echo $footerTop;
echo admin_section_bar('footer');
f_errors($errors);
f_open(['section' => 'footer', 'action' => 'edit']);
?>
<div class="card">
	<div class="card__head">Başlıqlar və yazılar</div>
	<div class="card__body">
		<p class="field__hint" style="margin-top:0">
			Boş buraxılan sahənin yerində orijinal mətn göstərilir (boz rəngli nümunədəki kimi).
			<span class="lang-flag">EN</span> — saytın ingiliscə versiyası üçün.
		</p>
		<div class="grid2--even">
<?php foreach (FOOTER_FIELDS as $key => $label): ?>
			<div>
				<?php f_text('az_' . $key, $label, (string) ($form['az'][$key] ?? ''), [
                    'placeholder' => FOOTER_TEXTS[$key],
                ]); ?>
			</div>
			<div>
				<?php f_text('en_' . $key, $label . ' — EN', (string) ($form['en'][$key] ?? ''), [
                    'placeholder' => $key === 'media' ? FOOTER_TEXTS[$key] : t_lang(FOOTER_TEXTS[$key], 'en'),
                ]); ?>
			</div>
<?php endforeach; ?>
		</div>
	</div>
</div>

<div class="actions">
	<button class="btn btn--primary" type="submit">Yadda saxla</button>
	<a class="btn" href="<?= e(admin_url(['section' => 'footer'])) ?>">Ləğv et</a>
</div>
<?php
f_close();
admin_shell_end();
