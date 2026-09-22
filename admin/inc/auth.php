<?php
/**
 * Admin panelinə giriş / authentication.
 */

const ADMIN_SESSION = 'itkin_admin';
const ADMIN_IDLE_LIMIT = 7200;   // 2 saat hərəkətsizlikdən sonra çıxış
const ADMIN_MAX_TRIES  = 8;      // bir pəncərədə maksimum cəhd
const ADMIN_TRY_WINDOW = 900;    // 15 dəqiqə

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('itkin_admin_sid');
    session_start();
}

function admin_user(): ?string
{
    if (empty($_SESSION[ADMIN_SESSION]['user'])) {
        return null;
    }
    $seen = (int) ($_SESSION[ADMIN_SESSION]['seen'] ?? 0);
    if ($seen > 0 && time() - $seen > ADMIN_IDLE_LIMIT) {
        admin_logout();
        return null;
    }
    $_SESSION[ADMIN_SESSION]['seen'] = time();
    return (string) $_SESSION[ADMIN_SESSION]['user'];
}

function admin_is_logged_in(): bool
{
    return admin_user() !== null;
}

/** Ardıcıl uğursuz cəhdləri məhdudlaşdırır */
function admin_throttled(): bool
{
    $tries = $_SESSION['itkin_admin_tries'] ?? ['n' => 0, 'since' => time()];
    if (time() - (int) $tries['since'] > ADMIN_TRY_WINDOW) {
        return false;
    }
    return (int) $tries['n'] >= ADMIN_MAX_TRIES;
}

function admin_note_failure(): void
{
    $tries = $_SESSION['itkin_admin_tries'] ?? ['n' => 0, 'since' => time()];
    if (time() - (int) $tries['since'] > ADMIN_TRY_WINDOW) {
        $tries = ['n' => 0, 'since' => time()];
    }
    $tries['n'] = (int) $tries['n'] + 1;
    $_SESSION['itkin_admin_tries'] = $tries;
}

function admin_login(string $user, string $password): bool
{
    $expectedUser = (string) cfg('admin_user', 'admin');
    $hash         = (string) cfg('admin_password', '');

    $userOk = hash_equals($expectedUser, $user);
    $passOk = $hash !== '' && password_verify($password, $hash);

    if (!$userOk || !$passOk) {
        admin_note_failure();
        return false;
    }

    session_regenerate_id(true);
    unset($_SESSION['itkin_admin_tries']);
    $_SESSION[ADMIN_SESSION] = ['user' => $expectedUser, 'seen' => time()];
    return true;
}

function admin_logout(): void
{
    unset($_SESSION[ADMIN_SESSION]);
    session_regenerate_id(true);
}

/* ---------------------------------------------------------------- CSRF */

function admin_token(): string
{
    if (empty($_SESSION['itkin_admin_csrf'])) {
        $_SESSION['itkin_admin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['itkin_admin_csrf'];
}

function admin_token_ok(): bool
{
    $given = (string) ($_POST['_token'] ?? '');
    $have  = (string) ($_SESSION['itkin_admin_csrf'] ?? '');
    return $have !== '' && hash_equals($have, $given);
}

function admin_token_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(admin_token()) . '">';
}
