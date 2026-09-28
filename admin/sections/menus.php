<?php
/**
 * Menyular bölməsi.
 * Üç menyu var: əsas menyu və altlıqdakı iki sütun.
 * Bəndlər iki səviyyəlidir.
 * Bəndlərin ingiliscə adları data/<menyu>-en.php-də bəndin id-sinə görə saxlanılır.
 */

if (!function_exists('menus_en_save')) {
    /**
     * Menyunun ingiliscə adlarını yazır ([bəndin id-si => ['label' => …]]).
     * Heç nə dəyişməyibsə fayla toxunulmur.
     */
    function menus_en_save(string $menu, array $next): void
    {
        $old = data_load($menu . '-en');
        ksort($old);
        ksort($next);
        if ($next === $old) {
            return;
        }
        store_save($menu . '-en', $next, 'Menyu — ingiliscə tərcümə (id => sahələr; boş sahə azərbaycancanı saxlayır)');
    }
}

if (!function_exists('menus_signature')) {
    /**
     * Menyunun redaktə olunan hissəsi: ad, ünvan, sıra və iç-içəlik. «Son dəyişiklik»
     * yalnız bunlar dəyişəndə yazılır — yadda saxlayanda köhnə tam ünvanların
     * (https://itkin.az/…) qısa yola çevrilməsi dəyişiklik sayılmır.
     */
    function menus_signature(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            $out[] = [
                (string) ($node['label'] ?? ''),
                // null — «#» (keçidsiz), '' — ana səhifə: fərqli sayılmalıdır
                !empty($node['external']) ? 'x:' . (string) ($node['href'] ?? '')
                    : (($node['path'] ?? null) === null ? '#' : 'p:' . (string) $node['path']),
                menus_signature((array) ($node['children'] ?? [])),
            ];
        }
        return $out;
    }
}

const ADMIN_MENUS = [
    'menu'          => 'Əsas menyu',
    'menu-footer-1' => 'Altlıq — birinci sütun',
    'menu-footer-2' => 'Altlıq — ikinci sütun',
];

/*
 * Bu fayl «Altlıq» bölməsindən də qoşulur (admin/sections/footer.php) — altlığın iki
 * sütununun keçidləri orada redaktə olunur. Onda bölmə, başlıq, seçilə bilən menyu,
 * ünvan parametrləri və yuxarıdakı nişanlar başqadır. Burada — yalnız əsas menyu.
 */
$menuSection = $menuSection ?? 'menus';
$menuTitle   = $menuTitle ?? 'Menyular';
$menuChoices = $menuChoices ?? ['menu'];
$menuTop     = $menuTop ?? '';

$which = (string) ($_GET['menu'] ?? $menuChoices[0]);
if (!in_array($which, $menuChoices, true)) {
    $which = $menuChoices[0];
}
// bu menyunun ünvanı (formanın, yönləndirmənin, «Ləğv et»in)
$menuParams = $menuParams ?? ['section' => $menuSection, 'menu' => $which];

$items  = data_load($which);
$errors = [];

