<?php
/**
 * Şəkillər bölməsi / media library.
 * Fayllar uploads/ qovluğunda saxlanılır, yüklənənlər uploads/YYYY/MM/ altına düşür.
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

$fragment = $pick !== '' && isset($_GET['fragment']);
$notice   = '';


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
        admin_redirect(['section' => 'media'] + $keep, $msg, $ok ? 'ok' : 'error');
    }
}

/* ---------------------------------------------------------------- istifadə */

/**
 * Saytda istinad olunan faylların siyahısı: “2023/11/ad.jpg” => true.
 *
 * Yazılar, səhifə şablonları, sabit hissələr və Elementor-un CSS faylları
 * (orada fon şəkilləri “../../2023/11/ad.webp” kimi yazılıb) bir dəfə
 * oxunur və içindəki bütün fayl yolları yığılır. JSON-dakı “2023\/11\/…”
 * yazılışı da tutulur.
 */
function media_usage(string $root): array
{
    static $used = null;
    if ($used !== null) {
        return $used;
    }

    $files = array_merge(
        glob($root . '/data/*.php') ?: [],
        glob($root . '/templates/*.php') ?: [],
        glob($root . '/inc/*.php') ?: [],
        glob($root . '/admin/inc/*.php') ?: [],
        glob($root . '/assets/css/*.css') ?: [],
        glob($root . '/assets/js/*.js') ?: [],
        glob($root . '/uploads/elementor/css/*.css') ?: [],
        [$root . '/config.php']
    );

    $used = [];
    $name = '[^"\'\s<>()\\\\,?\#]+?\.(?:' . implode('|', MEDIA_EXT) . ')(?![A-Za-z0-9.])';
    foreach ($files as $file) {
        // JSON-da yollar “2023\/11\/…” kimi yazılır — adi yola çeviririk
        $text = str_replace('\\/', '/', (string) @file_get_contents($file));

        // uploads/… ilə başlayan istənilən yol (il/ay qovluğunda olmayanlar da)
        if (preg_match_all('#uploads/(' . $name . ')#i', $text, $m)) {
            foreach ($m[1] as $ref) {
                $used[$ref] = true;
            }
        }
        // Elementor CSS-də fon şəkilləri nisbi yazılıb: ../../2023/11/ad.webp
        if (preg_match_all('#\.\./(\d{4}/\d{2}/' . $name . ')#i', $text, $m)) {
            foreach ($m[1] as $ref) {
                $used[$ref] = true;
            }
        }
    }
    return $used;
}

/** “uploads/2023/11/ad.jpg” -> “2023/11/ad.jpg” (istifadə siyahısının açarı) */
function media_ref(string $path): string
{
    return strpos($path, 'uploads/') === 0 ? substr($path, 8) : $path;
}

/* ---------------------------------------------------------------- silmək */

/*
 * Fayl birdəfəlik silinmir: storage/trash/ altına köçürülür (səhv silinibsə
 * oradan qaytarmaq olar). Saytda istifadə olunan fayl isə ümumiyyətlə
 * silinmir — yoxsa yazıda və ya səhifədə qırıq şəkil qalar.
 */
if ($action === 'delete' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $back = ['section' => 'media'] + array_filter([
        'q' => trim((string) ($_POST['q'] ?? '')),
        'p' => max(1, (int) ($_POST['p'] ?? 1)),
    ], static function ($v) { return $v !== '' && $v !== 1; });

    if (!admin_token_ok()) {
        admin_redirect($back, 'Forma köhnəlib. Səhifəni yeniləyin.', 'error');
    }

    $path = post_str('path');
    $abs  = $root . '/' . $path;
    if (!page_path_ok($path) || strpos($path, 'uploads/elementor/') === 0
        || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), MEDIA_EXT, true) || !is_file($abs)) {
        admin_redirect($back, 'Fayl tapılmadı.', 'error');
    }

    if (isset(media_usage($root)[media_ref($path)])) {
        admin_redirect($back, '“' . basename($path) . '” saytda istifadə olunur, ona görə silinmədi. '
            . 'Əvvəlcə onu yazıdan və ya səhifədən götürün.', 'error');
    }

    $trash = storage_dir('trash/' . date('Ymd-His') . '/' . dirname($path));
    if (!is_dir($trash)) {
        admin_redirect($back, 'Silmək alınmadı: storage/ qovluğuna yazmaq olmur.', 'error');
    }
    if (!@rename($abs, $trash . '/' . basename($path))) {
        admin_redirect($back, 'Silmək alınmadı: faylı köçürmək olmadı.', 'error');
    }

    admin_redirect($back, '“' . basename($path) . '” silindi.');
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
		<form method="post" action="<?= e(admin_url(['section' => 'media', 'action' => 'upload'] + $keep)) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
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
		<form method="get" action="<?= e(admin_url()) ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
			<input type="hidden" name="section" value="media">
