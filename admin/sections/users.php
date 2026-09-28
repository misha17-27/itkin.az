<?php
/**
 * İstifadəçilər — yalnız administrator (admin/index.php admin_can() ilə yoxlayır).
 *
 * Yeni istifadəçiyə və ya şifrəsi sıfırlanan istifadəçiyə administrator
 * müvəqqəti şifrə verir; o, ilk girişdə öz şifrəsini yazmalıdır (must_change).
 * Öz hesabınızın rolu və şifrəsi burada deyil, «Mənim profilim»də dəyişir —
 * beləcə sistemdə həmişə ən azı bir administrator qalır.
 */

$me    = admin_user();
$users = admin_users(true);
$id    = (int) ($_GET['id'] ?? 0);

/* ---------------------------------------------------------------- silmək */

if ($action === 'delete' && $id > 0) {
    admin_require_delete('users');
    $item = $users[$id] ?? null;
    if ($item === null) {
        admin_redirect(['section' => 'users'], 'Belə istifadəçi tapılmadı — yəqin artıq silinib.', 'error');
    }
    if ($id === $me['id']) {
        admin_redirect(['section' => 'users'], 'Öz hesabınızı silə bilməzsiniz.', 'error');
    }
    admin_users_mutate(static function (array $users) use ($id) {
        if (isset($users[$id])) {
            // id bir daha verilməsin; əlavə etdiyi məzmunda adı görünsün (admin/inc/authors.php)
            admin_user_seq_note($id);
            admin_user_forget($users[$id]);
        }
        unset($users[$id]);
        return $users;   // silinmiş istifadəçinin sessiyası növbəti sorğuda bağlanır
    });
    admin_redirect(['section' => 'users'], '“' . admin_user_label($item) . '” silindi.');
}

/* ---------------------------------------------------------------- yazmaq */

$errors = [];
$item   = $id > 0 ? ($users[$id] ?? null) : null;
if ($id > 0 && $item === null) {
    admin_redirect(['section' => 'users'], 'Belə istifadəçi tapılmadı.', 'error');
}
$isSelf = $item !== null && $item['id'] === $me['id'];

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name  = post_str('name');
    $login = post_str('login');
    $email = post_str('email');
    $role  = post_str('role');
    $pass1 = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }
    if (!preg_match(ADMIN_LOGIN_RE, $login)) {
        $errors[] = 'Giriş adı 3–40 simvol olmalıdır: hərf, rəqəm, nöqtə, tire, alt xətt, @.';
    } elseif (admin_login_taken($login, $id)) {
        $errors[] = 'Bu giriş adı artıq başqa istifadəçidədir.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-poçt ünvanı düzgün deyil.';
    }
    if ($isSelf) {
        $role = $me['role'];   // öz rolunu dəyişmək olmaz — son administrator itməsin
    } elseif (!isset(ADMIN_ROLES[$role])) {
        $errors[] = 'Rolu seçin.';
    }

    // Şifrə: yeni istifadəçi üçün məcburi, mövcud üçün — yalnız sıfırlananda
    $setPassword = !$isSelf && ($item === null || $pass1 !== '' || $pass2 !== '');
    if ($setPassword) {
        if (strlen($pass1) < ADMIN_PASSWORD_MIN) {
            $errors[] = 'Şifrə ən azı ' . ADMIN_PASSWORD_MIN . ' simvol olmalıdır.';
        } elseif ($pass1 !== $pass2) {
            $errors[] = 'Şifrələr üst-üstə düşmür.';
        } elseif ($pass1 === 'itkin2026') {
            $errors[] = 'Bu şifrə hamıya məlumdur — başqasını yazın.';
        }
    }

    if (!$errors) {
        $isNew = $item === null;
        $hash  = $setPassword ? password_hash($pass1, PASSWORD_DEFAULT) : '';   // bahalı iş — kiliddən əvvəl
        $row   = admin_users_mutate(static function (array $users, &$saved) use ($isNew, $id, $name, $login, $email, $role, $setPassword, $hash) {
            if (!$isNew && !isset($users[$id])) {
                return null;   // bu arada silinib
            }
            foreach ($users as $other) {
                // bu arada başqası eyni giriş adını götürüb
                if ($other['id'] !== $id && strtolower($other['login']) === strtolower($login)) {
                    return null;
                }
            }
            // silinmiş istifadəçinin id-si təkrar verilmir (admin/inc/users.php)
            $newId = $isNew ? admin_user_next_id($users) : $id;
            $row   = $isNew ? ['id' => $newId, 'created' => date('Y-m-d\TH:i:s')] : $users[$id];
            $row['name']  = $name;
            $row['login'] = $login;
            $row['email'] = $email;
            $row['role']  = $role;
            if ($setPassword) {
                // müvəqqəti şifrə: istifadəçi ilk girişdə öz şifrəsini yazmalıdır;
                // şifrə dəyişdiyi üçün onun açıq sessiyaları da bağlanır
                $row['password']    = $hash;
                $row['must_change'] = true;
            }
            $users[$newId] = $row;
            if ($isNew) {
                admin_user_seq_note($newId);
            }
            $saved = $row;
            return $users;
        });
        if ($row === null) {
            admin_redirect(['section' => 'users'], 'Yadda saxlanılmadı: istifadəçi bu arada dəyişib və ya giriş adı tutulub. Yenidən cəhd edin.', 'error');
        }

        if ($isSelf) {
            admin_session_renew();   // öz giriş adını dəyişibsə bu sessiya davam etsin
        }
        admin_redirect(['section' => 'users'], $isNew
            ? '“' . admin_user_label($row) . '” əlavə olundu. Giriş adını və müvəqqəti şifrəni ona çatdırın — ilk girişdə öz şifrəsini yazacaq.'
            : ($setPassword ? 'Yadda saxlanıldı. Yeni müvəqqəti şifrəni istifadəçiyə çatdırın.' : 'Yadda saxlanıldı.'));
    }

    $item = array_merge($item ?? ['id' => 0, 'role' => 'editor'], [
        'name' => $name, 'login' => $login, 'email' => $email, 'role' => $isSelf ? $me['role'] : $role,
    ]);
}

