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

/**
 * Daxil olmuş istifadəçi (admin/inc/users.php) və ya null.
 *
 * Sessiyada yalnız istifadəçinin id-si və giriş məlumatlarının izi saxlanılır;
 * rol və ad hər sorğuda siyahıdan oxunur — dəyişiklik dərhal qüvvəyə minir.
 */
function admin_user(): ?array
{
    $s = $_SESSION[ADMIN_SESSION] ?? null;
    if (!is_array($s) || empty($s['uid'])) {
        return null;
    }
    $seen = (int) ($s['seen'] ?? 0);
    if ($seen > 0 && time() - $seen > ADMIN_IDLE_LIMIT) {
        admin_logout();
        return null;
    }
    // İstifadəçi silinibsə, giriş adı və ya şifrəsi dəyişibsə köhnə sessiyalar
    // etibarsızdır — oğurlanmış sessiya şifrə dəyişəndən sonra açıq qalmasın
    $user = admin_user_get((int) $s['uid']);
    if ($user === null || !hash_equals(admin_credentials_mark($user), (string) ($s['cred'] ?? ''))) {
        admin_logout();
        return null;
    }
    $_SESSION[ADMIN_SESSION]['seen'] = time();
    return $user;
}

function admin_is_logged_in(): bool
{
    return admin_user() !== null;
}

/** İstifadəçinin id, giriş adı və şifrə hash-indən alınan iz — sessiyada saxlanılır */
function admin_credentials_mark(array $user): string
{
    return hash_hmac('sha256', $user['id'] . "\n" . $user['login'] . "\n" . $user['password'], 'itkin-admin-sessiya');
}

/** Öz giriş məlumatlarını dəyişəndən sonra bu sessiyanı yeni izlə davam etdirir */
function admin_session_renew(): void
{
    $user = admin_user_get((int) ($_SESSION[ADMIN_SESSION]['uid'] ?? 0));
    if ($user === null) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION[ADMIN_SESSION]['cred'] = admin_credentials_mark($user);
}

/** Daxil olmuş istifadəçinin rolu ('admin' / 'editor') */
function admin_role(): string
{
    $user = admin_user();
    return $user['role'] ?? 'editor';
}

/** Bu bölməni aça bilərmi (ayarlar və istifadəçilər — yalnız administrator) */
function admin_can(string $section): bool
{
    return !in_array($section, ADMIN_ONLY_SECTIONS, true) || admin_role() === 'admin';
}

/**
 * Şifrəni dəyişmək məcburidirmi: ilkin şifrədir (itkin2026 — README-də açıq
 * yazılıb) və ya administrator müvəqqəti şifrə verib. Mesaj, yoxsa null.
 * bcrypt yoxlaması baha olduğu üçün nəticə sessiyada izlə birlikdə saxlanılır.
 */
function admin_password_notice(): ?string
{
    $user = admin_user();
    if ($user === null) {
        return null;
    }
    if (!empty($user['must_change'])) {
        return 'Administrator sizə müvəqqəti şifrə verib — əvvəlcə öz şifrənizi yazın.';
    }
    $mark = admin_credentials_mark($user);
    $seen = $_SESSION['itkin_admin_default'] ?? null;
    if (!is_array($seen) || ($seen[0] ?? '') !== $mark) {
        $seen = [$mark, $user['password'] !== '' && password_verify('itkin2026', $user['password'])];
        $_SESSION['itkin_admin_default'] = $seen;
    }
    return $seen[1] ? 'Əvvəlcə ilkin şifrəni dəyişin — o, hamıya məlumdur.' : null;
}

/** Profildə və yan menyuda göstərilən ad (giriş adı deyil) */
function admin_display_name(): string
{
    $user = admin_user();
    return $user ? admin_user_label($user) : 'Administrator';
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
 *
 * $key boşdursa — IP (girişdə); profildə hazırkı şifrənin yoxlanması üçün
 * istifadəçinin öz açarı ('u:<id>') verilir.
 * Uğurlu yoxlamadan sonra admin_refund_try() yalnız bu bir cəhdi geri qaytarır:
 * əvvəlki səhvlər qalır — bir hesabla uğurla girmək başqa hesabın şifrəsini
 * təxmin edən sayğacı sıfırlaya bilməz.
 */
function admin_try_begin(string $key = ''): bool
{
    $key = $key !== '' ? $key : admin_throttle_key();
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
    $row = $_SESSION['itkin_admin_tries'][$key] ?? [0, time()];
    if (!is_array($row) || time() - (int) ($row[1] ?? 0) > ADMIN_TRY_WINDOW) {
        $row = [0, time()];
    }
    if ((int) $row[0] >= ADMIN_MAX_TRIES) {
        return false;
    }
    $_SESSION['itkin_admin_tries'][$key] = [(int) $row[0] + 1, (int) $row[1]];
    return true;
}

/** Uğurlu yoxlamadan sonra admin_try_begin()-in götürdüyü bir cəhdi qaytarır */
function admin_refund_try(string $key = ''): void
{
    $key = $key !== '' ? $key : admin_throttle_key();
    admin_attempts(static function (array $data) use ($key) {
        if (isset($data[$key])) {
            $n = max(0, (int) $data[$key][0] - 1);
            if ($n === 0) {
                unset($data[$key]);
            } else {
                $data[$key] = [$n, (int) $data[$key][1]];
            }
        }
        return $data;
    });
    $row = $_SESSION['itkin_admin_tries'][$key] ?? null;
    if (is_array($row)) {
        $_SESSION['itkin_admin_tries'][$key] = [max(0, (int) $row[0] - 1), (int) ($row[1] ?? time())];
    }
}

/* ---------------------------------------------------------------- giriş */

/**
 * Giriş. Cəhd əvvəlcədən admin_try_begin() ilə sayılmış olmalıdır.
 */
function admin_login(string $login, string $password): bool
{
    $user = admin_user_by_login($login);
    $hash = $user['password'] ?? '';

    if ($hash === '') {
        // Belə istifadəçi yoxdur. Cavab müddətindən bunu bilmək olmasın deyə
        // həqiqi yoxlama qədər vaxt aparan iş görülür (PHP 8.4-də bcrypt “cost” 12-dir)
        password_hash($password, PASSWORD_DEFAULT);
        return false;
    }
    if (!password_verify($password, $hash)) {
        return false;
    }

    // Köhnə “cost” ilə yaradılmış hash yenilənir — bütün hesablar eyni vaxt aparsın
    if (is_file(admin_users_file()) && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        try {
            admin_user_update($user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            $user = admin_user_get($user['id']) ?? $user;
        } catch (Throwable $e) {
            // yazmaq alınmasa da giriş davam edir
        }
    }

    session_regenerate_id(true);
    admin_refund_try();
    $_SESSION[ADMIN_SESSION] = ['uid' => $user['id'], 'seen' => time(), 'cred' => admin_credentials_mark($user)];
    unset($_SESSION['itkin_admin_default']);
    admin_last_login_set($user['id']);
    return true;
}

/** Daxil olmuş istifadəçinin hazırkı şifrəsini yoxlayır (profildə dəyişiklik üçün) */
function admin_password_ok(string $password): bool
{
    $user = admin_user();
    $hash = $user['password'] ?? '';
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
