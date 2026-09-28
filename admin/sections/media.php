<?php
/**
 * Qalereya bölməsi / media library.
 * Fayllar uploads/ qovluğunda saxlanılır, yüklənənlər uploads/YYYY/MM/ altına düşür.
 *
 * Yuxarıdakı süzgəc (Hamısı / Şəkillər / Videolar / PDF), axtarış və səhifələmə
 * birlikdə işləyir. Silinən fayl birdəfəlik yox olmur — zibil qutusuna düşür
 * (admin/inc/trash.php) və 30 gün ərzində bərpa edilə bilər.
 *
 * Saytda istifadə olunan faylı da silmək olar, amma yalnız harada istifadə
 * olunduğunu (xəbər, kitab, itkin, səhifə …) göstərən təsdiqdən sonra.
 * Dizayndakı fayllar — loqo, fon, səhifələrin orijinal şəkilləri (şablonlarda,
 * CSS-də, sabit hissələrdə yazılıb) — silinmir: onları paneldən geri qaytarmaq olmur.
 */

const MEDIA_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4'];
const MEDIA_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
const MEDIA_MAX = 20971520;   // 20 MB
const MEDIA_PER_PAGE = 60;

$root = dirname(__DIR__, 2);

require_once dirname(__DIR__) . '/inc/upload_check.php';

/*
 * Seçim rejimi: formadakı şəkil/video sahəsi kitabxananı pəncərədə açır.
 * Bu rejimdə “Yolu köçür” və “Sil” əvəzinə “Seç” düyməsi olur.
 *   picker=1        seçim rejimi
 *   kind=video|pdf  yalnız videolar və ya PDF-lər (default: yalnız şəkillər)
 *   json=1          yükləmə nəticəsi JSON kimi (formadakı “PDF yüklə” düyməsi üçün)
 *   fragment=1      çərçivəsiz — yalnız səhifənin içi (JS pəncərəyə qoyur)
 */
$pick = (string) ($_GET['picker'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $pick)) {
    $pick = '';
}
$kind = $pick !== '' && in_array($_GET['kind'] ?? '', ['video', 'pdf'], true) ? (string) $_GET['kind'] : 'image';
$keep = $pick !== '' ? ['picker' => $pick] + ($kind !== 'image' ? ['kind' => $kind] : []) : [];

/** Hər seçim növü üçün icazə verilən uzantılar */
const MEDIA_KIND_EXT = [
    'image' => MEDIA_IMAGE_EXT,
    'video' => ['mp4'],
    'pdf'   => ['pdf'],
];

/** Qalereyanın süzgəci (seçim pəncərəsində yoxdur): növ => [ad, uzantılar] */
const MEDIA_TYPES = [
    'image' => ['Şəkillər', MEDIA_IMAGE_EXT],
    'video' => ['Videolar', ['mp4']],
    'pdf'   => ['PDF', ['pdf']],
];

$fragment = $pick !== '' && isset($_GET['fragment']);
$notice   = '';

// Süzgəc: ?type=image|video|pdf (boş — hamısı)
$type = $pick === '' && is_string($_GET['type'] ?? null) && isset(MEDIA_TYPES[$_GET['type']]) ? (string) $_GET['type'] : '';


/* ---------------------------------------------------------------- yükləmə */

/**
 * Faylları qəbul edir, saxlananların yollarını qaytarır (uploads/…).
 * Alınmayanlar səbəbi ilə birlikdə $failed-ə yazılır.
 * $only verilibsə yalnız həmin uzantılar qəbul olunur (məsələn yalnız PDF).
 */
