<?php
/**
 * Zibil qutusu / trash.
 *
 * Silinən xəbər, kitab, itkin, kateqoriya və qalereyadakı fayl dərhal yox olmur —
 * storage/trash/ altına köçürülür və 30 gün ərzində bərpa oluna bilər:
 *
 *   storage/trash/items/<id>.json      hər silinmiş element üçün bir fayl: bütöv sətir,
 *                                      ingiliscə tərcüməsi (data/<ad>-en.php), sıradakı
 *                                      yeri, kim və nə vaxt sildi
 *   storage/trash/<tarix>/uploads/…    qalereyadan silinmiş faylın özü
 *   storage/trash/.lock                eyni anda gələn əməliyyatlar üçün kilid
 *   storage/trash-purge.txt            sonuncu avtomatik təmizləmənin vaxtı
 *
 * 30 gündən köhnə elementlər avtomatik silinir: paneldəki sorğularda gündə ən çox
 * bir dəfə və zibil qutusu açılanda. Silmə yalnız storage/trash/ içində gedir —
 * yollar yoxlanılır, qovluqdan kənara çıxan heç nə silinmir.
 */

const TRASH_DAYS = 30;

/** Növ => [ad, data faylının şərhi (store_save), ad sahəsi, saytdakı ünvanın prefiksi] */
const TRASH_TYPES = [
    'posts'      => ['Xəbər',      'Xəbərlər və yazılar / posts',            'title', ''],
    'kitabxana'  => ['Kitab',      'Kitabxana / library books',              'title', 'kitabxana-blog/'],
    'itkinlr'    => ['İtkin',      'İtkin düşmüş şəxslər / missing persons', 'title', 'itkinlr/'],
    'categories' => ['Kateqoriya', 'Kateqoriyalar / categories',             'name',  'category/'],
    'media'      => ['Fayl',       '',                                       '',      ''],
];

/** Qalereyadan zibil qutusuna düşə bilən fayl növləri (admin/sections/media.php ilə eyni) */
const TRASH_FILE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4'];

/** Faylın zibil qutusundakı yeri: 20260927-153012/uploads/2026/09/ad.jpg */
const TRASH_FILE_RE = '#^[0-9]{8}-[0-9]{6}(-[0-9]{1,4})?/uploads/[A-Za-z0-9._/-]+$#';

/* ---------------------------------------------------------------- saxlama */

function trash_dir(): string
{
    return storage_dir('trash');
}

function trash_items_dir(): string
{
    return storage_dir('trash/items');
}

/** Elementin id-si: silinmə vaxtı + təsadüfi hissə */
function trash_new_id(): string
{
    return date('Ymd-His') . '-' . bin2hex(random_bytes(4));
}

function trash_id_ok(string $id): bool
{
    return (bool) preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/', $id);
}

/**
 * Zibil qutusu üzərində iş kilid altında: iki nəfər eyni elementi eyni anda
 * bərpa edə və ya silə bilməsin, təmizləmə yazı ilə toqquşmasın.
 */
function trash_locked(callable $fn)
{
    $fh = @fopen(trash_dir() . '/.lock', 'c');
    if ($fh) {
        flock($fh, LOCK_EX);
    }
    try {
        return $fn();
    } finally {
        if ($fh) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}

/**
 * Elementi yazır — atomik: müvəqqəti fayl, sonra yerinə keçirmə.
 *
 * @throws RuntimeException yazmaq mümkün olmasa
 */
function trash_write(array $item): void
{
    $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Zibil qutusuna yazmaq alınmadı: ' . json_last_error_msg() . '.');
    }
    $file = trash_items_dir() . '/' . $item['id'] . '.json';
    $tmp  = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('Zibil qutusuna yazmaq alınmadı: storage/ qovluğunun yazma icazəsini yoxlayın.');
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Zibil qutusuna yazmaq alınmadı: faylı yerinə keçirmək olmadı.');
    }
}

