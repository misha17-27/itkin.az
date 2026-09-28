<?php
/**
 * Məzmunun müəllifi / content authorship.
 *
 * Paneldə əlavə olunan və dəyişdirilən qeydlərə (xəbər, kitab, itkin,
 * kateqoriya, statik səhifə) kimin və nə vaxt etdiyi sətrin özündə yazılır:
 *
 *   'created_by'  => ['id' => 3, 'since' => '2026-09-20T10:00:00', 'name' => 'Ali Məmmədov', 'at' => '2026-09-28T15:10:00']
 *   'modified_by' => eyni quruluş — son dəyişiklik
 *
 * - Giriş adı yazılmır (data/ faylları repozitoriyaya düşə bilər) — yalnız id və o anki ad.
 * - 'since' istifadəçinin yaradılma vaxtıdır: istifadəçi silinib id-si başqasına keçsə
 *   belə, köhnə qeydlər yeni istifadəçinin adına çıxmır.
 * - Siyahıda istifadəçinin indiki adı göstərilir (ad dəyişibsə — yenisi); istifadəçi
 *   silinibsə — yazılmış ad və «istifadəçi silinib».
 * - Köhnə saytdan gələn xəbərlərdə müəllif WordPress-dəki addır (head_meta: author).
 * - «Son dəyişiklik» yalnız saxlanan məlumat həqiqətən dəyişəndə yenilənir — yazını
 *   açıb heç nəyə toxunmadan yadda saxlayan «son redaktə edən» olmur.
 * - Yüklənən fayllar: storage/uploads-by.json (yol => eyni quruluş). Fayl zibil
 *   qutusuna düşəndə iz onunla gedir, bərpa olunanda geri qayıdır.
 */

/** Müqayisədə nəzərə alınmayan sahələr: vaxt və izlərin özü */
const AUTHOR_SKIP = ['modified', 'created_by', 'modified_by'];

/** Daxil olmuş istifadəçinin izi (indiki vaxtla); giriş yoxdursa boş */
function admin_author_stamp(): array
{
    $user = admin_user();
    if ($user === null) {
        return [];
    }
    return [
        'id'    => (int) $user['id'],
        'since' => (string) ($user['created'] ?? ''),
        'name'  => trim((string) ($user['name'] ?? '')),
        'at'    => date('Y-m-d\TH:i:s'),
    ];
}

/** Müqayisə üçün: açarlar sıralanır, skalyarlar mətnə çevrilir (1 və '1' eyni sayılır) */
function admin_author_norm($value)
{
    if (is_array($value)) {
        ksort($value);
        return array_map('admin_author_norm', $value);
    }
    if (is_bool($value)) {
        return $value ? '1' : '';
    }
    return (string) $value;
}

/** Saxlanan məlumat dəyişibmi (vaxt və izlərdən başqa) */
function admin_author_changed(array $now, array $before): bool
{
    foreach (AUTHOR_SKIP as $key) {
        unset($now[$key], $before[$key]);
    }
    return admin_author_norm($now) !== admin_author_norm($before);
}

/**
 * Yadda saxlanan sətrə izləri yazır.
 *
 * Yeni qeyd ($prev boşdur): «əlavə edib» və «son dəyişiklik» — daxil olmuş istifadəçi.
 * Mövcud qeyd: «əlavə edib» olduğu kimi qalır (köhnə, izsiz qeydə yazılmır — onu bu
 * istifadəçi əlavə etməyib); «son dəyişiklik» yalnız məlumat dəyişibsə və ya
 * $alsoChanged (sətirdən kənarda dəyişiklik) olduqda yenilənir.
 */
function admin_author_touch(array $row, ?array $prev, bool $alsoChanged = false): array
{
    $stamp = admin_author_stamp();
    if (!$prev) {
        unset($row['created_by'], $row['modified_by']);
        if ($stamp) {
            $row['created_by']  = $stamp;
            $row['modified_by'] = $stamp;
        }
        return $row;
    }

    foreach (['created_by', 'modified_by'] as $key) {
        if (isset($prev[$key]) && is_array($prev[$key])) {
            $row[$key] = $prev[$key];
        } else {
            unset($row[$key]);
        }
    }
    if ($stamp && ($alsoChanged || admin_author_changed($row, $prev))) {
        $row['modified_by'] = $stamp;
    }
    return $row;
}