/* ---------------------------------------------------------------- yazmaq */

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } else {
        $labels  = (array) ($_POST['label'] ?? []);
        $paths   = (array) ($_POST['path'] ?? []);
        $parents = (array) ($_POST['parent'] ?? []);
        $ids     = (array) ($_POST['mid'] ?? []);

        // Yeni bəndlər üçün boş id: mövcud və formadakı bütün id-lərdən böyük.
        // İngiliscə adlar id-yə görə saxlanılır — iki bənd eyni id-ni almamalıdır.
        $used = array_map('intval', array_values($ids));
        $walk = static function (array $nodes) use (&$walk, &$used): void {
            foreach ($nodes as $n) {
                $used[] = (int) ($n['id'] ?? 0);
                $walk((array) ($n['children'] ?? []));
            }
        };
        $walk($items);
        $nextId = max(899, max($used ?: [0])) + 1;
        $taken  = [];

        // 1) boş olmayan sətirlərdən düz siyahı
        $flat = [];
        foreach ($labels as $key => $label) {
            $label = trim(strip_tags((string) $label));   // ad — sadə mətn, HTML yox
            if ($label === '') {
                continue;                       // boş ad = silinmiş bənd
            }
            $raw = trim((string) ($paths[$key] ?? ''));
            $mid = (int) ($ids[$key] ?? 0);
            if ($mid <= 0 || isset($taken[$mid])) {
                $mid = $nextId++;               // yeni bənd (və ya köhnəlmiş formada təkrar id)
            }
            $taken[$mid] = true;
            $flat[(string) $key] = [
                'key'    => (string) $key,
                'parent' => (string) ($parents[$key] ?? ''),
                'node'   => [
                    'id'       => $mid,
                    'type'     => admin_menu_type($raw),
                    'object'   => admin_menu_object($raw),
                    'label'    => $label,
                    'href'     => $raw === '' ? '#' : $raw,
                    'children' => [],
                    'path'     => admin_menu_path($raw),
                    'external' => admin_menu_is_external($raw),
                ],
            ];
        }

        // 2) valideyn–övlad əlaqəsi (yalnız bir səviyyə dərinlik)
        $childrenOf = [];
        foreach ($flat as $key => $row) {
            $parent = $row['parent'];
            if ($parent !== '' && $parent !== $key && isset($flat[$parent]) && $flat[$parent]['parent'] === '') {
                $childrenOf[$parent][] = $key;
            }
        }

        // 3) ağac — sıra formadakı kimi qalır
        $tree = [];
        foreach ($flat as $key => $row) {
            $parent = $row['parent'];
            $isChild = $parent !== '' && $parent !== $key && isset($flat[$parent]) && $flat[$parent]['parent'] === '';
            if ($isChild) {
                continue;
            }
            $node = $row['node'];
            foreach ($childrenOf[$key] ?? [] as $childKey) {
                $node['children'][] = $flat[$childKey]['node'];
            }
            $tree[] = $node;
        }

        $enBefore = data_load($which . '-en', true);   // «son dəyişiklik» üçün müqayisə
        store_save($which, admin_menu_attach_pages($tree), ADMIN_MENUS[$which] . ' / navigation');

        // 4) ingiliscə adlar — yalnız qalan bəndlərin, boş ad saxlanılmır.
        //    Formada bu sahələr yoxdursa (köhnə səhifədən gələn sorğu) tərcüməyə toxunulmur.
        if (is_array($_POST['en_label'] ?? null)) {
            $enLabels = $_POST['en_label'];
            $enNext   = [];
            foreach ($flat as $key => $row) {
                $en = $enLabels[$key] ?? '';
                $en = is_string($en) ? trim(strip_tags(admin_eol($en))) : '';
                if ($en !== '') {
                    $enNext[(int) $row['node']['id']] = ['label' => $en];
                }
            }
            menus_en_save($which, $enNext);
        }

        // «Son dəyişiklik» (admin/inc/authors.php) — menyu və ya ingiliscə adlar həqiqətən dəyişibsə
        if (menus_signature(data_load($which, true)) !== menus_signature($items) || data_load($which . '-en', true) !== $enBefore) {
            admin_section_mark('menus:' . $which);
        }

        admin_redirect($menuParams, 'Menyu yadda saxlanıldı.');
    }
}

/* ---------------------------------------------------------------- forma */

// Mövcud bəndləri nömrələnmiş düz siyahıya açırıq
$flatRows = [];
$i = 0;
foreach ($items as $top) {
    $topIndex = $i;
    $flatRows[] = ['item' => $top, 'parent' => '', 'i' => $i++];
    foreach ($top['children'] ?? [] as $child) {
        $flatRows[] = ['item' => $child, 'parent' => (string) $topIndex, 'i' => $i++];
    }
}

$parentOptions = ['' => '— üst səviyyə —'];
foreach ($flatRows as $row) {
    if ($row['parent'] === '') {
        $parentOptions[(string) $row['i']] = $row['item']['label'];
    }
}

// ingiliscə adlar: bəndin id-si => ['label' => …]
$enMap = data_load($which . '-en');