function trash_get(string $id): ?array
{
    if (!trash_id_ok($id)) {
        return null;
    }
    $file = trash_items_dir() . '/' . $id . '.json';
    if (!is_file($file)) {
        return null;
    }
    $item = json_decode((string) @file_get_contents($file), true);
    if (!is_array($item) || ($item['id'] ?? '') !== $id || !isset(TRASH_TYPES[$item['type'] ?? ''])) {
        return null;
    }
    return $item;
}

/** Bütün elementlər — ən son silinən birinci */
function trash_all(): array
{
    $items = [];
    foreach (glob(trash_items_dir() . '/*.json') ?: [] as $file) {
        $item = trash_get(basename($file, '.json'));
        if ($item !== null) {
            $items[] = $item;
        }
    }
    usort($items, static function (array $a, array $b) {
        return ((int) ($b['deleted_at'] ?? 0) <=> (int) ($a['deleted_at'] ?? 0)) ?: strcmp($b['id'], $a['id']);
    });
    return $items;
}

/** Zibil qutusundakı elementlərin sayı (icmal üçün — faylları oxumadan) */
/** Bu növün zibil qutusundakı ən böyük id-si (0 — yoxdur) */
function trash_max_id(string $type): int
{
    $max = 0;
    foreach (trash_all() as $item) {
        if (($item['type'] ?? '') === $type) {
            $max = max($max, (int) ($item['row']['id'] ?? 0));
        }
    }
    return $max;
}

/**
 * Yeni qeyd üçün id: siyahıda, zibil qutusunda və ingiliscə tərcümə faylında
 * olmayan. Yoxsa zibil qutusundakı kateqoriyanın id-si yeni kateqoriyaya
 * verilər və bərpa olunan xəbərlər səhv kateqoriyaya düşərdi.
 */
function admin_next_id(string $type, array $rows): int
{
    $next = max(store_next_id($rows), trash_max_id($type) + 1);
    foreach (array_keys(data_load($type . '-en', true)) as $key) {
        $next = max($next, (int) $key + 1);
    }
    return $next;
}

/** Zibil qutusunda bu kateqoriyaya aid xəbərlərin sayı */
function trash_posts_in_category(int $categoryId): int
{
    $n = 0;
    foreach (trash_all() as $item) {
        if (($item['type'] ?? '') === 'posts'
            && in_array($categoryId, array_map('intval', (array) ($item['row']['categories'] ?? [])), true)) {
            $n++;
        }
    }
    return $n;
}

function trash_count(): int
{
    return count(glob(trash_items_dir() . '/*.json') ?: []);
}

/** Kim sildi: giriş adı və görünən ad */
function trash_who(): array
{
    $user = admin_user();
    return $user ? ['login' => (string) ($user['login'] ?? ''), 'name' => trim((string) ($user['name'] ?? ''))] : [];
}

/** Siyahıda göstərilən: ad, yoxdursa giriş adı */
function trash_who_label(array $item): string
{
    $who = (array) ($item['deleted_by'] ?? []);
    $name = trim((string) ($who['name'] ?? ''));
    return $name !== '' ? $name : (string) ($who['login'] ?? '');
}

/** Neçə gün sonra avtomatik silinəcək (0 — bu gün) */
function trash_days_left(array $item): int
{
    $age = time() - (int) ($item['deleted_at'] ?? 0);
    return max(0, TRASH_DAYS - (int) floor($age / 86400));
}

function trash_flash(string $title): string
{
    return '“' . $title . '” zibil qutusuna köçürüldü. ' . TRASH_DAYS . ' gün ərzində bərpa etmək olar.';
}

/* ---------------------------------------------------------------- qeydlər */

/**
 * Qeydi (xəbər, kitab, itkin, kateqoriya) zibil qutusuna köçürür: əvvəlcə
 * bütöv sətir ingiliscə tərcüməsi ilə birlikdə storage/trash-ə yazılır, sonra
 * data faylından silinir. Yazmaq alınmasa, qeyd yerində qalır.
 *
 * @return array|null zibil qutusundakı element; belə qeyd yoxdursa null
 * @throws RuntimeException
 */
