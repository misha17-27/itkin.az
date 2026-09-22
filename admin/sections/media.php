<?php
/**
 * Şəkillər bölməsi / media library.
 * Fayllar uploads/ qovluğunda saxlanılır, yüklənənlər uploads/YYYY/MM/ altına düşür.
 */

const MEDIA_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4'];
const MEDIA_MAX = 20971520;   // 20 MB
const MEDIA_PER_PAGE = 60;

$root = dirname(__DIR__, 2);

/*
 * Seçim rejimi: şəkil sahəsi bu səhifəni ayrıca pəncərədə açır və hansı
 * sahəyə qayıdacağını ünvanda göndərir. Bu rejimdə fayl adının yanında
 * "Yolu köçür" deyil, "Seç" düyməsi olur və seçimdən sonra pəncərə bağlanır.
 */
$pick = (string) ($_GET['picker'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $pick)) {
    $pick = '';
}
$keep = $pick !== '' ? ['picker' => $pick] : [];

/*
 * Fragment rejimi: şəkil sahəsi kitabxananı forma üzərindəki pəncərədə açır
 * və yalnız bu səhifənin içini JS ilə gətirir. Ayrıca brauzer pəncərəsi
 * açılmır — o, bloklana və ya sadəcə tab kimi açıla bilərdi.
 */
$fragment = $pick !== '' && isset($_GET['fragment']);
$notice   = '';


/* ---------------------------------------------------------------- yükləmə */

/**
 * Faylları qəbul edir və nəticə mesajını qaytarır.
 * Səhv olanda boş sətir qaytarır və səbəbi $error-a yazır.
 */
function media_upload(string $root, array &$failed): int
{
    $files = $_FILES['files'] ?? null;
    if (!$files || !is_array($files['name'])) {
        return 0;
    }

    $dir = 'uploads/' . date('Y') . '/' . date('m');
    $abs = $root . '/' . $dir;
    if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) {
        $failed[] = $dir . ' (qovluq yaradıla bilmədi)';
        return 0;
    }

    $done = 0;
    foreach ($files['name'] as $i => $name) {
        if ((int) $files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        if (!in_array($ext, MEDIA_EXT, true)) {
            $failed[] = $name . ' (icazə verilməyən format)';
            continue;
        }
        if ((int) $files['size'][$i] > MEDIA_MAX) {
            $failed[] = $name . ' (çox böyükdür)';
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
            $done++;
        } else {
            $failed[] = $name;
        }
    }

    return $done;
}

if ($action === 'upload' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $msg = 'Forma köhnəlib. Səhifəni yeniləyin.';
        $ok  = false;
    } else {
        $failed = [];
        $done   = media_upload($root, $failed);
        $ok     = $done > 0;
        $msg    = $ok ? $done . ' fayl yükləndi.' : 'Fayl yüklənmədi.';
        if ($failed) {
            $msg .= ' Alınmayanlar: ' . implode(', ', array_slice($failed, 0, 4));
        }
    }

    // Pəncərədə açılıbsa yönləndirmirik — siyahını elə burada təzələyib qaytarırıq
    if ($fragment) {
        $notice = $msg;
    } else {
        admin_redirect(['section' => 'media'] + $keep, $msg, $ok ? 'ok' : 'error');
    }
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
        $out[] = ['path' => $rel, 'name' => $file->getFilename(), 'size' => $file->getSize(), 'time' => $file->getMTime()];
    }

    usort($out, static function (array $a, array $b) {
        return $b['time'] <=> $a['time'];
    });

    return $out;
}

$all = media_files($root);

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

// Şəkil sahəsi üçün yalnız şəkillər göstərilir
if ($pick !== '') {
    $all = array_values(array_filter($all, static function (array $f) {
        return in_array(strtolower(pathinfo($f['path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }));
    $pages = max(1, (int) ceil(count($all) / MEDIA_PER_PAGE));
    $page  = min($page, $pages);
    $slice = array_slice($all, ($page - 1) * MEDIA_PER_PAGE, MEDIA_PER_PAGE);
}

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
			<input class="input" type="file" name="files[]" multiple style="max-width:340px" required>
			<button class="btn btn--primary" type="submit">Yüklə</button>
			<span class="field__hint">JPG, PNG, WebP, GIF, SVG, PDF, MP4 — 20 MB-a qədər. Fayllar <code>uploads/<?= date('Y/m') ?>/</code> qovluğuna düşür.</span>
		</form>
	</div>
</div>

<div class="card">
	<div class="card__body">
		<form method="get" action="<?= e(admin_url()) ?>" style="display:flex;gap:8px;align-items:center">
			<input type="hidden" name="section" value="media">
<?php if ($pick !== ''): ?>
			<input type="hidden" name="picker" value="<?= e($pick) ?>">
<?php endif; ?>
			<input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Fayl adı ilə axtar…" style="max-width:320px">
			<button class="btn" type="submit">Axtar</button>
			<span class="field__hint"><?= count($all) ?> fayl</span>
		</form>
	</div>
</div>

<div class="media-grid">
<?php foreach ($slice as $file): ?>
	<figure class="media-item" style="margin:0">
<?php $ext = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION)); ?>
<?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)): ?>
		<img src="<?= asset($file['path']) ?>" alt="" loading="lazy">
<?php else: ?>
		<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="" style="display:grid;place-items:center">
		<div style="height:108px;display:grid;place-items:center;font-weight:700;color:#5B6F65"><?= e(strtoupper($ext)) ?></div>
<?php endif; ?>
		<figcaption class="media-item__foot">
			<span class="media-item__name" title="<?= e($file['path']) ?>"><?= e($file['name']) ?></span>
<?php if ($pick !== ''): ?>
			<button class="btn btn--sm btn--primary" type="button" data-choose="<?= e($file['path']) ?>">Seç</button>
<?php else: ?>
			<button class="btn btn--sm" type="button" data-copy="<?= e($file['path']) ?>">Yolu köçür</button>
<?php endif; ?>
		</figcaption>
	</figure>
<?php endforeach; ?>
</div>

<?php if (!$slice): ?>
<div class="card"><div class="empty">Fayl tapılmadı.</div></div>
<?php endif; ?>

<?php if ($pages > 1): ?>
<div class="actions" style="margin-top:16px">
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
