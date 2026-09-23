<?php
/**
 * Poçt (SMTP): əlaqə formasının müraciətləri hara və necə göndərilsin.
 *
 * SMTP serveri yazılıbsa məktublar onun üzərindən gedir (inc/mailer.php),
 * yazılmayıbsa hostinqin PHP mail() funksiyası ilə. SMTP şifrəsi
 * data/settings.php-də saxlanılır (repozitoriyaya düşmür) və səhifədə
 * heç vaxt göstərilmir.
 */

require_once dirname(__DIR__, 2) . '/inc/mailer.php';

const MAIL_SECURE = [
    'ssl' => 'SSL/TLS (465)',
    'tls' => 'STARTTLS (587)',
    ''    => 'Şifrələmə yoxdur (25) — girişsiz',
];

$errors = [];
$result = null;
$form   = (string) ($_POST['form'] ?? '');

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } elseif ($form === 'settings') {
        $to     = post_str('contact_email');
        $from   = post_str('contact_from');
        $host   = post_str('smtp_host');
        $secure = post_str('smtp_secure');
        $port   = post_int('smtp_port', 0);
        $user   = post_str('smtp_user');
        $pass   = (string) ($_POST['smtp_pass'] ?? '');   // boşdursa köhnəsi qalır

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Müraciətlərin gedəcəyi ünvan düzgün deyil.';
        }
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Göndərən ünvan düzgün deyil.';
        }
        if (!array_key_exists($secure, MAIL_SECURE)) {
            $secure = 'ssl';
        }
        if ($host !== '' && !preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
            $errors[] = 'SMTP serverinin adı düzgün deyil (məsələn mail.itkin.az).';
        }
        if ($port === 0) {
            $port = $secure === 'ssl' ? 465 : ($secure === 'tls' ? 587 : 25);
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Port 1–65535 arasında olmalıdır.';
        }
        if ($host !== '' && $secure === '' && $user !== '' && !cfg('smtp_allow_plain')) {
            $errors[] = 'Şifrə şifrələnməmiş bağlantı ilə göndərilmir — SSL/TLS və ya STARTTLS seçin.';
        }
        // Saxlanmış şifrə yalnız yazıldığı serverə gedir: server, port, şifrələmə və ya
        // istifadəçi dəyişibsə, şifrəni yenidən yazmaq lazımdır. Yoxsa ələ keçirilmiş
        // sessiya serveri öz serverinə dəyişib «yoxlama məktubu» ilə poçt şifrəsini öyrənərdi.
        if ($pass === '' && $host !== '' && (string) cfg('smtp_pass', '') !== ''
            && (strcasecmp($host, (string) cfg('smtp_host', '')) !== 0
                || $port !== (int) cfg('smtp_port', 0)
                || $secure !== (string) cfg('smtp_secure', 'ssl')
                || $user !== (string) cfg('smtp_user', ''))) {
            $errors[] = 'Server, port, şifrələmə və ya istifadəçi dəyişib — poçt şifrəsini yenidən yazın.';
        }

        if (!$errors) {
            $changes = [
                'contact_email'  => $to,
                'contact_from'   => $from !== '' ? $from : null,   // boşdursa config.php-dəki qalır
                'mail_from_name' => post_str('mail_from_name'),
                'smtp_host'      => $host,
                'smtp_port'      => $port,
                'smtp_secure'    => $secure,
                'smtp_user'      => $user,
                'log_contact'    => !empty($_POST['log_contact']),
            ];
            if ($pass !== '') {
                $changes['smtp_pass'] = $pass;
            }
            if ($host === '') {
                // SMTP söndürülür — köhnə şifrə faylda qalmasın
                $changes['smtp_pass'] = null;
            }
            admin_settings_save($changes);
            admin_redirect(['section' => 'mail'], 'Poçt ayarları yadda saxlanıldı.');
        }
    } elseif ($form === 'test') {
        $to = post_str('test_to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Yoxlama məktubunun ünvanı düzgün deyil.';
        } else {
            $result = mail_send(
                $to,
                'Yoxlama məktubu — ' . cfg('site_name'),
                "Salam!\n\nBu məktub " . cfg('site_name') . " saytının idarə panelindən göndərilib.\n"
                . "Onu aldınızsa, əlaqə formasının müraciətləri də çatacaq.\n\n"
                . 'Göndərilmə vaxtı: ' . date('d.m.Y H:i') . "\n"
            );
        }
    }
}

admin_shell_start('mail', 'Poçt (SMTP)');
f_errors($errors);

$smtp   = trim((string) cfg('smtp_host', '')) !== '';
$secure = (string) cfg('smtp_secure', 'ssl');
?>
<?php if ($result !== null): ?>
<?php if ($result['ok']): ?>
<div class="card notice notice--ok"><div class="card__body">
	Yoxlama məktubu göndərildi (<?= $result['via'] === 'smtp' ? 'SMTP ilə' : 'PHP mail() ilə' ?>).
	Gəlməyibsə «Spam» qovluğuna da baxın.
