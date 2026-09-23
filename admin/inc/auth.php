<?php
/**
 * Admin panelinə giriş / authentication.
 */

const ADMIN_SESSION = 'itkin_admin';
const ADMIN_IDLE_LIMIT = 7200;   // 2 saat hərəkətsizlikdən sonra çıxış
const ADMIN_MAX_TRIES  = 8;      // bir IP-dən pəncərədə maksimum səhv cəhd
const ADMIN_TRY_WINDOW = 900;    // 15 dəqiqə

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    // Sessiyalar storage/ altında saxlanılır: paylaşılan hostinqdə ümumi
    // qovluğu başqa saytların təmizləyicisi 24 dəqiqədən sonra boşaldır,
    // panel isə 2 saat hərəkətsizliyə icazə verir.
    $dir = storage_dir('sessions');
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        // Bir çox hostinqdə PHP-nin öz təmizləyicisi söndürülüb (gc_probability=0),
        // köhnə sessiyaları isə cron ümumi qovluqdan silir — bizim qovluğa baxmır.
        // Ona görə təmizləməni burada özümüz yandırırıq.
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    ini_set('session.gc_maxlifetime', (string) ADMIN_IDLE_LIMIT);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Cloudflare arxasında da HTTPS tanınsın, yoxsa cookie “Secure”-suz qalar
        'secure'   => request_is_https(),
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
    // Giriş adı və ya şifrə dəyişibsə köhnə sessiyalar etibarsızdır —
    // oğurlanmış sessiya şifrəni dəyişəndən sonra da açıq qalmasın
    if (!hash_equals(admin_credentials_mark(), (string) ($_SESSION[ADMIN_SESSION]['cred'] ?? ''))) {
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

/** Hazırkı giriş adı və şifrə hash-indən alınan iz — sessiyada saxlanılır */
function admin_credentials_mark(): string
{
    return hash_hmac('sha256', (string) cfg('admin_user', 'admin') . "\n" . (string) cfg('admin_password', ''), 'itkin-admin-sessiya');
}

/** Giriş məlumatları dəyişəndən sonra bu sessiyanı yeni izlə davam etdirir */
function admin_session_renew(): void
{
    session_regenerate_id(true);
    $_SESSION[ADMIN_SESSION]['user'] = (string) cfg('admin_user', 'admin');
    $_SESSION[ADMIN_SESSION]['cred'] = admin_credentials_mark();
}

/**
 * İlkin şifrə hələ də istifadədədirmi. bcrypt yoxlaması baha olduğu üçün
 * nəticə sessiyada giriş məlumatlarının izi ilə birlikdə saxlanılır.
 */
function admin_default_password(): bool
{
    $mark = admin_credentials_mark();
    $seen = $_SESSION['itkin_admin_default'] ?? null;
    if (!is_array($seen) || ($seen[0] ?? '') !== $mark) {
        $seen = [$mark, password_verify('itkin2026', (string) cfg('admin_password', ''))];
        $_SESSION['itkin_admin_default'] = $seen;
    }
    return (bool) $seen[1];
}

/** Profildə göstərilən ad (giriş adı deyil) */
function admin_display_name(): string
{
    $name = trim((string) cfg('admin_name', ''));
    return $name !== '' ? $name : 'Administrator';
}

/* ---------------------------------------------------------------- cəhd limiti */

/** Sorğunu göndərənin IP-si — inc/helpers.php, client_ip() */
function admin_client_ip(): string
{
    return client_ip();
}

/** Cəhd sayğacının açarı: IPv4 olduğu kimi, IPv6 isə /64 — client_key() */
function admin_throttle_key(): string
{
    return client_key();
}

/**
 * Uğursuz cəhdlərin cədvəli üzərində iş (IP => [say, başlanğıc vaxtı]).
 *
 * Əvvəl sayğac sessiyada saxlanılırdı — cookie-ni atan hücumçu hər dəfə
 * sıfırdan başlayırdı. İndi IP-yə görə storage/ altında saxlanılır.
 * $change null deyilsə cədvəli dəyişib yazır; kilid ilə, eyni anda gələn
 * sorğular bir-birinin yazdığını pozmasın.
 */
function admin_attempts(?callable $change = null): array
{
    $file = storage_dir() . '/login-attempts.json';
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return [];
    }
    flock($fh, $change ? LOCK_EX : LOCK_SH);
    $data = json_decode((string) stream_get_contents($fh), true);
    $data = is_array($data) ? $data : [];

    // köhnəlmiş qeydləri atırıq
    foreach ($data as $ip => $row) {
        if (!is_array($row) || time() - (int) ($row[1] ?? 0) > ADMIN_TRY_WINDOW) {
            unset($data[$ip]);
        }
    }
    // fayl sonsuz böyüməsin: ən təzə 5000 qeyd qalır
    if (count($data) > 5000) {
        uasort($data, static function ($a, $b) {
            return (int) $b[1] <=> (int) $a[1];
        });
        $data = array_slice($data, 0, 5000, true);
    }

    if ($change) {
        $data = $change($data);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) json_encode($data));
        fflush($fh);
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data;
}