function media_upload(string $root, array &$failed, array $only = []): array
{
    $files = $_FILES['files'] ?? null;
    if (!$files || !is_array($files['name'])) {
        return [];
    }

    $dir = 'uploads/' . date('Y') . '/' . date('m');
    $abs = $root . '/' . $dir;
    if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) {
        $failed[] = $dir . ' (qovluq yaradıla bilmədi)';
        return [];
    }

    $saved = [];
    foreach ($files['name'] as $i => $name) {
        if ((int) $files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        if (!in_array($ext, MEDIA_EXT, true) || ($only && !in_array($ext, $only, true))) {
            $failed[] = $name . ' (icazə verilməyən format)';
            continue;
        }
        if ((int) $files['size'][$i] > MEDIA_MAX) {
            $failed[] = $name . ' (çox böyükdür)';
            continue;
        }
        // Uzantı kifayət deyil: məzmun da həmin formatda olmalıdır, SVG-də skript olmamalıdır
        $problem = upload_content_problem((string) $files['tmp_name'][$i], $ext);
        if ($problem !== null) {
            $failed[] = $name . ' (' . $problem . ')';
            continue;
        }

        $base = store_slug(pathinfo((string) $name, PATHINFO_FILENAME)) ?: 'fayl';
        $file = $base . '.' . $ext;
        $n = 2;
        while (file_exists($abs . '/' . $file)) {
            $file = $base . '-' . $n++ . '.' . $ext;
        }

        if (@move_uploaded_file($files['tmp_name'][$i], $abs . '/' . $file)) {
            @chmod($abs . '/' . $file, 0644);
            $saved[] = $dir . '/' . $file;
        } else {
            $failed[] = $name;
        }
    }

    return $saved;
}

if ($action === 'upload' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $saved  = [];
    $failed = [];
    if (!admin_token_ok()) {
        $msg = 'Forma köhnəlib. Səhifəni yeniləyin.';
        $ok  = false;
    } else {
        // Seçim pəncərəsindən gəlirsə yalnız həmin növ qəbul olunur
        $saved = media_upload($root, $failed, $pick !== '' ? MEDIA_KIND_EXT[$kind] : []);
        admin_uploads_by_add($saved);   // kim yükləyib — qalereyada göstərilir
        $ok    = $saved !== [];
        $msg   = $ok ? count($saved) . ' fayl yükləndi.' : 'Fayl yüklənmədi.';
        if ($failed) {
            $msg .= ' Alınmayanlar: ' . implode(', ', array_slice($failed, 0, 4));
        }
    }

    // Formadakı “yüklə” düyməsi nəticəni JSON kimi gözləyir
    if (!empty($_GET['json'])) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok'      => $ok,
            'paths'   => $saved,
            'message' => $msg,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Pəncərədə açılıbsa yönləndirmirik — siyahını elə burada təzələyib qaytarırıq
    if ($fragment) {
        $notice = $msg;
    } else {
        admin_redirect(['section' => 'media'] + $keep + ($type !== '' ? ['type' => $type] : []), $msg, $ok ? 'ok' : 'error');
    }
}

/* ---------------------------------------------------------------- istifadə */

/** Səhifə sahələrinin prefiksi (data/page-texts.php: “home.img.1”) => səhifənin adı */
const MEDIA_PAGE_NAMES = [
    'home'       => 'Ana səhifə',
    'haqqimizda' => 'Haqqımızda',
    'elaqe'      => 'Əlaqə',
    'senedler'   => 'Beynəlxalq sənədlər',
    'qanun'      => 'Milli qanunvericilik',
    'sekiller'   => 'Şəkillər',
    'kitabxana'  => 'Kitabxana',
    'xeberler'   => 'Xəbərlər',
];

/** Məzmun faylları (data/<ad>.php və <ad>-en.php): ad => [yerin növü, başlıq sahəsi] */
const MEDIA_CONTENT = [
    'posts'      => ['Xəbər', 'title'],
    'kitabxana'  => ['Kitab', 'title'],
    'itkinlr'    => ['İtkin', 'title'],
    'categories' => ['Kateqoriya', 'name'],
    'pages'      => ['Səhifə', 'title'],
];

/** Mətndəki fayl istinadları: “2023/11/ad.jpg” kimi açarlar */
function media_refs(string $text): array
{
    // JSON-da yollar “2023\/11\/…” kimi yazılır — adi yola çeviririk
    $text = str_replace('\\/', '/', $text);
    $name = '[^"\'\s<>()\\\\,?\#]+?\.(?:' . implode('|', MEDIA_EXT) . ')(?![A-Za-z0-9.])';
    $refs = [];
    // uploads/… ilə başlayan istənilən yol (il/ay qovluğunda olmayanlar da)
    if (preg_match_all('#uploads/(' . $name . ')#i', $text, $m)) {
        foreach ($m[1] as $ref) {
            $refs[$ref] = true;
        }
    }
    // Elementor CSS-də fon şəkilləri nisbi yazılıb: ../../2023/11/ad.webp
    if (preg_match_all('#\.\./(\d{4}/\d{2}/' . $name . ')#i', $text, $m)) {
        foreach ($m[1] as $ref) {
            $refs[$ref] = true;
        }
    }
    return array_keys($refs);
}

