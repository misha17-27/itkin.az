<?php
/**
 * Məzmunun yazılması / content store writer.
 *
 * Admin paneli məzmunu data/*.php fayllarına geri yazır. Fayllar generator
 * çıxardığı formatda saxlanılır ki, git-də fərqlər oxunaqlı qalsın.
 *
 * Yazı atomikdir: əvvəlcə müvəqqəti fayla yazılır, sonra yerinə keçirilir.
 * Hər yazıdan əvvəl köhnə nüsxə storage/backups/ altında saxlanılır.
 */

const STORE_BACKUPS = 20;

function store_path(string $name): string
{
    if (!preg_match('/^[a-z0-9-]+$/', $name)) {
        throw new InvalidArgumentException('Yanlış fayl adı: ' . $name);
    }
    return dirname(__DIR__) . '/data/' . $name . '.php';
}

/** Massivi PHP mətninə çevirir (generator ilə eyni format) */
function store_export($value, int $indent = 0): string
{
    $pad = str_repeat('    ', $indent);

    if (is_array($value)) {
        if ($value === []) {
            return '[]';
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        $out = "[\n";
        foreach ($value as $key => $item) {
            $out .= $pad . '    ';
            if (!$isList) {
                $out .= store_scalar((string) $key) . ' => ';
            }
            $out .= store_export($item, $indent + 1) . ",\n";
        }
        return $out . $pad . ']';
    }

    return store_scalar($value);
}

function store_scalar($value): string
{
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $text = (string) $value;

    // Uzun və ya çoxsətirli mətnlər heredoc kimi — oxunaqlı qalsın deyə.
    // Uzunluq simvolla ölçülür (bayt ilə deyil) ki, generatorun formatı ilə
    // üst-üstə düşsün və hər yazıdan sonra fayl bütövlükdə dəyişməsin.
    if (strpos($text, "\n") !== false || mb_strlen($text, 'UTF-8') > 160) {
        $tag = 'HTML';
        while (strpos($text, $tag) !== false) {
            $tag .= 'X';
        }
        return "<<<'" . $tag . "'\n" . $text . "\n" . $tag;
    }

    return "'" . strtr($text, ["\\" => "\\\\", "'" => "\\'"]) . "'";
}

/** Köhnə nüsxəni saxlayır və köhnələri təmizləyir */
function store_backup(string $name): void
{
    $file = store_path($name);
    if (!is_file($file)) {
        return;
    }
    $dir = storage_dir('backups');
    if (!is_dir($dir)) {
        return;
    }
    @copy($file, $dir . '/' . $name . '-' . date('Ymd-His') . '.php');

    $old = glob($dir . '/' . $name . '-*.php') ?: [];
    if (count($old) > STORE_BACKUPS) {
        sort($old);
        foreach (array_slice($old, 0, count($old) - STORE_BACKUPS) as $stale) {
            @unlink($stale);
        }
    }
}

/**
 * Məzmunu fayla yazır.
 *
 * @throws RuntimeException yazmaq mümkün olmasa
 */
function store_save(string $name, array $rows, string $comment = '', bool $backup = true): void
{
    $file = store_path($name);

    if ($comment === '') {
        $comment = 'Məzmun / content (admin panelindən yazılıb)';
    }

    $php = "<?php\n/**\n * " . str_replace('*/', '', $comment) . "\n *\n"
         . " * Bu fayl admin panelindən avtomatik yazılır — əl ilə də redaktə etmək olar.\n */\n\n"
         . 'return ' . store_export($rows) . ";\n";

    // Yazmadan əvvəl sintaksisi yoxlayırıq ki, saytı sındırmayaq
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
        throw new RuntimeException('Fayla yazmaq alınmadı: ' . basename($file) . '. Qovluğun yazma icazəsini yoxlayın.');
    }

    store_opcache_drop($tmp);
    $check = store_validate($tmp);
    if ($check !== '') {
        @unlink($tmp);
        throw new RuntimeException('Yazılan məlumat düzgün deyil: ' . $check);
    }

    if ($backup) {
        store_backup($name);
    }

    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Faylı əvəz etmək alınmadı: ' . basename($file));
    }

    store_opcache_drop($file);
    store_forget($name);
}

/**
 * OPcache faylın köhnə nüsxəsini bir neçə saniyə verə bilər (revalidate_freq).
 * Yazıdan dərhal sonra oxunanda — məsələn şifrə dəyişəndə sessiyanın izi —
 * köhnə məlumat götürülməsin deyə keş dərhal atılır.
 */
function store_opcache_drop(string $file): void
{
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);   // @: bəzi hostinqlərdə opcache.restrict_api qadağan edir
    }
}

/** Faylın PHP kimi düzgün olduğunu yoxlayır */
function store_validate(string $file): string
{
    $result = @include $file;
    if (!is_array($result)) {
        return 'massiv qaytarmır';
    }
    return '';
}

/** data_load() keşini təmizləyir ki, yazıdan sonra yeni məlumat oxunsun */
function store_forget(string $name): void
{
    // data_load() statik keş saxlayır; sorğu başa çatandan sonra onsuz da sıfırlanır,
    // amma eyni sorğuda yenidən oxunanda köhnə məlumat qalmasın deyə yeniləyirik.
    data_load($name, true);
}

/** Sətirlər arasında yeni, təkrarlanmayan id */
function store_next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['id'] ?? 0));
    }
    return $max + 1;
}

/** Mətndən ünvana yararlı slug qurur (Azərbaycan hərfləri ilə) */
function store_slug(string $text): string
{
    $map = [
        'ə' => 'e', 'Ə' => 'e', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o',
        'ü' => 'u', 'Ü' => 'u', 'ş' => 's', 'Ş' => 's', 'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g', 'â' => 'a', 'Â' => 'a',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    return trim((string) $text, '-');
}

/** Slug-ı unikal edir (eyni siyahıda təkrarlanmasın) */
function store_unique_slug(array $rows, string $slug, ?int $exceptId = null): string
{
    $taken = [];
    foreach ($rows as $row) {
        if ($exceptId !== null && (int) ($row['id'] ?? 0) === $exceptId) {
            continue;
        }
        $taken[$row['slug']] = true;
    }
    if ($slug === '') {
        $slug = 'yazi';
    }
    $base = $slug;
    $n = 2;
    while (isset($taken[$slug])) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}
