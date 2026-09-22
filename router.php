<?php
/**
 * Yalnız lokal inkişaf üçün: php -S localhost:8099 router.php
 * Serverdə (cPanel) bu fayl istifadə olunmur — orada .htaccess işləyir.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . urldecode($path);

// mövcud fayl — birbaşa verilir
if ($path !== '/' && is_file($file)) {
    return false;
}

// qovluq — içindəki index.php (məsələn /admin/)
if (is_dir($file)) {
    $index = rtrim($file, '/') . '/index.php';
    if (is_file($index)) {
        require $index;
        return true;
    }
}

require __DIR__ . '/index.php';
