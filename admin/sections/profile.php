<?php
/**
 * Mənim profilim — hər istifadəçi öz hesabını burada dəyişir.
 *
 * Ad və e-poçt sadəcə göstərmək üçündür. Giriş adı və şifrə isə yalnız
 * hazırkı şifrə düzgün yazılanda dəyişir — açıq qalmış sessiyanı ələ keçirən
 * biri sahibini paneldən kənarda qoya bilməsin.
 */

$errors = [];
$form   = (string) ($_POST['form'] ?? '');
$me     = admin_user();
$notice = admin_password_notice();   // ilkin və ya müvəqqəti şifrə — yenisi məcburidir

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } elseif ($form === 'profile') {
        $name  = post_str('admin_name');
        $email = post_str('admin_email');
        if ($name === '') {
            $errors[] = 'Adınızı yazın.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-poçt ünvanı düzgün deyil.';
        }
        if (!$errors) {
            admin_user_update($me['id'], ['name' => $name, 'email' => $email]);
            admin_redirect(['section' => 'profile'], 'Profil yadda saxlanıldı.');
        }
    } elseif ($form === 'login') {
        $current = (string) ($_POST['current'] ?? '');
        $login   = post_str('admin_user');
        $pass1   = (string) ($_POST['password'] ?? '');
        $pass2   = (string) ($_POST['password2'] ?? '');

        // Hazırkı şifrənin yoxlanması da girişdəki kimi limitə tabedir —
        // yoxsa açıq qalmış sessiya şifrəni sonsuz təxmin etmək üçün işlənərdi
        // sayğac istifadəçinin özünündür: başqasının girişini və IP-nin sayğacını sıfırlamır
        $tryKey = 'u:' . $me['id'];
        $passOk = false;
        if (!admin_try_begin($tryKey)) {
            $errors[] = 'Çox sayda səhv cəhd. 15 dəqiqə gözləyin.';
        } elseif (!admin_password_ok($current)) {
            $errors[] = 'Hazırkı şifrə səhvdir.';
        } else {
            $passOk = true;
            admin_refund_try($tryKey);
        }
        if (!preg_match(ADMIN_LOGIN_RE, $login)) {
            $errors[] = 'İstifadəçi adı 3–40 simvol olmalıdır: hərf, rəqəm, nöqtə, tire, @.';
        } elseif ($passOk && admin_login_taken($login, $me['id'])) {
            // yalnız şifrə düzgündürsə deyirik — başqalarının giriş adlarını yoxlamaq üçün işlənməsin
            $errors[] = 'Bu istifadəçi adı artıq başqa hesabdadır.';
        }
        if ($notice !== null && $pass1 === '') {
            $errors[] = 'Yeni şifrə yazın — hazırkı şifrə ilə işləmək olmaz.';
        }
        if ($pass1 !== '' || $pass2 !== '') {
            if (strlen($pass1) < ADMIN_PASSWORD_MIN) {
                $errors[] = 'Yeni şifrə ən azı ' . ADMIN_PASSWORD_MIN . ' simvol olmalıdır.';
            } elseif ($pass1 !== $pass2) {
                $errors[] = 'Yeni şifrələr üst-üstə düşmür.';
            } elseif (hash_equals($pass1, $current) || $pass1 === 'itkin2026') {
                $errors[] = 'Yeni şifrə köhnəsi ilə eyni olmamalıdır.';
            }
        }

        if (!$errors) {
            $changes = ['login' => $login];
            if ($pass1 !== '') {
                $changes['password']    = password_hash($pass1, PASSWORD_DEFAULT);
                $changes['must_change'] = null;   // müvəqqəti şifrə artıq yoxdur
            }
            admin_user_update($me['id'], $changes);

            // Bu sessiya davam edir, bütün başqa sessiyalar (başqa brauzer,
            // oğurlanmış cookie) növbəti sorğuda çıxışa düşür
            admin_session_renew();

            admin_redirect(['section' => 'profile'],
                $pass1 !== '' ? 'Giriş məlumatları və şifrə yeniləndi.' : 'Giriş adı yeniləndi.');
        }
    }
}

admin_shell_start('profile', 'Mənim profilim');
f_errors($errors);
?>
<?php if ($notice !== null): ?>
<div class="errors">
	<strong><?= e($notice) ?></strong>
	Aşağıdakı «Giriş adı və şifrə» bölməsində hazırkı şifrəni və yenisini yazın.
</div>
<?php endif; ?>

<div class="narrow">
<?php f_open(['section' => 'profile', 'action' => 'edit']); ?>
	<input type="hidden" name="form" value="profile">
	<div class="card">
		<div class="card__head">Mənim profilim</div>
		<div class="card__body">
			<?php
            f_text('admin_name', 'Ad', admin_display_name());
            f_text('admin_email', 'E-poçt', $me['email'], [
                'type' => 'email',
                'hint' => 'Poçt yoxlaması üçün hazır ünvan kimi də işlənir.',
            ]);
            ?>
			<p class="field__hint" style="margin:0 0 14px">Rol: <strong><?= e(ADMIN_ROLES[$me['role']] ?? '') ?></strong></p>
			<button class="btn btn--primary" type="submit">Yadda saxla</button>
		</div>
	</div>
<?php f_close(); ?>

<?php f_open(['section' => 'profile', 'action' => 'edit'], ['autocomplete' => 'off']); ?>
	<input type="hidden" name="form" value="login">
	<div class="card">
		<div class="card__head">Giriş adı və şifrə</div>
		<div class="card__body">
			<?php
            f_text('admin_user', 'İstifadəçi adı (giriş üçün)', $me['login'], [
                'hint' => '“admin” kimi hamının təxmin etdiyi addan qaçın.',
            ]);
            f_text('current', 'Hazırkı şifrə', '', ['type' => 'password', 'required' => true]);
            f_text('password', 'Yeni şifrə', '', [
                'type' => 'password',
                'hint' => 'Ən azı ' . ADMIN_PASSWORD_MIN . ' simvol.'
                    . ($notice !== null ? '' : ' Şifrəni dəyişmək istəmirsinizsə, boş buraxın.'),
            ]);
            f_text('password2', 'Yeni şifrəni təkrarlayın', '', ['type' => 'password']);
            ?>
			<button class="btn btn--primary" type="submit">Yadda saxla</button>
		</div>
	</div>
<?php f_close(); ?>
</div>
<?php
admin_shell_end();