/** Qeydin (massivin) bütün mətn dəyərləri bir mətndə */
function media_row_text($value): string
{
    if (!is_array($value)) {
        return is_string($value) ? $value : '';
    }
    $text = '';
    array_walk_recursive($value, static function ($v) use (&$text) {
        if (is_string($v)) {
            $text .= $v . "\n";
        }
    });
    return $text;
}

/** Dizayn faylının oxunaqlı adı */
function media_design_label(string $rel): string
{
    if ($rel === 'data/page-fields.php') {
        return 'Səhifələrin orijinal şəkilləri (data/page-fields.php)';
    }
    if (strpos($rel, 'templates/') === 0) {
        return 'Şablon: ' . $rel;
    }
    if (substr($rel, -4) === '.css') {
        return 'Dizaynın CSS faylı: ' . $rel;
    }
    return 'Saytın sabit hissəsi: ' . $rel;
}

/**
 * Saytda istinad olunan fayllar və harada:
 *   “2023/11/ad.jpg” => ['Xəbər «…»' => false, 'Şablon: templates/home.body.php' => true, …]
 * true — dizayn (şablon, CSS, sabit hissələr, səhifələrin orijinal sahələri): belə fayl silinmir.
 *
 * Məzmun (xəbər, kitab, itkin, kateqoriya, səhifə — AZ və EN) qeyd-qeyd oxunur ki,
 * yer adı ilə göstərilsin. Qalan fayllar bir dəfə oxunur və içindəki bütün yollar
 * yığılır. Zibil qutusundakı qeydlər sayılmır — onlar saytda görünmür.
 */
function media_usage(string $root): array
{
    static $used = null;
    if ($used !== null) {
        return $used;
    }
    $used = [];
    $add = static function (string $text, string $place, bool $design) use (&$used) {
        foreach (media_refs($text) as $ref) {
            $used[$ref][$place] = !empty($used[$ref][$place]) || $design;
        }
    };

    $seen = [];   // qeyd-qeyd oxunmuş data faylları
    foreach (MEDIA_CONTENT as $name => [$label, $field]) {
        $titles = [];
        foreach (data_load($name) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row[$field] ?? ''));
            $title = $title !== '' ? $title : '#' . ($row['id'] ?? '?');
            $titles[(string) ($row['id'] ?? '')] = $title;
            $add(media_row_text($row), $label . ' «' . $title . '»', false);
        }
        foreach (data_load($name . '-en') as $id => $row) {
            $add(media_row_text($row), $label . ' «' . ($titles[(string) $id] ?? '#' . $id) . '» (EN)', false);
        }
        $seen[$name] = $seen[$name . '-en'] = true;
    }
    // Səhifələrdə paneldən dəyişdirilmiş şəkillər, qalereyalar, videolar
    foreach (['page-texts' => '', 'page-texts-en' => ' (EN)'] as $name => $suffix) {
        foreach (data_load($name) as $key => $value) {
            $page = explode('.', (string) $key)[0];
            $add(media_row_text($value), 'Səhifə «' . (MEDIA_PAGE_NAMES[$page] ?? $page) . '»' . $suffix, false);
        }
        $seen[$name] = true;
    }
    // Arxiv səhifələrinin (itkinlər, kitabxana) paylaşma şəkli
    foreach (['archives', 'archives-en'] as $name) {
        foreach (data_load($name) as $key => $value) {
            $add(media_row_text($value), 'Arxiv səhifəsi «' . $key . '»', false);
        }
        $seen[$name] = true;
    }

    // Köhnə saytın müəllifləri siyahısı (data/wp-authors.php) faylların adlarını sadalayır,
    // amma onları istifadə etmir — dizayn sayılmasın, yoxsa həmin fayllar silinməz olardı
    $seen['wp-authors'] = true;

    // Qalan hər şey dizayndır
    $files = array_merge(
        glob($root . '/data/*.php') ?: [],
        glob($root . '/templates/*.php') ?: [],
        glob($root . '/templates/partials/*.php') ?: [],
        glob($root . '/inc/*.php') ?: [],
        glob($root . '/admin/inc/*.php') ?: [],
        glob($root . '/admin/views/*.php') ?: [],
        glob($root . '/assets/css/*.css') ?: [],
        glob($root . '/assets/js/*.js') ?: [],
        glob($root . '/uploads/elementor/css/*.css') ?: [],
        [$root . '/config.php']
    );
    foreach ($files as $file) {
        $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
        if (strpos($rel, 'data/') === 0 && isset($seen[basename($rel, '.php')])) {
            continue;
        }
        $add((string) @file_get_contents($file), media_design_label($rel), true);
    }
    return $used;
}

