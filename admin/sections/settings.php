<?php
/**
 * Ümumi ayarlar.
 * Dəyişikliklər data/settings.php faylına yazılır və config.php-nin üstünə düşür.
 *
 * Şifrə «Mənim profilim», əlaqə formasının poçtu «Poçt (SMTP)», kapça isə
 * «Təhlükəsizlik» bölməsindədir.
 */

$errors = [];

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }

    $siteUrl = rtrim(post_str('site_url'), '/');
    if ($siteUrl !== '' && !preg_match('#^https?://[^\s/]+#i', $siteUrl)) {
        $errors[] = 'Saytın ünvanı http:// və ya https:// ilə başlamalıdır.';
    } elseif (stripos($siteUrl, 'https://') === 0 && stripos((string) cfg('site_url'), 'https://') !== 0
        && !request_is_https() && PHP_SAPI !== 'cli-server') {
        // https:// ünvan bütün sorğuları HTTPS-ə yönləndirir — SSL işləmirsə sayt açılmaz
        $errors[] = 'https:// ünvanı saxlamaq üçün paneli https:// ilə açın — beləcə SSL-in işlədiyi yoxlanılır.';
    }

    if (!$errors) {
        admin_settings_save([
            'site_name'    => post_str('site_name') ?: 'İtkin',
            'site_tagline' => post_str('site_tagline'),
            'site_url'     => $siteUrl,
            'ga_id'        => post_str('ga_id'),
            'per_page'     => [
                'xeberler'  => max(1, post_int('pp_xeberler', 12)),
                'category'  => max(1, post_int('pp_category', 10)),
                'kitabxana' => max(1, post_int('pp_kitabxana', 12)),
                'itkinlr'   => max(1, post_int('pp_itkinlr', 10)),
            ],
        ]);
        admin_redirect(['section' => 'settings'], 'Ayarlar yadda saxlanıldı.');
    }
}

/* ---------------------------------------------------------------- forma */

admin_shell_start('settings', 'Ümumi ayarlar');
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
                    'hint' => 'Boş buraxsanız avtomatik təyin olunur. https:// ilə yazılsa, http sorğuları HTTPS-ə yönləndirilir.',
                ]);
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
			<div class="card__head">Başqa ayarlar</div>
			<div class="card__body">
				<ul class="steps" style="margin:0">
					<li><a href="<?= e(admin_url(['section' => 'mail'])) ?>">Poçt (SMTP)</a> — müraciətlər hara gəlsin, necə göndərilsin</li>
					<li><a href="<?= e(admin_url(['section' => 'security'])) ?>">Təhlükəsizlik</a> — kapça</li>
					<li><a href="<?= e(admin_url(['section' => 'profile'])) ?>">Mənim profilim</a> — ad, giriş adı, şifrə</li>
				</ul>
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