/* ---------------------------------------------------------------- forma */

if ($action === 'edit') {
    $isNew = $id === 0;
    $item  = $item ?? ['id' => 0, 'name' => '', 'login' => '', 'email' => '', 'role' => 'editor'];

    admin_shell_start('users', $isNew ? 'Yeni istifadəçi' : 'İstifadəçini redaktə et', [
        ['href' => admin_url(['section' => 'users']), 'label' => '← Bütün istifadəçilər'],
    ]);
    f_errors($errors);
    f_open(['section' => 'users', 'action' => 'edit', 'id' => $id], ['autocomplete' => 'off']);
    ?>
<div class="narrow">
	<div class="card">
		<div class="card__head">Hesab</div>
		<div class="card__body">
			<?php
            f_text('name', 'Ad, soyad', (string) $item['name']);
            f_text('login', 'Giriş adı', (string) $item['login'], [
                'required' => true,
                'hint' => 'Hərf, rəqəm, nöqtə, tire, alt xətt və @. Məsələn: <code>aysel</code> və ya e-poçt ünvanı.',
            ]);
            f_text('email', 'E-poçt', (string) $item['email'], ['type' => 'email']);
            ?>
<?php if ($isSelf): ?>
			<p class="field__hint" style="margin:0 0 6px">Rol: <strong><?= e(ADMIN_ROLES[$item['role']] ?? '') ?></strong>.
				Öz rolunuzu və şifrənizi burada dəyişmək olmaz — şifrə «<a href="<?= e(admin_url(['section' => 'profile'])) ?>">Mənim profilim</a>»dədir.</p>
<?php else: ?>
			<?php
            f_select('role', 'Rol', ADMIN_ROLES, (string) $item['role'], [
                'hint' => '<strong>Administrator</strong> — hər şey, o cümlədən istifadəçilər və ayarlar. '
                    . '<strong>Redaktor</strong> — yalnız məzmun: xəbərlər, kitabxana, itkinlər, kateqoriyalar, '
                    . 'səhifələr, menyular, şəkillər və əlaqə məlumatları.',
            ]);
            ?>
<?php endif; ?>
		</div>
	</div>

<?php if (!$isSelf): ?>
	<div class="card">
		<div class="card__head"><?= $isNew ? 'Müvəqqəti şifrə' : 'Şifrəni sıfırlamaq' ?></div>
		<div class="card__body">
			<p class="field__hint" style="margin-top:0">
				<?= $isNew
                    ? 'Şifrəni istifadəçiyə özünüz çatdırın. İlk girişdə ondan öz şifrəsini yazması tələb olunacaq.'
                    : 'Boş buraxsanız, şifrə dəyişmir. Yeni şifrə yazsanız, istifadəçinin açıq sessiyaları bağlanır və ilk girişdə öz şifrəsini yazmalı olacaq.' ?>
			</p>
			<?php
            f_text('password', $isNew ? 'Şifrə' : 'Yeni şifrə', '', [
                'type' => 'password',
                'required' => $isNew,
                'hint' => 'Ən azı ' . ADMIN_PASSWORD_MIN . ' simvol.',
            ]);
            f_text('password2', 'Şifrəni təkrarlayın', '', ['type' => 'password', 'required' => $isNew]);
            ?>
		</div>
	</div>
<?php endif; ?>

	<?php f_actions(admin_url(['section' => 'users']),
        $isNew || $isSelf ? null : admin_url(['section' => 'users', 'action' => 'delete', 'id' => $id])); ?>
</div>
    <?php
    f_close();
    admin_shell_end();
    return;
}