/** Bu sorğuda sətrə yeni «son dəyişiklik» izi yazılıbmı */
function admin_author_touched(array $row, ?array $prev): bool
{
    return ($row['modified_by'] ?? null) !== ($prev['modified_by'] ?? null);
}

/**
 * Dəyişiklik yalnız sətirdən kənardadır (ingiliscə tərcümə) — «son dəyişiklik»
 * izini sətrə ayrıca yazır.
 */
function admin_author_mark(string $name, int $id, string $comment): void
{
    $stamp = admin_author_stamp();
    if (!$stamp) {
        return;
    }
    $rows = data_load($name, true);
    foreach ($rows as $i => $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            $rows[$i]['modified_by'] = $stamp;
            store_save($name, $rows, $comment);
            return;
        }
    }
}

/* ---------------------------------------------------------------- göstərmək */

/**
 * İzin göstərilən forması:
 *   name — istifadəçinin indiki adı (hələ varsa), yoxsa yazılmış ad
 *   at   — nə vaxt (ISO); origin — 'user' (indiki istifadəçi) və ya 'gone' (silinib);
 *   key  — süzgəc üçün açar
 */
function admin_author_view(array $stamp): array
{
    $id   = (int) ($stamp['id'] ?? 0);
    $user = $id > 0 ? admin_user_get($id) : null;
    $at   = (string) ($stamp['at'] ?? '');
    if ($user !== null && (string) ($user['created'] ?? '') === (string) ($stamp['since'] ?? '')) {
        return ['name' => admin_user_label($user), 'at' => $at, 'origin' => 'user', 'key' => 'u' . $id];
    }
    return ['name' => admin_author_gone_name($id, $stamp), 'at' => $at, 'origin' => 'gone', 'key' => 'd' . $id];
}

/** Silinmiş istifadəçinin adı: silinəndə yadda saxlanan (ad boş idisə — giriş adı), yoxsa izdəki ad */
function admin_author_gone_name(int $id, array $stamp = []): string
{
    $gone = admin_users_deleted()[(string) $id] ?? null;
    $name = is_array($gone) && (!$stamp || (string) ($gone['since'] ?? '') === (string) ($stamp['since'] ?? ''))
        ? trim((string) ($gone['label'] ?? ''))
        : '';
    if ($name === '') {
        $name = trim((string) ($stamp['name'] ?? ''));
    }
    return $name !== '' ? $name : 'Silinmiş istifadəçi';
}

/**
 * Köhnə saytın (WordPress) müəllifləri — data/wp-authors.php:
 * növ (posts, kitabxana, itkinlr, pages) => [id => ad], 'uploads' => [yol => ad].
 * WordPress bazasının nüsxəsindən bir dəfə yaradılıb.
 */
function admin_wp_authors(): array
{
    static $map = null;
    if ($map === null) {
        $file = dirname(__DIR__, 2) . '/data/wp-authors.php';
        $map  = is_file($file) ? (array) include $file : [];
    }
    return $map;
}

/** Qeyd köhnə saytdan gəlib (panelin əlavə etdiyi qeydlərdə schema boşdur) */
function admin_row_is_old(array $row): bool
{
    return trim((string) ($row['schema'] ?? '')) !== '';
}

/** Köhnə saytdakı (WordPress) müəllif: data/wp-authors.php, xəbərlərdə həm də <meta name="author"> */
function admin_author_legacy(array $row, string $type): string
{
    if (!admin_row_is_old($row)) {
        return '';
    }
    $name = trim((string) (admin_wp_authors()[$type][(int) ($row['id'] ?? 0)] ?? ''));
    if ($name !== '') {
        return $name;
    }
    foreach ((array) ($row['head_meta'] ?? []) as $tag) {
        if (is_array($tag) && ($tag[0] ?? '') === 'n' && ($tag[1] ?? '') === 'author') {
            return trim((string) ($tag[2] ?? ''));
        }
    }
    return '';
}

