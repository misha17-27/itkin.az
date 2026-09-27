<?php
/**
 * Panelin istifadəçiləri / admin users.
 *
 * Siyahı data/users.php-də saxlanılır: repozitoriyaya düşmür (.gitignore),
 * data/ qovluğu brauzerdən açılmır, şifrələr yalnız hash şəklindədir.
 *
 * Fayl yoxdursa, config.php + data/settings.php-dəki köhnə tək hesab
 * (admin_user / admin_password) yeganə administrator sayılır. İlk istifadəçi
 * əlavə olunanda və ya profil dəyişəndə həmin hesab bu fayla köçür.
 * Giriş itəndə: data/users.php-ni silin — köhnə hesab yenidən işləyər.
 */

const ADMIN_ROLES = [
    'admin'  => 'Administrator',
    'editor' => 'Redaktor',
];

/** Yalnız administratorun açdığı bölmələr (redaktor — yalnız məzmun və öz profili) */
const ADMIN_ONLY_SECTIONS = ['users', 'settings', 'mail', 'security'];

/** Giriş adı: hərf, rəqəm, nöqtə, alt xətt, tire, @ */
const ADMIN_LOGIN_RE = '/^[A-Za-z0-9._@-]{3,40}$/';

/** Yeni və ya sıfırlanmış şifrənin minimal uzunluğu */
const ADMIN_PASSWORD_MIN = 10;

function admin_users_file(): string
{
    return dirname(__DIR__, 2) . '/data/users.php';
}

/** Bütün istifadəçilər: id => ['id','login','password','name','email','role', …] */
function admin_users(bool $reload = false): array
{
    static $users = null;
    if ($reload) {
        $users = null;
    }
    if ($users !== null) {
        return $users;
    }

    $file = admin_users_file();
    $rows = is_file($file) ? include $file : null;
    if (!is_array($rows) || $rows === []) {
        // köhnə tək hesab — heç vaxt hamı bayırda qalmasın
        $rows = [[
            'id'       => 1,
            'login'    => (string) cfg('admin_user', 'admin'),
            'password' => (string) cfg('admin_password', ''),
            'name'     => (string) cfg('admin_name', ''),
            'email'    => (string) cfg('admin_email', ''),
            'role'     => 'admin',
        ]];
    }

    $users = [];
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['id']) || !isset($row['login'])) {
            continue;
        }
        $row['id']       = (int) $row['id'];
        $row['login']    = (string) $row['login'];
        $row['password'] = (string) ($row['password'] ?? '');
        $row['name']     = (string) ($row['name'] ?? '');
        $row['email']    = (string) ($row['email'] ?? '');
        $row['role']     = isset(ADMIN_ROLES[$row['role'] ?? '']) ? $row['role'] : 'editor';
        $users[$row['id']] = $row;
    }
    return $users;
}

/** Siyahını yazır (ehtiyat nüsxəsiz — köhnə şifrə hash-ləri yığılmasın). Yalnız admin_users_mutate() içindən. */
function admin_users_save(array $users): void
{
    ksort($users);
    store_save('users', array_values($users),
        'Panelin istifadəçiləri / admin users — şifrələr hash şəklindədir, repozitoriyaya göndərməyin', false);
    admin_users(true);
}

/**
 * Siyahını kilid altında dəyişir: fayl kilidin içində yenidən oxunur, $fn yeni
 * siyahını (və ya dəyişiklik yoxdursa null) qaytarır. Eyni anda iki
 * administratorun yazısı bir-birini silmir, köhnə nüsxə təzəsinin üstünə düşmür.
 *
 * @return mixed $fn-in ikinci nəticəsi ($result referansı ilə)
 */
function admin_users_mutate(callable $fn)
{
    $fh = @fopen(storage_dir() . '/users.lock', 'c');
    if ($fh) {
        flock($fh, LOCK_EX);
    }
    try {
        $result = null;
        $users = $fn(admin_users(true), $result);
        if (is_array($users)) {
            admin_users_save($users);
        }
        return $result;
    } finally {
        if ($fh) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}

function admin_user_get(int $id): ?array
{
    return admin_users()[$id] ?? null;
}

/** Giriş adına görə (böyük-kiçik hərf fərq etmir) */
function admin_user_by_login(string $login): ?array
{
    $want = strtolower(trim($login));
    if ($want === '') {
        return null;
    }
    foreach (admin_users() as $user) {
        if (hash_equals(strtolower($user['login']), $want)) {
            return $user;
        }
    }
    return null;
}

/** Bu giriş adı başqa istifadəçidədirmi */
function admin_login_taken(string $login, int $exceptId = 0): bool
{
    $user = admin_user_by_login($login);
    return $user !== null && $user['id'] !== $exceptId;
}

/** İstifadəçinin göstərilən adı */
function admin_user_label(array $user): string
{
    return trim($user['name']) !== '' ? trim($user['name']) : $user['login'];
}

/** Bir istifadəçinin sahələrini dəyişib yazır (köhnə tək hesab da bu zaman fayla köçür) */
function admin_user_update(int $id, array $changes): void
{
    admin_users_mutate(static function (array $users) use ($id, $changes) {
        if (!isset($users[$id])) {
            return null;
        }
        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($users[$id][$key]);
            } else {
                $users[$id][$key] = $value;
            }
        }
        return $users;
    });
}

/**
 * Son giriş vaxtları ayrıca, storage/user-logins.json-da (kilidlə) saxlanılır:
 * hər girişdə istifadəçilər faylını yenidən yazmaq lazım gəlmir.
 */
function admin_last_login_set(int $id): void
{
    storage_json('user-logins.json', static function (array $d) use ($id) {
        $d[(string) $id] = date('Y-m-d\TH:i:s');
        return $d;
    });
}

function admin_last_logins(): array
{
    return storage_json('user-logins.json');
}