function trash_move_record(string $type, int $id): ?array
{
    if (!isset(TRASH_TYPES[$type]) || $type === 'media') {
        throw new InvalidArgumentException('Yanlış növ: ' . $type);
    }
    [, $comment, $field] = TRASH_TYPES[$type];

    return trash_locked(static function () use ($type, $id, $comment, $field) {
        $rows = data_load($type, true);
        $position = null;
        foreach ($rows as $i => $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $position = $i;
                break;
            }
        }
        if ($position === null) {
            return null;
        }
        $row = $rows[$position];

        $item = [
            'id'         => trash_new_id(),
            'type'       => $type,
            'title'      => (string) ($row[$field] ?? ''),
            'deleted_at' => time(),
            'deleted_by' => trash_who(),
            'position'   => (int) array_search($position, array_keys($rows), true),
            'row'        => $row,
            'en'         => admin_en_get($type, $id),
        ];
        trash_write($item);

        try {
            store_save($type, admin_delete($rows, $id), $comment);
        } catch (Throwable $e) {
            @unlink(trash_items_dir() . '/' . $item['id'] . '.json');
            throw $e;
        }
        admin_en_save($type, $id, null);   // tərcümə zibil qutusundadır — bərpada qayıdır
        return $item;
    });
}

/**
 * Qeydi yerinə qaytarır: sətir əvvəlki sırasına, tərcümə data/<ad>-en.php-yə.
 * Ünvanı (slug) bu arada başqa qeydə verilibsə — yeni unikal ünvan; id tutulubsa —
 * yeni id, ingiliscə tərcümə də onunla gedir.
 */
function trash_restore_record(array $item): array
{
    $type = $item['type'];
    [, $comment, $field, $prefix] = TRASH_TYPES[$type];

    $row = (array) ($item['row'] ?? []);
    $en  = (array) ($item['en'] ?? []);
    if (!isset($row['id'])) {
        return ['ok' => false, 'message' => 'Bərpa alınmadı: elementin məlumatı zədələnib.'];
    }

    $rows   = data_load($type, true);
    $enRows = data_load($type . '-en', true);
    $notes  = [];

    // id: həm siyahıda, həm də tərcümə faylında boş olmalıdır (orada köhnə yetim tərcümə qala bilər)
    $oldId = (int) $row['id'];
    $taken = $oldId <= 0 || admin_find($rows, $oldId) !== null
        || (isset($enRows[$oldId]) && (array) $enRows[$oldId] !== $en);
    $newId = $oldId;
    if ($taken) {
        $newId = admin_next_id($type, $rows);
        $row['id'] = $newId;
    }

    $slug = (string) ($row['slug'] ?? '');
    $free = store_unique_slug($rows, $slug !== '' ? $slug : store_slug((string) ($row[$field] ?? '')));
    if ($free !== $slug) {
        $row['slug'] = $free;
        if ($slug !== '') {
            $notes[] = 'Əvvəlki ünvan başqa qeydə verildiyi üçün yeni ünvan: /' . $prefix . $free . '/.';
        }
    }

    // əvvəlki yerinə — saytdakı sıra (kitablar, itkinlər) dəyişməsin
    $position = max(0, min(count($rows), (int) ($item['position'] ?? 0)));
    array_splice($rows, $position, 0, [$row]);
    store_save($type, array_values($rows), $comment);
    if ($en) {
        admin_en_save($type, $newId, $en);
    }

    $missing = trash_missing_files($row);
    if ($missing) {
        $notes[] = 'Diqqət: ' . count($missing) . ' fayl tapılmadı (' . implode(', ', array_map('basename', array_slice($missing, 0, 3)))
            . (count($missing) > 3 ? ' …' : '') . ') — zibil qutusundadırsa, onu da bərpa edin.';
    }

    return ['ok' => true, 'message' => trim('“' . ($item['title'] ?? '') . '” bərpa olundu'
        . ($en ? ' (ingiliscə versiyası ilə birlikdə)' : '') . '. ' . implode(' ', $notes))];
}