/**
 * Qeydi kim əlavə edib. origin:
 *   user / gone — paneldə əlavə olunub (istifadəçi var / silinib)
 *   wp          — köhnə saytda, müəllif məlumdur (WordPress)
 *   old         — köhnə saytdan gəlib, orada müəllif qeyd olunmurdu (kateqoriyalar)
 *   none        — paneldə bu qeyd aparılmağa başlamazdan əvvəl əlavə olunub
 */
function admin_row_author(array $row, string $type): array
{
    if (!empty($row['created_by']) && is_array($row['created_by'])) {
        return admin_author_view($row['created_by']);
    }
    $wp = admin_author_legacy($row, $type);
    if ($wp !== '') {
        return ['name' => $wp, 'at' => '', 'origin' => 'wp', 'key' => 'wp-' . (store_slug($wp) ?: 'x')];
    }
    return admin_row_is_old($row)
        ? ['name' => '', 'at' => '', 'origin' => 'old', 'key' => 'old']
        : ['name' => '', 'at' => '', 'origin' => 'none', 'key' => 'none'];
}

/** Qeydi son dəyişən (izi yoxdursa null) */
function admin_row_editor(array $row): ?array
{
    return !empty($row['modified_by']) && is_array($row['modified_by']) ? admin_author_view($row['modified_by']) : null;
}

/** «28 Sentyabr 2026, 15:10» */
function admin_author_when(string $iso): string
{
    return $iso !== '' && strtotime($iso) ? az_date($iso) . ', ' . substr($iso, 11, 5) : '';
}

/** Adı olmayan hallar üçün izah (siyahıda «—»-nin üstündə, formada mətn kimi) */
const AUTHOR_UNKNOWN = [
    'old'  => 'Köhnə saytda bunun müəllifi qeyd olunmurdu',
    'none' => 'Məlum deyil — panel müəllifi yazmağa başlamazdan əvvəl əlavə olunub',
];

/** Siyahıdakı «Əlavə edib» xanası */
function admin_author_cell(array $row, string $type): string
{
    return admin_author_cell_view(admin_row_author($row, $type));
}

/** Görünüşdən xana: ad + kiçik qeyd (köhnə sayt / istifadəçi silinib) */
function admin_author_cell_view(array $a): string
{
    if ($a['name'] === '') {
        return '<span class="author author--none" title="' . e(AUTHOR_UNKNOWN[$a['origin']] ?? '') . '">—</span>';
    }
    $title = $a['at'] !== '' ? ' title="' . e(admin_author_when($a['at'])) . '"' : '';
    $html  = '<span class="author' . ($a['origin'] === 'gone' ? ' author--gone' : '') . '"' . $title . '>' . e($a['name']) . '</span>';
    if ($a['origin'] === 'wp') {
        $html .= '<span class="table__sub" title="Köhnə saytdakı (WordPress) müəllif">köhnə sayt</span>';
    } elseif ($a['origin'] === 'gone') {
        $html .= '<span class="table__sub">istifadəçi silinib</span>';
    }
    return $html;
}

/** Bir sətir: «Əlavə edib: Ali Məmmədov · 28 Sentyabr 2026, 15:10» */
function admin_author_line(string $label, array $view): string
{
    $row = '<div class="authorship__row"><span class="authorship__label">' . e($label) . '</span>';
    if ($view['name'] === '') {
        return $row . '<span class="authorship__when">' . e(AUTHOR_UNKNOWN[$view['origin']] ?? 'məlum deyil') . '</span></div>';
    }
    $when = $view['origin'] === 'wp' ? 'köhnə saytda (WordPress)' : admin_author_when($view['at']);
    return $row
        . '<span class="authorship__who' . ($view['origin'] === 'gone' ? ' author--gone' : '') . '">' . e($view['name'])
        . ($view['origin'] === 'gone' ? ' <small>(istifadəçi silinib)</small>' : '') . '</span>'
        . ($when !== '' ? '<span class="authorship__when">' . e($when) . '</span>' : '')
        . '</div>';
}