/** “uploads/2023/11/ad.jpg” -> “2023/11/ad.jpg” (istifadə siyahısının açarı) */
function media_ref(string $path): string
{
    return strpos($path, 'uploads/') === 0 ? substr($path, 8) : $path;
}

/** Faylın istifadə yerləri: ['yer' => dizayndırmı] */
function media_places(string $root, string $path): array
{
    return media_usage($root)[media_ref($path)] ?? [];
}

/**
 * Təsdiqin izi: silinəcək istifadədəki fayllar və onların yerləri.
 * Təsdiq göstəriləndən sonra fayl başqa yerdə də istifadə olunubsa,
 * iz dəyişir və təsdiq yenidən (yeni siyahı ilə) soruşulur.
 */
function media_sig(array $placesByPath): string
{
    ksort($placesByPath);
    return substr(sha1((string) json_encode($placesByPath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 20);
}

/** Tək faylın «Sil» təsdiqi: harada istifadə olunur */
function media_confirm_text(string $name, array $labels, bool $image): string
{
    $list = array_slice($labels, 0, 8);
    return '“' . $name . '” saytda istifadə olunur:' . "\n• " . implode("\n• ", $list)
        . (count($labels) > 8 ? "\n• … və daha " . (count($labels) - 8) . ' yer' : '')
        . "\n\nSilsəniz, bu yerlərdə " . ($image ? 'şəkil görünməyəcək' : 'fayl açılmayacaq') . '. '
        . 'Fayl zibil qutusuna düşür və ' . TRASH_DAYS . ' gün ərzində bərpa edilə bilər.' . "\n\nYenə də silinsin?";
}

/** Dizayn faylının «Sil» düyməsi üzərindəki izah ($places: yer => dizayndırmı) */
function media_lock_text(array $places): string
{
    $design = array_keys(array_filter($places));
    $other  = count($places) - count($design);
    return 'Bu fayl saytın dizaynında istifadə olunur: ' . implode('; ', array_slice($design, 0, 3))
        . (count($design) > 3 ? ' …' : '')
        . ($other > 0 ? ' (həmçinin ' . $other . ' xəbər, kitab və ya səhifədə)' : '') . '. '
        . 'Loqo, fon və səhifələrin orijinal şəkilləri şablonlarda və CSS-də yazılıb — silinsə, saytın görünüşü '
        . 'pozular və bunu paneldən düzəltmək olmaz. Ona görə belə fayllar silinmir.';
}

/* ---------------------------------------------------------------- silmək */

/*
 * Fayl birdəfəlik silinmir: storage/trash/ altına köçürülür və zibil qutusunda
 * 30 gün saxlanılır. Saytda istifadə olunan fayl yalnız təsdiqdən sonra silinir
 * (təsdiqdə harada istifadə olunduğu göstərilir), dizayn faylı isə heç silinmir.
 *   action=delete  tək fayl (path)
 *   action=bulk    seçilənlər (paths[])
 */
if (($action === 'delete' || $action === 'bulk') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $bq    = trim((string) ($_POST['q'] ?? ''));
    $bp    = max(1, (int) ($_POST['p'] ?? 1));
    $btype = is_string($_POST['type'] ?? null) && isset(MEDIA_TYPES[$_POST['type']]) ? (string) $_POST['type'] : '';
    $back  = ['section' => 'media'] + array_filter(['type' => $btype, 'q' => $bq, 'p' => $bp],
        static function ($v) { return $v !== '' && $v !== 1; });

    if (!admin_token_ok()) {
        admin_redirect($back, 'Forma köhnəlib. Səhifəni yeniləyin.', 'error');
    }

    $paths = $action === 'bulk' ? (array) ($_POST['paths'] ?? []) : [post_str('path')];
    $paths = array_values(array_unique(array_filter(array_map(static function ($p) {
        return is_string($p) ? trim($p) : '';
    }, $paths), 'strlen')));
    if (!$paths) {
        admin_redirect($back, 'Heç bir fayl seçilməyib.', 'error');
    }

    $ok    = [];   // silinə bilən fayllar
    $needs = [];   // onlardan saytda istifadə olunanlar: yol => yerlər
    $skip  = [];   // silinməyənlər (səbəbi ilə)
    foreach (array_slice($paths, 0, 300) as $path) {
        if (!page_path_ok($path) || strpos($path, 'uploads/elementor/') === 0
            || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), MEDIA_EXT, true) || !is_file($root . '/' . $path)) {
            $skip[] = basename($path) . ' (tapılmadı)';
            continue;
        }
        $places = media_places($root, $path);
        if (in_array(true, $places, true)) {
            $skip[] = basename($path) . ' (saytın dizaynında istifadə olunur)';
            continue;
        }
        if ($places) {
            $needs[$path] = array_keys($places);
        }
        $ok[] = $path;
    }

    // Tək fayl silinmirsə — səbəbi ilə geri
    if (!$ok) {
        admin_redirect($back, count($paths) === 1
            ? ($skip && strpos($skip[0], 'dizayn') !== false
                ? '“' . basename($paths[0]) . '” saytın dizaynında istifadə olunur (loqo, fon, səhifənin orijinal şəkli), ona görə silinmir.'
                : 'Fayl tapılmadı.')
            : 'Heç bir fayl silinmədi: ' . implode(', ', array_slice($skip, 0, 6)) . (count($skip) > 6 ? ' …' : ''), 'error');
    }

    /*
     * İstifadədəki fayl üçün açıq təsdiq lazımdır. Kartdakı «Sil» düyməsi onu
     * brauzerin pəncərəsində soruşur və izi göndərir; iz yoxdursa (seçilənləri
     * silmək, JS-siz brauzer) və ya bu arada dəyişibsə — təsdiq səhifəsi.
     */
    if ($needs && (string) ($_POST['confirm'] ?? '') !== media_sig($needs)) {
        $free = array_values(array_diff($ok, array_keys($needs)));
        admin_shell_start('media', 'Silməyi təsdiqləyin');
        ?>
<div class="card media-confirm">
	<div class="card__head"><?= count($needs) === 1 ? 'Bu fayl saytda istifadə olunur' : 'Bu fayllar saytda istifadə olunur' ?></div>
	<div class="card__body">
		<p style="margin-top:0">
			Silsəniz, aşağıdakı yerlərdə şəkil görünməyəcək (PDF və video açılmayacaq).
			Fayllar zibil qutusuna düşür və <?= TRASH_DAYS ?> gün ərzində oradan bərpa edilə bilər.
		</p>
		<ul class="media-confirm__list">
<?php foreach ($needs as $path => $labels): ?>
			<li>
				<strong><?= e(basename($path)) ?></strong> <span class="table__meta"><?= e($path) ?></span>
				<ul>
<?php   foreach ($labels as $label): ?>
					<li><?= e($label) ?></li>
<?php   endforeach; ?>
				</ul>
			</li>
<?php endforeach; ?>
		</ul>
<?php if ($free): ?>
		<p>Həmçinin istifadə olunmayan <?= count($free) ?> fayl silinəcək: <?= e(implode(', ', array_map('basename', array_slice($free, 0, 10)))) ?><?= count($free) > 10 ? ' …' : '' ?>.</p>
<?php endif; ?>
<?php if ($skip): ?>
		<p class="field__hint">Silinməyəcək: <?= e(implode(', ', array_slice($skip, 0, 10))) ?><?= count($skip) > 10 ? ' …' : '' ?>.</p>
<?php endif; ?>
		<form method="post" action="<?= e(admin_url(['section' => 'media', 'action' => 'bulk'])) ?>" class="actions">
			<?= admin_token_field() ?>
<?php foreach ($ok as $path): ?>
			<input type="hidden" name="paths[]" value="<?= e($path) ?>">
<?php endforeach; ?>
			<input type="hidden" name="confirm" value="<?= e(media_sig($needs)) ?>">
			<input type="hidden" name="q" value="<?= e($bq) ?>">
			<input type="hidden" name="p" value="<?= (int) $bp ?>">
			<input type="hidden" name="type" value="<?= e($btype) ?>">
			<button class="btn btn--danger" type="submit">Bəli, zibil qutusuna köçür (<?= count($ok) ?>)</button>
			<a class="btn" href="<?= e(admin_url($back)) ?>">Ləğv et</a>
		</form>
	</div>
</div>
        <?php
        admin_shell_end();
        return;
    }

    $done   = [];
    $failed = [];
    foreach ($ok as $path) {
        try {
            trash_move_file($path, $needs[$path] ?? []);
            $done[] = basename($path);
        } catch (RuntimeException $e) {
            $failed[] = basename($path) . ' (' . $e->getMessage() . ')';
        }
    }
    $failed = array_merge($failed, $skip);

    if (!$done) {
        admin_redirect($back, 'Silmək alınmadı: ' . implode(', ', array_slice($failed, 0, 4)), 'error');
    }
    $msg = count($done) === 1 ? trash_flash($done[0])
        : count($done) . ' fayl zibil qutusuna köçürüldü. ' . TRASH_DAYS . ' gün ərzində bərpa etmək olar.';
    if ($failed) {
        $msg .= ' Silinmədi: ' . implode(', ', array_slice($failed, 0, 4)) . (count($failed) > 4 ? ' …' : '') . '.';
    }
    admin_redirect($back, $msg);
}