/** Qeydin istinad etdiyi, amma uploads/-da olmayan fayllar */
function trash_missing_files(array $row): array
{
    $text = '';
    array_walk_recursive($row, static function ($value) use (&$text) {
        if (is_string($value)) {
            $text .= $value . "\n";
        }
    });
    $text = str_replace('\\/', '/', $text);
    if (!preg_match_all('#uploads/[A-Za-z0-9._/-]+?\.(?:' . implode('|', TRASH_FILE_EXT) . ')(?![A-Za-z0-9.])#i', $text, $m)) {
        return [];
    }
    $root = dirname(__DIR__, 2);
    $missing = [];
    foreach (array_unique($m[0]) as $path) {
        if (page_path_ok($path) && strpos($path, 'uploads/elementor/') !== 0 && !is_file($root . '/' . $path)) {
            $missing[] = $path;
        }
    }
    return $missing;
}

/* ---------------------------------------------------------------- fayllar */

/**
 * storage/trash içindəki faylın tam yolu — yalnız düzgün yazılmış və həqiqətən
 * storage/trash/ altında olan yol (../, simvolik keçid və s. keçmir). Yoxdursa null.
 */
function trash_file_abs(string $rel): ?string
{
    if (!preg_match(TRASH_FILE_RE, $rel) || strpos($rel, '//') !== false || strpos($rel, '/.') !== false
        || !in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), TRASH_FILE_EXT, true)) {
        return null;
    }
    $root = realpath(trash_dir());
    $real = realpath(trash_dir() . '/' . $rel);
    if ($root === false || $real === false || !is_file($real)
        || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/** Faylı zibil qutusundan silir və boş qalan qovluqlarını yığışdırır (storage/trash-dən kənara çıxmadan) */
function trash_unlink_file(string $rel): void
{
    $abs = trash_file_abs($rel);
    if ($abs === null) {
        return;
    }
    @unlink($abs);
    trash_prune_dirs(dirname($abs));
}

/** Boş qovluqları yuxarıya doğru silir — yalnız storage/trash/ içində, items/ istisna */
function trash_prune_dirs(string $dir): void
{
    $root  = realpath(trash_dir());
    $items = realpath(trash_items_dir());
    if ($root === false) {
        return;
    }
    $dir = realpath($dir);
    while ($dir !== false && $dir !== $root && $dir !== $items && strpos($dir, $root . DIRECTORY_SEPARATOR) === 0) {
        if (!@rmdir($dir)) {   // boş deyilsə rmdir alınmır — dayanırıq
            break;
        }
        $dir = dirname($dir);
    }
}

/**
 * Qalereyadakı faylı zibil qutusuna köçürür: storage/trash/<tarix>/uploads/…
 * $usedIn — silinəndə harada istifadə olunurdu (zibil qutusunda göstərilir).
 *
 * @throws RuntimeException
 */
function trash_move_file(string $path, array $usedIn = []): array
{
    $root = dirname(__DIR__, 2);
    $abs  = $root . '/' . $path;
    if (!page_path_ok($path) || !is_file($abs)
        || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), TRASH_FILE_EXT, true)) {
        throw new RuntimeException('Fayl tapılmadı.');
    }

    return trash_locked(static function () use ($path, $abs, $usedIn) {
        // eyni saniyədə eyni adlı fayl (məsələn bərpa edib yenidən silmək) — növbəti qovluq
        $stamp = date('Ymd-His');
        $dir = $stamp;
        for ($n = 2; file_exists(trash_dir() . '/' . $dir . '/' . $path); $n++) {
            $dir = $stamp . '-' . $n;
        }
        $rel    = $dir . '/' . $path;
        $target = storage_dir('trash/' . $dir . '/' . dirname($path));
        if (!is_dir($target)) {
            throw new RuntimeException('Silmək alınmadı: storage/ qovluğuna yazmaq olmur.');
        }
        $size = (int) @filesize($abs);
        if (!@rename($abs, $target . '/' . basename($path))) {
            trash_prune_dirs($target);
            throw new RuntimeException('Silmək alınmadı: faylı köçürmək olmadı.');
        }

        $item = [
            'id'         => trash_new_id(),
            'type'       => 'media',
            'title'      => basename($path),
            'deleted_at' => time(),
            'deleted_by' => trash_who(),
            'path'       => $path,
            'file'       => $rel,
            'size'       => $size,
            'used_in'    => array_values(array_map('strval', $usedIn)),
        ];
        try {
            trash_write($item);
        } catch (Throwable $e) {
            @rename($target . '/' . basename($path), $abs);   // qeyd yazılmadı — fayl yerinə qayıdır
            trash_prune_dirs($target);
            throw $e;
        }
        return $item;
    });
}

