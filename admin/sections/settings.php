<?php
/**
 * Ayarlar bölməsi.
 * Dəyişikliklər data/settings.php faylına yazılır və config.php-nin üstünə düşür.
 */

$saved  = is_file(dirname(__DIR__, 2) . '/data/settings.php')
    ? (array) include dirname(__DIR__, 2) . '/data/settings.php'
    : [];
$errors = [];
$notice = '';

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }

    $email = post_str('contact_email');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Əlaqə e-poçtu düzgün deyil.';
    }

    $from = post_str('contact_from');
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Göndərən e-poçt düzgün deyil.';
    }

    // Şifrə dəyişikliyi — yalnız hər iki sahə doldurulsa
    $pass1 = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');
    $newHash = null;
    if ($pass1 !== '' || $pass2 !== '') {
        if (strlen($pass1) < 8) {
            $errors[] = 'Şifrə ən azı 8 simvol olmalıdır.';
        } elseif ($pass1 !== $pass2) {
            $errors[] = 'Şifrələr üst-üstə düşmür.';
        } else {
            $newHash = password_hash($pass1, PASSWORD_DEFAULT);
        }
    }

    if (!$errors) {
        $next = $saved;
        $next['site_name']     = post_str('site_name') ?: 'İtkin';
        $next['site_tagline']  = post_str('site_tagline');
        $next['site_url']      = rtrim(post_str('site_url'), '/');
        $next['contact_email'] = $email;
        $next['contact_from']  = $from;
        $next['ga_id']         = post_str('ga_id');
        $next['log_contact']   = !empty($_POST['log_contact']);
        $next['per_page'] = [
            'xeberler'  => max(1, post_int('pp_xeberler', 12)),
            'category'  => max(1, post_int('pp_category', 10)),
            'kitabxana' => max(1, post_int('pp_kitabxana', 12)),
            'itkinlr'   => max(1, post_int('pp_itkinlr', 10)),
        ];
        if ($newHash !== null) {
            $next['admin_password'] = $newHash;
        }

        store_save('settings', $next, 'Sayt ayarları / site settings');
        cfg_reset();
        admin_redirect(['section' => 'settings'],
            $newHash !== null ? 'Ayarlar və şifrə yeniləndi.' : 'Ayarlar yadda saxlanıldı.');
    }
}

/* ---------------------------------------------------------------- forma */

admin_shell_start('settings', 'Ayarlar');
f_errors($errors);

$perPage = (array) cfg('per_page', []);
?>
<?php f_open(['section' => 'settings', 'action' => 'edit']); ?>
<div class="grid2">
	<div>
		<div class="card">
			<div class="card__head">Sayt</div>
			<div class="card__body">
				<?php
                f_text('site_name', 'Saytın adı', (string) cfg('site_name', ''));
                f_text('site_tagline', 'Təşkilatın adı', (string) cfg('site_tagline', ''), [
                    'hint' => 'Struktur məlumatda və altlıqda işlənir.',
                ]);
                f_text('site_url', 'Saytın ünvanı', (string) cfg('site_url', ''), [
                    'placeholder' => 'https://itkin.az',
                    'hint' => 'Boş buraxsanız avtomatik təyin olunur. Yalnız alt qovluqda işləyəndə lazımdır.',
                ]);
                ?>
			</div>
		</div>

		<div class="card">
			<div class="card__head">Əlaqə forması</div>
			<div class="card__body">
				<?php
                f_text('contact_email', 'Müraciətlərin gedəcəyi e-poçt', (string) cfg('contact_email', ''), ['type' => 'email']);
                f_text('contact_from', 'Göndərən ünvan', (string) cfg('contact_from', ''), [
                    'type' => 'email',
                    'hint' => 'Bu ünvan sizin domeninizdə mövcud olmalıdır, yoxsa məktublar spama düşə bilər.',
                ]);
                ?>
				<label class="check">
					<input type="checkbox" name="log_contact" value="1"<?= cfg('log_contact') ? ' checked' : '' ?>>
					Müraciətləri fayla da yaz (<code>storage/contact.log</code>)
				</label>
			</div>
		</div>
	</div>

	<div>
		<div class="card">
			<div class="card__head">Səhifələmə</div>
			<div class="card__body">
				<?php
                f_text('pp_xeberler', 'Xəbərlər səhifəsində', (string) ($perPage['xeberler'] ?? 12), ['type' => 'number']);
                f_text('pp_category', 'Kateqoriyada', (string) ($perPage['category'] ?? 10), ['type' => 'number']);
                f_text('pp_kitabxana', 'Kitabxanada', (string) ($perPage['kitabxana'] ?? 12), ['type' => 'number']);
                f_text('pp_itkinlr', 'İtkinlər siyahısında', (string) ($perPage['itkinlr'] ?? 10), ['type' => 'number']);
                ?>
			</div>
		</div>

		<div class="card">
			<div class="card__head">Statistika</div>
			<div class="card__body">
				<?php f_text('ga_id', 'Google Analytics ID', (string) cfg('ga_id', ''), [
                    'placeholder' => 'GT-XXXXXXX',
                    'hint' => 'Boş buraxsanız sayğac ümumiyyətlə yüklənmir.',
                ]); ?>
			</div>
		</div>

		<div class="card">
			<div class="card__head">Şifrə</div>
			<div class="card__body">
				<p class="field__hint" style="margin-top:0">Dəyişmək istəmirsinizsə, bu sahələri boş buraxın.</p>
				<?php
                f_text('password', 'Yeni şifrə', '', ['type' => 'password']);
                f_text('password2', 'Təkrar', '', ['type' => 'password']);
                ?>
			</div>
		</div>
	</div>
</div>

<div class="actions">
	<button class="btn btn--primary" type="submit">Yadda saxla</button>
</div>
<?php
f_close();
admin_shell_end();