/* ---------------------------------------------------------------- siyahı */

/** uploads/ altındakı bütün faylları yenidən köhnəyə toplayır */
function media_files(string $root): array
{
    $dir = $root . '/uploads';
    if (!is_dir($dir)) {
        return [];
    }

    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, MEDIA_EXT, true)) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        // Elementor-un öz keş qovluğunu göstərmirik
        if (strpos($rel, 'uploads/elementor/') === 0) {
            continue;
        }
        $out[] = ['path' => $rel, 'name' => $file->getFilename(), 'ext' => $ext, 'size' => $file->getSize(), 'time' => $file->getMTime()];
    }

    usort($out, static function (array $a, array $b) {
        return $b['time'] <=> $a['time'];
    });

    return $out;
}

$all = media_files($root);

// Seçim pəncərəsində yalnız sahəyə uyğun fayllar
if ($pick !== '') {
    $all = array_values(array_filter($all, static function (array $f) use ($kind) {
        return in_array($f['ext'], MEDIA_KIND_EXT[$kind], true);
    }));
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $all = array_values(array_filter($all, static function (array $f) use ($q) {
        return stripos($f['path'], $q) !== false;
    }));
}

// Növə görə süzgəc: saylar axtarışın nəticəsindən
$typeCounts = ['' => count($all)];
if ($pick === '') {
    foreach (MEDIA_TYPES as $t => [, $exts]) {
        $typeCounts[$t] = count(array_filter($all, static function (array $f) use ($exts) {
            return in_array($f['ext'], $exts, true);
        }));
    }
    if ($type !== '') {
        $exts = MEDIA_TYPES[$type][1];
        $all = array_values(array_filter($all, static function (array $f) use ($exts) {
            return in_array($f['ext'], $exts, true);
        }));
    }
}
$typeKeep = $type !== '' ? ['type' => $type] : [];