$renderRow = static function (array $row) use ($parentOptions, $enMap) {
    $it   = $row['item'];
    $idx  = $row['i'];
    $href = ($it['path'] ?? null) === null ? ($it['label'] === '' ? '' : '#') : (string) $it['path'];
    $en   = (int) ($it['id'] ?? 0) > 0 ? (string) ($enMap[(int) $it['id']]['label'] ?? '') : '';
    ?>
			<tr>
				<td>
					<input type="hidden" name="mid[<?= $idx ?>]" value="<?= (int) ($it['id'] ?? 0) ?>">
					<input class="input" type="text" name="label[<?= $idx ?>]" value="<?= e((string) ($it['label'] ?? '')) ?>"
					       placeholder="<?= $it['label'] === '' ? 'Yeni bənd…' : '' ?>">
					<div class="pf__en" style="display:flex;gap:8px;align-items:center;margin-top:6px">
						<span class="lang-flag" aria-hidden="true">EN</span>
						<input class="input" type="text" name="en_label[<?= $idx ?>]" value="<?= e($en) ?>" lang="en"
						       placeholder="İngiliscə ad" aria-label="İngiliscə ad">
					</div>
				</td>
				<td><input class="input" type="text" name="path[<?= $idx ?>]" value="<?= e($href) ?>" placeholder="haqqimizda"></td>
				<td>
					<select class="select" name="parent[<?= $idx ?>]">
<?php foreach ($parentOptions as $key => $text): ?>
<?php if ((string) $key === (string) $idx) { continue; } ?>
						<option value="<?= e((string) $key) ?>"<?= (string) $key === $row['parent'] ? ' selected' : '' ?>><?= e($text) ?></option>
<?php endforeach; ?>
					</select>
				</td>
			</tr>
    <?php
};

admin_shell_start($menuSection, $menuTitle);
?>
<?php if ($menuTop !== ''): ?>
<?= $menuTop ?>
<?php else: ?>
<p class="field__hint" style="margin-top:0">
	Altlıqdakı keçidlər («Yararlı linklər», «Media») və yazılar —
	<a href="<?= e(admin_url(['section' => 'footer'])) ?>">«Altlıq» bölməsində</a>.
</p>
<?php endif; ?>

<?= admin_section_bar('menus:' . $which) ?>
<?php f_errors($errors); ?>
<?php f_open($menuParams + ['action' => 'edit']); ?>

<div class="card">
	<div class="card__head"><?= e(ADMIN_MENUS[$which]) ?></div>
	<div class="card__body">
		<p class="field__hint" style="margin-top:0">
			Ünvan sahəsinə saytın daxili yolunu yazın (<code>haqqimizda</code>, <code>category/tedbirler</code>),
			tam ünvanı (<code>https://…</code>) və ya açılan siyahı üçün <code>#</code>.
			Bəndi silmək üçün adını boşaldın. Alt bənd etmək üçün “Valideyn” sütununda üst bəndi seçin.
		</p>
		<p class="field__hint">
			<span class="lang-flag">EN</span> sahəsi — bəndin saytın ingiliscə versiyasındakı adı. Boş buraxsanız
			(məsələn, yeni bənddə), ingiliscə versiyada azərbaycanca ad göstərilir. Ünvan və sıra hər iki dildə eynidir;
			səhifənin ingiliscə versiyası yoxdursa, keçid azərbaycanca səhifəyə aparır.
		</p>

		<table class="table">
			<thead><tr><th style="width:36%">Ad</th><th style="width:36%">Ünvan</th><th style="width:28%">Valideyn</th></tr></thead>
			<tbody>
<?php
foreach ($flatRows as $row) {
    $renderRow($row);
}
for ($k = 0; $k < 3; $k++) {
    $renderRow(['item' => ['id' => 0, 'label' => '', 'path' => null], 'parent' => '', 'i' => $i++]);
}
?>
			</tbody>
		</table>
	</div>
</div>

<div class="actions">
	<button class="btn btn--primary" type="submit">Yadda saxla</button>
	<a class="btn" href="<?= e(admin_url($menuParams)) ?>">Ləğv et</a>
</div>
<?php
f_close();
admin_shell_end();