</div></div>
<?php else: ?>
<div class="errors">
	<strong>Məktub göndərilmədi.</strong> <?= e($result['error']) ?>
<?php if (!empty($result['log'])): ?>
	<details style="margin-top:8px"><summary>Server ilə yazışma</summary><pre class="mail-log"><?= e(implode("\n", $result['log'])) ?></pre></details>
<?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="narrow narrow--wide">
<?php f_open(['section' => 'mail', 'action' => 'edit'], ['autocomplete' => 'off']); ?>
	<input type="hidden" name="form" value="settings">
	<div class="card">
		<div class="card__head">
			Məktubların göndərilməsi
			<span class="field__hint" style="font-weight:400">— hazırda: <strong><?= $smtp ? 'SMTP' : 'PHP mail()' ?></strong></span>
		</div>
		<div class="card__body">
			<?php f_text('contact_email', 'Saytdan gələn müraciətlər hara gəlsin', (string) cfg('contact_email', ''), ['type' => 'email']); ?>
			<div class="grid3">
				<?php
                f_text('smtp_host', 'SMTP serveri', (string) cfg('smtp_host', ''), [
                    'placeholder' => 'mail.itkin.az',
                    'hint' => 'Boş buraxsanız hostinqin mail() funksiyası işlənir.',
                ]);
                f_text('smtp_port', 'Port', (string) cfg('smtp_port', ''), ['type' => 'number', 'placeholder' => '465']);
                f_select('smtp_secure', 'Şifrələmə', MAIL_SECURE, $secure);
                ?>
			</div>
			<div class="grid2 grid2--even">
				<?php
                f_text('smtp_user', 'İstifadəçi (poçtun tam ünvanı)', (string) cfg('smtp_user', ''), ['placeholder' => 'info@itkin.az']);
                f_text('smtp_pass', 'Şifrə', '', [
                    'type' => 'password',
                    'placeholder' => cfg('smtp_pass') ? 'saxlanılıb — dəyişmək istəmirsinizsə boş buraxın' : '',
                ]);
                f_text('contact_from', 'Göndərən ünvan', (string) cfg('contact_from', ''), [
                    'type' => 'email',
                    'hint' => 'Adətən SMTP istifadəçisi ilə eyni. Başqa domendə olsa məktub spama düşə bilər.',
                ]);
                f_text('mail_from_name', 'Göndərənin adı', (string) cfg('mail_from_name', ''), [
                    'placeholder' => (string) cfg('site_name', 'İtkin'),
                ]);
                ?>
			</div>
			<label class="check" style="margin-bottom:14px">
				<input type="checkbox" name="log_contact" value="1"<?= cfg('log_contact') ? ' checked' : '' ?>>
				Müraciətləri fayla da yaz (<code>storage/contact-log.php</code>) — məktub çatmasa belə itməsin
			</label>
			<button class="btn btn--primary" type="submit">Yadda saxla</button>
		</div>
	</div>
<?php f_close(); ?>

<?php f_open(['section' => 'mail', 'action' => 'edit']); ?>
	<input type="hidden" name="form" value="test">
	<div class="card">
		<div class="card__head">Göndərməni yoxlamaq</div>
		<div class="card__body">
			<p class="field__hint" style="margin-top:0">Hazırkı ayarlarla yoxlama məktubu göndəririk — ayarların düzgün olduğuna əmin olmaq üçün.</p>
			<div class="inline-form">
				<?php f_text('test_to', 'Alıcının ünvanı', post_str('test_to') ?: (string) (cfg('admin_email') ?: cfg('contact_email', '')), ['type' => 'email']); ?>
				<button class="btn" type="submit">Yoxlama məktubu göndər</button>
			</div>
		</div>
	</div>
<?php f_close(); ?>

	<div class="card">
		<div class="card__head">Məlumatları haradan götürmək</div>
		<div class="card__body">
			<p style="margin-top:0">
				cPanel → <strong>Email Accounts</strong> → lazım olan qutunun yanında <strong>Connect Devices</strong>.
				Orada server, port və şifrələmə yazılıb. İstifadəçi — poçtun tam ünvanı, şifrə — həmin qutunun şifrəsi.
			</p>
			<p class="field__hint" style="margin-bottom:0">
				Gmail işlədirsinizsə: server <code>smtp.gmail.com</code>, port 465, SSL/TLS; şifrə kimi Google hesabında
				yaradılan <strong>tətbiq şifrəsi</strong> (App Password) lazımdır — adi şifrə işləmir.
			</p>
		</div>
	</div>
</div>
<?php
admin_shell_end();
