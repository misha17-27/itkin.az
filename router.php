<?php
/**
 * Yalnız lokal inkişaf üçün: php -S localhost:8080 router.php
 * Serverdə (cPanel) bu fayl istifadə olunmur — orada .htaccess işləyir.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . urldecode($path);
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