$page  = max(1, (int) ($_GET['p'] ?? 1));
$pages = max(1, (int) ceil(count($all) / MEDIA_PER_PAGE));
$page  = min($page, $pages);
$slice = array_slice($all, ($page - 1) * MEDIA_PER_PAGE, MEDIA_PER_PAGE);
$used  = $pick === '' ? media_usage($root) : [];

// Eyni markup həm tam səhifə, həm də pəncərə üçün lazımdır — bufere yığırıq
ob_start();
?>
<?php if ($notice !== ''): ?>
<div class="card"><div class="card__body" style="padding:12px 16px"><?= e($notice) ?></div></div>
<?php endif; ?>
<div class="card">
	<div class="card__body">
		<form method="post" action="<?= e(admin_url(['section' => 'media', 'action' => 'upload'] + $keep + $typeKeep)) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
			<?= admin_token_field() ?>
			<input class="input" type="file" name="files[]" multiple style="max-width:340px" required
			       accept="<?= $pick === '' ? '' : ['image' => 'image/*', 'video' => 'video/mp4', 'pdf' => 'application/pdf,.pdf'][$kind] ?>">
			<button class="btn btn--primary" type="submit">Yüklə</button>
			<span class="field__hint">JPG, PNG, WebP, GIF, SVG, PDF, MP4 — 20 MB-a qədər. Fayllar <code>uploads/<?= date('Y/m') ?>/</code> qovluğuna düşür.</span>
		</form>
	</div>