/** Bu ünvandan çox sayda səhv cəhd olubmu */
function admin_throttled(): bool
{
    $row = admin_attempts()[admin_throttle_key()] ?? null;
    return $row !== null && (int) $row[0] >= ADMIN_MAX_TRIES;
}

/**
 * Şifrəni yoxlamazdan əvvəl cəhdi “sifariş edir”: limit dolubsa false,
 * yoxsa sayğacı artırıb true. Yoxlama və artırma bir kilid altında olur —
 * eyni anda göndərilən onlarla sorğu da limitdən artıq cəhd ala bilmir.
 * Uğurlu girişdən sonra admin_clear_failures() sayğacı sıfırlayır.
 */
function admin_try_begin(): bool
{
    $key = admin_throttle_key();
    $allowed = false;
    $ran = false;
    admin_attempts(static function (array $data) use ($key, &$allowed, &$ran) {
        $ran = true;
        $row = $data[$key] ?? [0, time()];
        if ((int) $row[0] >= ADMIN_MAX_TRIES) {
            return $data;
        }
        $allowed = true;
        $data[$key] = [(int) $row[0] + 1, (int) $row[1]];
        return $data;
    });
    if ($ran) {
        return $allowed;
    }

    // storage/ yazılmır — hamını bayırda qoymaq əvəzinə köhnə qayda ilə
    // sessiyada sayırıq (İcmal səhifəsi girişdən sonra qovluq barədə xəbərdarlıq edir)
    error_log('itkin admin: storage/login-attempts.json açılmır — cəhdlər sessiyada sayılır');
    $row = $_SESSION['itkin_admin_tries'] ?? [0, time()];
    if (!is_array($row) || time() - (int) ($row[1] ?? 0) > ADMIN_TRY_WINDOW) {
        $row = [0, time()];
    }
    if ((int) $row[0] >= ADMIN_MAX_TRIES) {
        return false;
    }
    $_SESSION['itkin_admin_tries'] = [(int) $row[0] + 1, (int) $row[1]];
    return true;
}

function admin_clear_failures(): void
{
    $key = admin_throttle_key();
    admin_attempts(static function (array $data) use ($key) {
        unset($data[$key]);
        return $data;
    });
    unset($_SESSION['itkin_admin_tries']);
}

/* ---------------------------------------------------------------- giriş */

/**
 * Giriş. Cəhd əvvəlcədən admin_try_begin() ilə sayılmış olmalıdır.
 */
function admin_login(string $user, string $password): bool
{
    $expectedUser = (string) cfg('admin_user', 'admin');
    $hash         = (string) cfg('admin_password', '');

    $userOk = hash_equals($expectedUser, $user);
    $passOk = $hash !== '' && password_verify($password, $hash);

    if (!$userOk || !$passOk) {
        return false;
    }

    session_regenerate_id(true);
    admin_clear_failures();
    $_SESSION[ADMIN_SESSION] = ['user' => $expectedUser, 'seen' => time(), 'cred' => admin_credentials_mark()];
    return true;
}

/** Hazırkı şifrəni yoxlayır (profildə giriş məlumatlarını dəyişmək üçün) */
function admin_password_ok(string $password): bool
{
    $hash = (string) cfg('admin_password', '');
    return $hash !== '' && password_verify($password, $hash);
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
