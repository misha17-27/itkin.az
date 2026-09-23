<?php
/**
 * Əlaqə və sosial şəbəkələr — saytın altlığında və başlıqlarında görünən
 * ümumi məlumatlar. data/contacts.php-yə yazılır; fayl olmayanda orijinal
 * saytın dəyərləri işlənir (inc/helpers.php, CONTACT_DEFAULTS).
 */

const CONTACT_SOCIAL = [
    'facebook'  => ['Facebook',  'https://www.facebook.com/…'],
    'instagram' => ['Instagram', 'https://www.instagram.com/…'],
    'youtube'   => ['YouTube',   'https://www.youtube.com/@…'],
];

$errors = [];

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } else {
        $next = [
            'phone' => post_str('phone'),
            'email' => post_str('email'),
        ];
        if ($next['email'] !== '' && !filter_var($next['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-poçt ünvanı düzgün deyil.';
        }
        foreach (CONTACT_SOCIAL as $key => [$label]) {
            $url = post_str($key);
            if ($url !== '' && (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL))) {
                $errors[] = $label . ': ünvan https:// ilə başlamalıdır.';
            }
            $next[$key] = $url;
        }
        if (!$errors) {
            store_save('contacts', $next, 'Əlaqə və sosial şəbəkələr / site contacts');
            admin_redirect(['section' => 'contacts'], 'Əlaqə məlumatları yadda saxlanıldı.');
        }
    }
}

admin_shell_start('contacts', 'Əlaqə və sosial şəbəkələr', [
    ['href' => base_path() . '/', 'label' => 'Saytı aç ↗'],
]);
f_errors($errors);

$val = static function (string $key) use ($errors): string {
    return $errors ? post_str($key) : site_contact_value($key);
};
?>
<div class="narrow narrow--wide">
<?php f_open(['section' => 'contacts', 'action' => 'edit']); ?>
	<div class="card">
		<div class="card__head">Əlaqə məlumatları</div>
		<div class="card__body">
			<p class="field__hint" style="margin-top:0">Saytın altlığında, hər səhifədə görünür. Boş buraxılan sətir altlıqdan götürülür.</p>
			<div class="grid2 grid2--even">
				<?php
                f_text('phone', 'Telefon (necə göstərilsin)', $val('phone'), ['placeholder' => '(+994 12) 405 99 79']);
                f_text('email', 'E-poçt', $val('email'), ['type' => 'email', 'placeholder' => 'info@itkin.az']);
                ?>
			</div>
			<p class="field__hint" style="margin-bottom:0">
				«Əlaqə» səhifəsindəki telefon, e-poçt və ünvan həmin səhifənin öz sahələridir —
				<a href="<?= e(admin_url(['section' => 'pages', 'page' => 'elaqe'])) ?>">Səhifələr → Əlaqə</a>.
				Formadan gələn müraciətlərin ünvanı isə <a href="<?= e(admin_url(['section' => 'mail'])) ?>">Poçt (SMTP)</a> bölməsindədir.
			</p>
		</div>
	</div>

	<div class="card">
		<div class="card__head">Sosial şəbəkələr</div>
		<div class="card__body">
			<p class="field__hint" style="margin-top:0">
				İkonlar başlıqda və altlıqda görünür. Ünvan boşdursa ikon keçidsiz qalır.
				Instagram və YouTube ikonları daxili səhifələrin başlığındadır.
			</p>
			<?php foreach (CONTACT_SOCIAL as $key => [$label, $placeholder]) {
                f_text($key, $label, $val($key), ['type' => 'url', 'placeholder' => $placeholder]);
            } ?>
		</div>
	</div>

	<div class="actions">
		<button class="btn btn--primary" type="submit">Yadda saxla</button>
	</div>
<?php f_close(); ?>
</div>
<?php
admin_shell_end();