/** «Son dəyişiklik» sətri; iz yoxdursa — bu qeyd aparılandan bəri dəyişdirilməyib */
function admin_editor_line(?array $editor): string
{
    if ($editor !== null) {
        return admin_author_line('Son dəyişiklik', $editor);
    }
    return '<div class="authorship__row"><span class="authorship__label">Son dəyişiklik</span>'
        . '<span class="authorship__when">panel bunu qeyd etməyə başlayandan bəri dəyişdirilməyib</span></div>';
}

/** Redaktə formasındakı blok: kim əlavə edib, kim son dəyişib */
function admin_author_box(array $row, string $type): string
{
    return '<div class="authorship">' . admin_author_line('Əlavə edib', admin_row_author($row, $type))
        . admin_editor_line(admin_row_editor($row)) . '</div>';
}

/** Formanın yan sütunu üçün kart. Başlıq «Müəllif» deyil — kitabın öz müəllif sahəsi ilə qarışmasın */
function admin_author_card(array $row, string $type): string
{
    return '<div class="card"><div class="card__head">Kim əlavə edib</div><div class="card__body">'
        . admin_author_box($row, $type) . '</div></div>';
}

/* ---------------------------------------------------------------- menyular, əlaqə */

/**
 * Sətri olmayan bölmələrin (menyular, əlaqə məlumatları) son dəyişikliyi —
 * storage/section-edits.json: açar => iz.
 */
function admin_section_mark(string $key): void
{
    $stamp = admin_author_stamp();
    if (!$stamp) {
        return;
    }
    storage_json('section-edits.json', static function (array $d) use ($key, $stamp) {
        $d[$key] = $stamp;
        return $d;
    });
}

/** Bölmənin yuxarısındakı «Son dəyişiklik» zolağı */
function admin_section_bar(string $key): string
{
    $stamp = storage_json('section-edits.json')[$key] ?? null;
    return '<div class="page-edited"><div class="authorship">'
        . admin_editor_line(is_array($stamp) ? admin_author_view($stamp) : null) . '</div></div>';
}

/* ---------------------------------------------------------------- süzgəc */

/**
 * Süzgəcin seçimləri: açar => [seçimin mətni, say, ad]. Sıra: indiki istifadəçilər
 * (ada görə), silinmiş istifadəçilər, köhnə saytın müəllifləri, naməlum.
 */
function admin_author_options(array $rows, string $type): array
{
    $groups = ['user' => [], 'gone' => [], 'wp' => [], 'old' => [], 'none' => []];
    foreach ($rows as $row) {
        $a   = admin_row_author($row, $type);
        $key = $a['key'];
        $g   = $a['origin'];
        if (!isset($groups[$g][$key])) {
            $groups[$g][$key] = [admin_author_option_label($a), 0, admin_author_plain($a)];
        }
        $groups[$g][$key][1]++;
    }
    foreach ($groups as $g => $list) {
        uasort($list, static function (array $x, array $y) {
            return strcmp(mb_strtolower($x[0]), mb_strtolower($y[0]));
        });
        $groups[$g] = $list;
    }
    return $groups['user'] + $groups['gone'] + $groups['wp'] + $groups['old'] + $groups['none'];
}

/** Görünüşün adı (adı yoxdursa — izah) */
function admin_author_plain(array $a): string
{
    if ($a['name'] !== '') {
        return $a['name'];
    }
    return $a['origin'] === 'old' ? 'Köhnə sayt (müəllif qeyd olunmurdu)' : 'Məlum deyil';
}

/** Süzgəcdəki seçimin mətni */
function admin_author_option_label(array $a): string
{
    $name = admin_author_plain($a);
    if ($a['origin'] === 'gone' && $name !== 'Silinmiş istifadəçi') {
        return $name . ' (silinib)';
    }
    if ($a['origin'] === 'wp') {
        return $name . ' (köhnə sayt)';
    }
    return $name;
}

/**
 * Süzgəc açarının adı — siyahıda bu açarla qeyd qalmayanda (köhnə keçid):
 * indiki istifadəçi, silinmiş istifadəçi, köhnə saytın müəllifi və ya naməlum.
 */