</div>

<div class="card">
	<div class="card__body">
<?php if ($pick === ''): ?>
		<nav class="filter-tabs" aria-label="Fayl növü">
			<a class="filter-tabs__tab<?= $type === '' ? ' is-active' : '' ?>" href="<?= e(admin_url(['section' => 'media'] + ($q !== '' ? ['q' => $q] : []))) ?>">Hamısı <span class="filter-tabs__n"><?= $typeCounts[''] ?></span></a>
<?php   foreach (MEDIA_TYPES as $t => [$tLabel]): ?>
			<a class="filter-tabs__tab<?= $type === $t ? ' is-active' : '' ?>" href="<?= e(admin_url(['section' => 'media', 'type' => $t] + ($q !== '' ? ['q' => $q] : []))) ?>"><?= e($tLabel) ?> <span class="filter-tabs__n"><?= (int) $typeCounts[$t] ?></span></a>
<?php   endforeach; ?>
		</nav>
<?php endif; ?>
		<form method="get" action="<?= e(admin_url()) ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
			<input type="hidden" name="section" value="media">
<?php foreach ($keep + $typeKeep as $k => $v): ?>
			<input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
<?php endforeach; ?>
			<input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Fayl adı ilə axtar…" style="max-width:320px">
			<button class="btn" type="submit">Axtar</button>
			<span class="field__hint"><?= count($all) ?> fayl</span>
<?php if ($pick === ''): ?>
			<span class="field__hint">· <span class="badge">İstifadədə</span> olan faylı silmək üçün təsdiq lazımdır ·
				<span class="badge badge--off">Dizayn</span> faylları (loqo, fon, səhifələrin orijinal şəkilləri) silinmir</span>
<?php endif; ?>
		</form>
	</div>
</div>

<?php if ($pick === '' && $slice): ?>
<form id="media-bulk" class="bulk-bar" method="post" action="<?= e(admin_url(['section' => 'media', 'action' => 'bulk'])) ?>">
	<?= admin_token_field() ?>
	<input type="hidden" name="q" value="<?= e($q) ?>">
	<input type="hidden" name="p" value="<?= (int) $page ?>">
	<input type="hidden" name="type" value="<?= e($type) ?>">
	<button class="btn btn--sm btn--danger" type="submit"
	        data-confirm="Seçilən fayllar zibil qutusuna köçürülsün? <?= TRASH_DAYS ?> gün ərzində bərpa etmək olar.">Seçilənləri sil</button>
	<span class="field__hint">Kartın küncündəki qutunu işarələyin. Seçilənlər arasında saytda istifadə olunan fayl varsa, silməzdən əvvəl harada istifadə olunduğu göstəriləcək.</span>
	<a class="bulk-bar__trash" href="<?= e(admin_url(['section' => 'trash', 'type' => 'media'])) ?>">Zibil qutusu →</a>
</form>
<?php endif; ?>

<div class="media-grid">
<?php $uploadsBy = admin_uploads_by(); ?>
<?php foreach ($slice as $file): ?>
<?php
    $places  = $pick === '' ? ($used[media_ref($file['path'])] ?? []) : [];
    $labels  = array_keys($places);
    $design  = in_array(true, $places, true);
    $isImage = in_array($file['ext'], MEDIA_IMAGE_EXT, true);
    // kim yükləyib (panel bunu yadda saxlamağa başlayandan sonra yüklənənlər üçün)
    $by      = admin_upload_author($file['path'], $uploadsBy);
?>
	<figure class="media-item">
		<div class="media-item__thumb">
<?php if ($isImage): ?>
			<img src="<?= e(asset($file['path'])) ?>" alt="" loading="lazy">
<?php elseif ($file['ext'] === 'mp4'): ?>
			<video src="<?= e(asset($file['path'])) ?>#t=0.5" preload="metadata" muted playsinline></video>
			<span class="media-item__kind">▶ Video</span>