<?php foreach ($keep as $k => $v): ?>
			<input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
<?php endforeach; ?>
			<input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Fayl adı ilə axtar…" style="max-width:320px">
			<button class="btn" type="submit">Axtar</button>
			<span class="field__hint"><?= count($all) ?> fayl</span>
<?php if ($pick === ''): ?>
			<span class="field__hint">· <span class="badge">İstifadədə</span> olan fayllar saytda göstərilir və silinmir</span>
<?php endif; ?>
		</form>
	</div>
</div>

<div class="media-grid">
<?php foreach ($slice as $file): ?>
<?php $inUse = isset($used[media_ref($file['path'])]); ?>
	<figure class="media-item">
		<div class="media-item__thumb">
<?php if (in_array($file['ext'], MEDIA_IMAGE_EXT, true)): ?>
			<img src="<?= e(asset($file['path'])) ?>" alt="" loading="lazy">
<?php elseif ($file['ext'] === 'mp4'): ?>
			<video src="<?= e(asset($file['path'])) ?>" muted playsinline preload="metadata"></video>
			<span class="media-item__ext">MP4</span>
<?php else: ?>
			<span class="media-item__ext"><?= e(strtoupper($file['ext'])) ?></span>
<?php endif; ?>
<?php if ($inUse): ?>
			<span class="media-item__badge" title="Bu fayl yazıda, səhifədə və ya dizaynda istifadə olunur">İstifadədə</span>
<?php endif; ?>
		</div>
		<figcaption class="media-item__foot">
			<span class="media-item__name" title="<?= e($file['path']) ?>"><?= e($file['name']) ?></span>
			<div class="media-item__btns">
<?php if ($pick !== ''): ?>
				<button class="btn btn--sm btn--primary" type="button" data-choose="<?= e($file['path']) ?>">Seç</button>
<?php else: ?>
				<button class="btn btn--sm" type="button" data-copy="<?= e($file['path']) ?>">Yolu köçür</button>
<?php if ($inUse): ?>
				<button class="btn btn--sm btn--danger" type="button" disabled
				        title="Saytda istifadə olunur — əvvəlcə onu yazıdan və ya səhifədən götürün">Sil</button>
<?php else: ?>
				<form method="post" action="<?= e(admin_url(['section' => 'media', 'action' => 'delete'])) ?>">
					<?= admin_token_field() ?>
					<input type="hidden" name="path" value="<?= e($file['path']) ?>">
					<input type="hidden" name="q" value="<?= e($q) ?>">
					<input type="hidden" name="p" value="<?= (int) $page ?>">
					<button class="btn btn--sm btn--danger" type="submit"
					        data-confirm="“<?= e($file['name']) ?>” silinsin?">Sil</button>
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
	<a class="btn btn--sm<?= $n === $page ? ' btn--primary' : '' ?>" href="<?= e(admin_url(['section' => 'media', 'p' => $n] + ($q !== '' ? ['q' => $q] : []) + $keep)) ?>"><?= $n ?></a>
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

admin_shell_start('media', $pick !== '' ? 'Şəkil seçin' : 'Şəkillər və fayllar');
echo $body;
admin_shell_end();