/* ---------------------------------------------------------------- siyahı */

$list   = array_values($users);
$logins = admin_last_logins();
usort($list, static function (array $a, array $b) {
    // administratorlar əvvəl, sonra ada görə
    return [$a['role'] !== 'admin', mb_strtolower(admin_user_label($a))] <=> [$b['role'] !== 'admin', mb_strtolower(admin_user_label($b))];
});

admin_shell_start('users', 'İstifadəçilər', [
    ['href' => admin_url(['section' => 'users', 'action' => 'edit']), 'label' => '+ Yeni istifadəçi', 'primary' => true],
]);
?>
<p class="field__hint" style="margin-top:0">
	<strong>Administrator</strong> hər şeyi, o cümlədən istifadəçiləri və ayarları idarə edir.
	<strong>Redaktor</strong> yalnız məzmunla işləyir: xəbərlər, kitabxana, itkinlər, kateqoriyalar,
	səhifələr, menyular, şəkillər və əlaqə məlumatları.
</p>
<table class="table">
	<thead>
		<tr>
			<th>Ad</th>
			<th>Giriş adı</th>
			<th>E-poçt</th>
			<th style="width:130px">Rol</th>
			<th style="width:140px">Son giriş</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
<?php foreach ($list as $u): ?>
<?php $self = $u['id'] === $me['id']; ?>
		<tr>
			<td>
				<a class="table__title" href="<?= e(admin_url(['section' => 'users', 'action' => 'edit', 'id' => $u['id']])) ?>"><?= e(admin_user_label($u)) ?></a>
<?php if ($self): ?>
				<span class="badge">siz</span>
<?php endif; ?>
<?php if (!empty($u['must_change'])): ?>
				<span class="badge badge--warn" title="İlk girişdə öz şifrəsini yazmalıdır">müvəqqəti şifrə</span>
<?php endif; ?>
			</td>
			<td class="table__meta"><?= e($u['login']) ?></td>
			<td class="table__meta"><?= e($u['email']) ?></td>
			<td><?= e(ADMIN_ROLES[$u['role']] ?? '') ?></td>
<?php $last = (string) ($logins[(string) $u['id']] ?? ''); ?>
			<td class="table__meta"><?= $last !== '' ? e(az_date($last) . ', ' . substr($last, 11, 5)) : '—' ?></td>
			<td class="is-right">
				<div class="table__actions">
					<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'users', 'action' => 'edit', 'id' => $u['id']])) ?>">Redaktə</a>
<?php if (!$self): ?>
					<form method="post" action="<?= e(admin_url(['section' => 'users', 'action' => 'delete', 'id' => $u['id']])) ?>">
						<?= admin_token_field() ?>
						<button class="btn btn--sm btn--danger" type="submit"
						        data-confirm="&#8220;<?= e(admin_user_label($u)) ?>&#8221; silinsin? O, panelə daxil ola bilməyəcək. Əlavə etdiyi məzmun saytda qalır — paneldə onun adı ilə göstəriləcək.">Sil</button>
					</form>
<?php endif; ?>
				</div>
			</td>
		</tr>
<?php endforeach; ?>
	</tbody>
</table>
<?php
admin_shell_end();