<?php else: ?>
			<span class="media-item__ext"><?= e(strtoupper($file['ext'])) ?></span>
<?php endif; ?>
<?php if ($design): ?>
			<span class="media-item__badge media-item__badge--lock" title="<?= e(media_lock_text($places)) ?>">Dizayn</span>
<?php elseif ($places): ?>
			<span class="media-item__badge" title="<?= e('İstifadə olunur:' . "\n" . implode("\n", array_slice($labels, 0, 12)) . (count($labels) > 12 ? "\n…" : '')) ?>">İstifadədə</span>
<?php endif; ?>
<?php if ($pick === '' && !$design): ?>
			<label class="media-item__check" title="Seç — «Seçilənləri sil» üçün">
				<input type="checkbox" name="paths[]" value="<?= e($file['path']) ?>" form="media-bulk" aria-label="Seç: <?= e($file['name']) ?>">
			</label>
<?php endif; ?>
		</div>
		<figcaption class="media-item__foot">
			<span class="media-item__name" title="<?= e($file['path']) ?>"><?= e($file['name']) ?></span>
<?php if ($by !== null && $pick === ''): ?>
<?php   $byNote = $by['origin'] === 'wp' ? 'köhnə saytda (WordPress)' : admin_author_when($by['at']) . ($by['origin'] === 'gone' ? ' · istifadəçi silinib' : ''); ?>
			<span class="media-item__by<?= $by['origin'] === 'gone' ? ' author--gone' : '' ?>" title="<?= e($byNote) ?>">Yükləyib: <?= e($by['name']) ?></span>
<?php endif; ?>
			<div class="media-item__btns">
<?php if ($pick !== ''): ?>
				<button class="btn btn--sm btn--primary" type="button" data-choose="<?= e($file['path']) ?>">Seç</button>
<?php else: ?>
				<button class="btn btn--sm" type="button" data-copy="<?= e($file['path']) ?>">Yolu köçür</button>
<?php if ($design): ?>
				<span class="media-item__lock" title="<?= e(media_lock_text($places)) ?>">
					<button class="btn btn--sm btn--danger" type="button" disabled>Sil</button>
				</span>
<?php else: ?>
				<form method="post" action="<?= e(admin_url(['section' => 'media', 'action' => 'delete'])) ?>">
					<?= admin_token_field() ?>
					<input type="hidden" name="path" value="<?= e($file['path']) ?>">
					<input type="hidden" name="q" value="<?= e($q) ?>">
					<input type="hidden" name="p" value="<?= (int) $page ?>">
					<input type="hidden" name="type" value="<?= e($type) ?>">
<?php   if ($places): ?>
					<input type="hidden" name="confirm" value="<?= e(media_sig([$file['path'] => $labels])) ?>">
<?php   endif; ?>
					<button class="btn btn--sm btn--danger" type="submit"
					        data-confirm="<?= e($places ? media_confirm_text($file['name'], $labels, $isImage)
                                : '“' . $file['name'] . '” silinsin? Fayl zibil qutusuna düşür və ' . TRASH_DAYS . ' gün ərzində bərpa edilə bilər.') ?>">Sil</button>
				</form>
<?php endif; ?>
<?php endif; ?>
			</div>
		</figcaption>
	</figure>
<?php endforeach; ?>
</div>

<?php if (!$slice): ?>
<div class="card"><div class="empty">Fayl tapılmadı.</div></div>
<?php endif; ?>

<?php if ($pages > 1): ?>
<div class="actions" style="margin-top:16px;flex-wrap:wrap">
<?php for ($n = 1; $n <= $pages; $n++): ?>
	<a class="btn btn--sm<?= $n === $page ? ' btn--primary' : '' ?>" href="<?= e(admin_url(['section' => 'media', 'p' => $n] + ($q !== '' ? ['q' => $q] : []) + $keep + $typeKeep)) ?>"><?= $n ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php
$body = ob_get_clean();

// Pəncərə üçün yalnız içi lazımdır
if ($fragment) {
    echo $body;
    return;
}

admin_shell_start('media', $pick !== '' ? 'Şəkil seçin' : 'Qalereya', $pick !== '' ? [] : [
    ['href' => admin_url(['section' => 'trash', 'type' => 'media']), 'label' => 'Zibil qutusu'],
]);
echo $body;
admin_shell_end();
