<?php
/**
 * Mənim profilim.
 *
 * Ad və e-poçt sadəcə göstərmək üçündür. Giriş adı və şifrə isə yalnız
 * hazırkı şifrə düzgün yazılanda dəyişir — açıq qalmış sessiyanı ələ keçirən
 * biri sahibini paneldən kənarda qoya bilməsin.
 */

$errors = [];
$form   = (string) ($_POST['form'] ?? '');

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
            admin_settings_save(['admin_name' => $name, 'admin_email' => $email]);
            admin_redirect(['section' => 'profile'], 'Profil yadda saxlanıldı.');
        }
    } elseif ($form === 'login') {
        $current = (string) ($_POST['current'] ?? '');
        $user    = post_str('admin_user');
        $pass1   = (string) ($_POST['password'] ?? '');
        $pass2   = (string) ($_POST['password2'] ?? '');

        // Hazırkı şifrənin yoxlanması da girişdəki kimi limitə tabedir —
        // yoxsa açıq qalmış sessiya şifrəni sonsuz təxmin etmək üçün işlənərdi
        if (!admin_try_begin()) {
            $errors[] = 'Çox sayda səhv cəhd. 15 dəqiqə gözləyin.';
        } elseif (!admin_password_ok($current)) {
            $errors[] = 'Hazırkı şifrə səhvdir.';
        } else {
            admin_clear_failures();
        }
        if (!preg_match('/^[A-Za-z0-9._@-]{3,40}$/', $user)) {
            $errors[] = 'İstifadəçi adı 3–40 simvol olmalıdır: hərf, rəqəm, nöqtə, tire, @.';
        }
        if ($pass1 !== '' || $pass2 !== '') {
            if (strlen($pass1) < 10) {
                $errors[] = 'Yeni şifrə ən azı 10 simvol olmalıdır.';
            } elseif ($pass1 !== $pass2) {
                $errors[] = 'Yeni şifrələr üst-üstə düşmür.';
            } elseif (hash_equals($pass1, $current)) {
                $errors[] = 'Yeni şifrə köhnəsi ilə eyni olmamalıdır.';
            }
        }

        if (!$errors) {
            $changes = ['admin_user' => $user];
            if ($pass1 !== '') {
                $changes['admin_password'] = password_hash($pass1, PASSWORD_DEFAULT);
            }
            admin_settings_save($changes);

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

$defaultPass = password_verify('itkin2026', (string) cfg('admin_password', ''));
?>
<?php if ($defaultPass): ?>
<div class="errors">
	<strong>Şifrə hələ də ilkin şifrədir (itkin2026).</strong>
	Bu şifrə README-də yazılıb — aşağıdan dərhal dəyişin.
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
            f_text('admin_email', 'E-poçt', (string) cfg('admin_email', ''), [
                'type' => 'email',
                'hint' => 'Poçt yoxlaması üçün hazır ünvan kimi də işlənir.',
            ]);
            ?>
			<p class="field__hint" style="margin:0 0 14px">Rol: <strong>Administrator</strong></p>
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
            f_text('admin_user', 'İstifadəçi adı (giriş üçün)', (string) cfg('admin_user', 'admin'), [
                'hint' => '“admin” kimi hamının təxmin etdiyi addan qaçın.',
            ]);
            f_text('current', 'Hazırkı şifrə', '', ['type' => 'password', 'required' => true]);
            f_text('password', 'Yeni şifrə', '', [
                'type' => 'password',
                'hint' => 'Ən azı 10 simvol. Şifrəni dəyişmək istəmirsinizsə, boş buraxın.',
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
