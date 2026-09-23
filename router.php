<?php
/**
 * Yalnız lokal inkişaf üçün: php -S localhost:8099 router.php
 * Serverdə (cPanel) bu fayl istifadə olunmur — orada .htaccess işləyir,
 * və .htaccess onu kənardan açmağa da imkan vermir.
 *
 * Serverdəki .htaccess qaydalarını burada da təqlid edirik ki, lokal
 * yoxlamalar hostinqdəki davranışı göstərsin: gizli fayllar, sənədlər və
 * yalnız koddan daxil edilən qovluqlar birbaşa açılmır.
 */
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rel  = ltrim(urldecode($path), '/');

if (preg_match('#(^|/)\.(?!well-known(/|$))#', $rel)
    || preg_match('#^(README\.md|router\.php|error_log)$#', $rel)
    || preg_match('#^(inc|templates|data|storage|admin/(inc|sections|views))(/|$)#', $rel)
    || preg_match('#^config\.php$#', $rel)
    || preg_match('#^uploads/.*\.(php[0-9]?|phtml|phar)$#i', $rel)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$root = realpath(__DIR__);
$file = realpath(__DIR__ . '/' . $rel);

// ../ ilə kökdən kənara çıxmaq olmaz
if ($file === false || strpos($file, $root) !== 0) {
    require __DIR__ . '/index.php';
    return true;
}

// mövcud fayl — birbaşa verilir
if ($rel !== '' && is_file($file)) {
    return false;
}

// qovluq — içindəki index.php (məsələn /admin/)
if (is_dir($file)) {
    $index = rtrim($file, '/\\') . '/index.php';
    if (is_file($index)) {
        require $index;
        return true;
    }
}

require __DIR__ . '/index.php';