/** Faylı əvvəlki yerinə qaytarır; orada artıq başqa fayl varsa — yeni adla */
function trash_restore_file(array $item): array
{
    $src  = trash_file_abs((string) ($item['file'] ?? ''));
    $path = (string) ($item['path'] ?? '');
    if ($src === null) {
        return ['ok' => false, 'message' => 'Bərpa alınmadı: faylın özü zibil qutusunda tapılmadı. Onu «Birdəfəlik sil» ilə siyahıdan götürə bilərsiniz.'];
    }
    if (!page_path_ok($path) || strpos($path, 'uploads/elementor/') === 0) {
        return ['ok' => false, 'message' => 'Bərpa alınmadı: faylın əvvəlki yolu düzgün deyil.'];
    }

    $root = dirname(__DIR__, 2);
    $dir  = dirname($path);
    $ext  = pathinfo($path, PATHINFO_EXTENSION);
    $base = pathinfo($path, PATHINFO_FILENAME);
    $dest = $path;
    for ($n = 2; file_exists($root . '/' . $dest); $n++) {
        $dest = $dir . '/' . $base . '-' . $n . '.' . $ext;
    }
    if (!is_dir($root . '/' . $dir) && !@mkdir($root . '/' . $dir, 0775, true) && !is_dir($root . '/' . $dir)) {
        return ['ok' => false, 'message' => 'Bərpa alınmadı: ' . $dir . '/ qovluğunu yaratmaq olmadı.'];
    }
    if (!@rename($src, $root . '/' . $dest)) {
        return ['ok' => false, 'message' => 'Bərpa alınmadı: faylı köçürmək olmadı.'];
    }
    @chmod($root . '/' . $dest, 0644);
    trash_prune_dirs(dirname($src));

    if ($dest !== $path) {
        return ['ok' => true, 'message' => '“' . basename($path) . '” bərpa olundu, amma əvvəlki yerində artıq başqa fayl olduğu üçün '
            . 'yeni adla saxlanıldı: ' . $dest . '. Saytdakı köhnə keçidlər həmin başqa faylı göstərir.'];
    }
    return ['ok' => true, 'message' => '“' . basename($path) . '” bərpa olundu — ' . $path . '.'];
}

/**
 * Köhnə qaydada silinmiş faylları (qeydsiz, yalnız storage/trash/<tarix>/uploads/…)
 * siyahıya salır — onlar da zibil qutusunda görünsün, bərpa və təmizlənsin.
 * Kilid altında çağırılmalıdır.
 */
function trash_adopt_files(): void
{
    $root = trash_dir();
    $known = [];
    foreach (trash_all() as $item) {
        if ($item['type'] === 'media') {
            $known[(string) ($item['file'] ?? '')] = true;
        }
    }

    foreach (scandir($root) ?: [] as $entry) {
        if (!preg_match('/^([0-9]{8}-[0-9]{6})(-[0-9]{1,4})?$/', $entry, $m) || !is_dir($root . '/' . $entry . '/uploads')) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $entry . '/uploads', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = $entry . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root . '/' . $entry) + 1));
            if (isset($known[$rel]) || trash_file_abs($rel) === null) {
                continue;
            }
            $when = DateTime::createFromFormat('Ymd-His', $m[1]);
            trash_write([
                'id'         => trash_new_id(),
                'type'       => 'media',
                'title'      => $file->getFilename(),
                'deleted_at' => $when ? $when->getTimestamp() : (int) $file->getMTime(),
                'deleted_by' => [],
                'path'       => substr($rel, strlen($entry) + 1),
                'file'       => $rel,
                'size'       => (int) $file->getSize(),
                'used_in'    => [],
            ]);
            $known[$rel] = true;
        }
    }
}