function admin_author_key_view(string $key): array
{
    if (preg_match('/^u([0-9]+)$/', $key, $m) && ($user = admin_user_get((int) $m[1])) !== null) {
        return ['name' => admin_user_label($user), 'at' => '', 'origin' => 'user', 'key' => $key];
    }
    if (preg_match('/^[ud]([0-9]+)$/', $key, $m)) {
        return ['name' => admin_author_gone_name((int) $m[1]), 'at' => '', 'origin' => 'gone', 'key' => $key];
    }
    if (strpos($key, 'wp-') === 0) {
        foreach (admin_wp_authors() as $group) {
            foreach ((array) $group as $name) {
                if ('wp-' . store_slug((string) $name) === $key) {
                    return ['name' => (string) $name, 'at' => '', 'origin' => 'wp', 'key' => $key];
                }
            }
        }
        return ['name' => substr($key, 3), 'at' => '', 'origin' => 'wp', 'key' => $key];
    }
    return ['name' => '', 'at' => '', 'origin' => $key === 'old' ? 'old' : 'none', 'key' => $key];
}

/** URL-dən gələn süzgəc açarı (düzgün formatda deyilsə — boş) */
function admin_author_filter_key($raw): string
{
    return is_string($raw) && preg_match('/^(u[0-9]{1,9}|d[0-9]{1,9}|wp-[a-z0-9-]{1,80}|old|none)$/', $raw) ? $raw : '';
}

/* ---------------------------------------------------------------- yüklənən fayllar */

/** Faylları kim yükləyib: yol => iz */
function admin_uploads_by(): array
{
    return storage_json('uploads-by.json');
}

/**
 * Faylı kim yükləyib: paneldən — iz ($stamp və ya storage/uploads-by.json), köhnə
 * saytda — WordPress müəllifi (data/wp-authors.php). Məlum deyilsə null.
 */
function admin_upload_author(string $path, ?array $uploadsBy = null, array $stamp = []): ?array
{
    if (!$stamp) {
        $uploadsBy = $uploadsBy ?? admin_uploads_by();
        $stamp     = isset($uploadsBy[$path]) && is_array($uploadsBy[$path]) ? $uploadsBy[$path] : [];
    }
    // köhnə saytın faylı zibil qutusundan başqa adla bərpa olunub: ['wp' => ad]
    $wp = trim((string) ($stamp['wp'] ?? ''));
    if ($stamp && $wp === '') {
        return admin_author_view($stamp);
    }
    if ($wp === '') {
        $wp = trim((string) (admin_wp_authors()['uploads'][$path] ?? ''));
    }
    return $wp !== '' ? ['name' => $wp, 'at' => '', 'origin' => 'wp', 'key' => 'wp-' . (store_slug($wp) ?: 'x')] : null;
}

/** Yeni yüklənən faylları daxil olmuş istifadəçinin adına yazır */
function admin_uploads_by_add(array $paths): void
{
    $stamp = admin_author_stamp();
    if (!$stamp || !$paths) {
        return;
    }
    storage_json('uploads-by.json', static function (array $d) use ($paths, $stamp) {
        foreach ($paths as $path) {
            $d[(string) $path] = $stamp;
        }
        return $d;
    });
}

/** Faylın izini siyahıdan götürür və qaytarır (fayl zibil qutusuna gedir) */
function admin_uploads_by_take(string $path): array
{
    $taken = [];
    storage_json('uploads-by.json', static function (array $d) use ($path, &$taken) {
        if (isset($d[$path]) && is_array($d[$path])) {
            $taken = $d[$path];
        }
        unset($d[$path]);
        return $d;
    });
    return $taken;
}

/** Bərpa olunan faylın izini qaytarır (fayl başqa adla bərpa olunubsa — həmin adla) */
function admin_uploads_by_put(string $path, array $stamp): void
{
    if (!$stamp) {
        return;
    }
    storage_json('uploads-by.json', static function (array $d) use ($path, $stamp) {
        $d[$path] = $stamp;
        return $d;
    });
}
