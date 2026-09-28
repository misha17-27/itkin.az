<?php
/**
 * itkin.az — idarə paneli / admin panel.
 *
 * Bütün məzmun data/*.php fayllarında saxlanılır; panel onları oxuyur və
 * geri yazır. Verilənlər bazası lazım deyil.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/inc/helpers.php';
require_once $root . '/inc/data.php';
require_once $root . '/inc/store.php';
require_once $root . '/inc/turnstile.php';

// Panel yalnız öz ünvanında açılır (config.php → admin_path, məsələn /mguliyev/).
// Qovluğun adı ilə (/admin/) gələn sorğu saytın adi 404 səhifəsini görür.
if (!admin_request()) {
    require $root . '/index.php';
    exit;
}

require_once __DIR__ . '/inc/users.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/form.php';
require_once __DIR__ . '/inc/trash.php';
require_once __DIR__ . '/inc/authors.php';

production_errors();
force_https();
send_security_headers(true);

admin_session_start();

$action  = (string) ($_GET['action'] ?? '');
$section = (string) ($_GET['section'] ?? 'dashboard');

/* ---------------------------------------------------------------- çıxış */

if ($action === 'logout') {
    admin_logout();
    admin_redirect([], 'Çıxış etdiniz.');
}

/* ---------------------------------------------------------------- giriş */

if (!admin_is_logged_in()) {
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isset($_POST['user'], $_POST['password'])) {
        // Sessiya bitəndən sonra açıq qalmış səhifədən gələn forma — giriş cəhdi
        // sayılmır, yoxsa bir neçə “Yadda saxla” IP-ni 15 dəqiqəlik bağlayardı
        $error = 'Sessiyanın vaxtı bitib — yenidən daxil olun. Saxlanılmamış dəyişikliklər itdi.';
        if ((string) ($_GET['json'] ?? '') === '1') {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Sessiyanın vaxtı bitib. Səhifəni yeniləyib yenidən daxil olun.'],
                JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!admin_token_ok()) {
            $error = 'Forma köhnəlib. Səhifəni yeniləyib yenidən cəhd edin.';
        } elseif (!admin_try_begin()) {
            $error = 'Çox sayda uğursuz cəhd. 15 dəqiqə gözləyin.';
        } elseif (turnstile_on('login') && !turnstile_verify(admin_client_ip())) {
            $error = 'Zəhmət olmasa “robot deyiləm” yoxlamasını keçin.';
        } elseif (admin_login(post_str('user'), (string) ($_POST['password'] ?? ''))) {
            admin_redirect([], 'Xoş gəldiniz!');
        } else {
            $error = 'İstifadəçi adı və ya şifrə yanlışdır.';
        }
    }
    include __DIR__ . '/views/login.php';
    exit;
}

/*
 * İlkin şifrə (itkin2026) README-də açıq yazılıb; administratorun verdiyi
 * müvəqqəti şifrəni də yalnız ikisi bilir. Dəyişilənə qədər yalnız
 * «Mənim profilim» açılır.
 */
if ($section !== 'profile' && ($notice = admin_password_notice()) !== null) {
    admin_redirect(['section' => 'profile'], $notice, 'error');
}

// Ayarlar və istifadəçilər yalnız administrator üçündür (menyuda gizlətmək kifayət deyil)
if (!admin_can($section)) {
    admin_redirect([], 'Bu bölmə yalnız administrator üçündür.', 'error');
}

// Zibil qutusu: 30 gündən köhnə elementlər gündə bir dəfə silinir (admin/inc/trash.php)
trash_purge_daily();

/* ---------------------------------------------------------------- bölmələr */

if (!array_key_exists($section, ADMIN_SECTIONS)) {
    $section = 'dashboard';
}

$file = __DIR__ . '/sections/' . $section . '.php';
if (!is_file($file)) {
    $file = __DIR__ . '/sections/dashboard.php';
    $section = 'dashboard';
}

try {
    include $file;
} catch (Throwable $e) {
    admin_shell_start($section, 'Xəta');
    echo '<div class="errors"><strong>Əməliyyat alınmadı.</strong><br>' . e($e->getMessage()) . '</div>';
    admin_shell_end();
}