/* ---------------------------------------------------------------- əməliyyatlar */

/** Bərpa et: ['ok' => bool, 'message' => string] */
function trash_restore(string $id): array
{
    return trash_locked(static function () use ($id) {
        $item = trash_get($id);
        if ($item === null) {
            return ['ok' => false, 'message' => 'Bu element zibil qutusunda yoxdur — yəqin artıq bərpa olunub və ya silinib.'];
        }
        $result = $item['type'] === 'media' ? trash_restore_file($item) : trash_restore_record($item);
        if ($result['ok']) {
            @unlink(trash_items_dir() . '/' . $id . '.json');
        }
        return $result;
    });
}

/** Elementi və (fayldırsa) faylın özünü birdəfəlik silir. Kilid altında çağırılmalıdır. */
function trash_destroy_item(array $item): void
{
    if ($item['type'] === 'media') {
        trash_unlink_file((string) ($item['file'] ?? ''));
    }
    if (trash_id_ok((string) $item['id'])) {
        @unlink(trash_items_dir() . '/' . $item['id'] . '.json');
    }
}

/** Birdəfəlik sil — element yoxdursa false */
function trash_destroy(string $id): ?array
{
    return trash_locked(static function () use ($id) {
        $item = trash_get($id);
        if ($item !== null) {
            trash_destroy_item($item);
        }
        return $item;
    });
}

/** Zibil qutusunu boşaldır — silinən elementlərin sayı */
function trash_empty(): int
{
    return trash_locked(static function () {
        trash_adopt_files();
        $n = 0;
        foreach (trash_all() as $item) {
            trash_destroy_item($item);
            $n++;
        }
        return $n;
    });
}

/**
 * 30 gündən köhnə elementləri silir (həm də yarımçıq qalmış müvəqqəti və
 * oxunmayan köhnə qeyd fayllarını) — silinənlərin sayı.
 */
function trash_purge(): int
{
    return trash_locked(static function () {
        trash_adopt_files();
        $cutoff = time() - TRASH_DAYS * 86400;
        $n = 0;
        foreach (trash_all() as $item) {
            if ((int) ($item['deleted_at'] ?? 0) < $cutoff) {
                trash_destroy_item($item);
                $n++;
            }
        }
        // yarımçıq qalmış yazılar (bir gündən köhnə) və oxunmayan köhnə qeydlər — yalnız items/ içində
        foreach (glob(trash_items_dir() . '/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $name  = basename($file);
            $mtime = (int) @filemtime($file);
            if (substr($name, -4) === '.tmp') {
                if ($mtime < time() - 86400) {
                    @unlink($file);
                }
            } elseif (substr($name, -5) === '.json' && $mtime < $cutoff && trash_get(basename($name, '.json')) === null) {
                @unlink($file);
            }
        }
        return $n;
    });
}

/**
 * Avtomatik təmizləmə — paneldəki hər sorğuda çağırılır, amma gündə ən çox bir
 * dəfə işləyir (vaxtı storage/trash-purge.txt-də). Xəta paneli dayandırmır.
 */
function trash_purge_daily(): void
{
    try {
        $stamp = storage_dir() . '/trash-purge.txt';
        $last  = is_file($stamp) ? (int) @file_get_contents($stamp) : 0;
        if ($last > time() - 86400 && $last <= time()) {
            return;
        }
        if (@file_put_contents($stamp, (string) time(), LOCK_EX) === false) {
            return;   // storage/ yazılmırsa, zibil qutusu da işləmir — hər sorğuda yoxlamayaq
        }
        trash_purge();
    } catch (Throwable $e) {
        // təmizləmə növbəti gün və ya zibil qutusu açılanda yenidən cəhd olunur
    }
}
