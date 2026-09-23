<?php
/**
 * Təhlükəsizlik: Cloudflare Turnstile (kapça) və qorunmanın icmalı.
 *
 * Kapçanı səhv açarla girişdə yandırmaq paneli kilidləyə bilər, ona görə:
 *   1) açarlar saxlananda gizli açar Cloudflare-də yoxlanılır;
 *   2) yoxlamanı (formada və ya girişdə) yandırmaq üçün bu səhifədəki
 *      pəncərəni keçmək lazımdır — bu, açarların bu domen üçün birlikdə
 *      işlədiyini sübut edir.
 * Hər ehtimala qarşı README-də əl ilə söndürmə yolu da yazılıb.
 */

$errors = [];
$form   = (string) ($_POST['form'] ?? '');

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } elseif ($form === 'keys') {
        $site   = post_str('turnstile_site');
        $secret = post_str('turnstile_secret');   // boşdursa — köhnəsi qalır
        $clear  = !empty($_POST['clear']);

        if ($clear) {
            admin_settings_save([
                'turnstile_site' => null, 'turnstile_secret' => null,
                'turnstile_form' => null, 'turnstile_login' => null,
            ]);
            admin_redirect(['section' => 'security'], 'Kapça söndürüldü, açarlar silindi.');
        }

        if ($site === '' || !preg_match('/^[A-Za-z0-9_-]{10,100}$/', $site)) {
            $errors[] = 'Açıq açar (Site Key) düzgün deyil.';
        }
        if ($secret === '' && trim((string) cfg('turnstile_secret', '')) === '') {
            $errors[] = 'Gizli açarı (Secret Key) yazın.';
        } elseif ($secret !== '') {
            $problem = turnstile_secret_problem($secret);
            if ($problem !== '') {
                $errors[] = $problem;
            }
        }

        if (!$errors) {
            $changes = ['turnstile_site' => $site];
            if ($secret !== '') {
                $changes['turnstile_secret'] = $secret;
            }
            // Açar dəyişibsə əvvəlki yoxlama artıq sübut deyil — yenidən yandırmaq lazımdır
            if ($site !== (string) cfg('turnstile_site', '') || $secret !== '') {
                $changes['turnstile_form']  = null;
                $changes['turnstile_login'] = null;
            }
            admin_settings_save($changes);
            admin_redirect(['section' => 'security'],
                'Açarlar yadda saxlanıldı. İndi aşağıda yoxlamanı keçib kapçanı yandırın.');
        }
    } elseif ($form === 'switch') {
        $wantForm  = !empty($_POST['turnstile_form']);
        $wantLogin = !empty($_POST['turnstile_login']);
        $turningOn = ($wantForm && !cfg('turnstile_form')) || ($wantLogin && !cfg('turnstile_login'));

        if (!turnstile_configured()) {
            $errors[] = 'Əvvəlcə açarları yadda saxlayın.';
        } elseif ($turningOn) {
            // Yandırmaq üçün pəncərə həqiqətən keçilməlidir; Cloudflare-ə çatmaq
            // olmayanda da yandırmırıq — açarların işlədiyini bilmirik
            $token = (string) ($_POST['cf-turnstile-response'] ?? '');
            $check = $token !== '' ? turnstile_check($token, '', admin_client_ip()) : ['ok' => false, 'reached' => true];
            if (!$check['reached']) {
                $errors[] = 'Cloudflare-ə qoşulmaq olmadı — kapçanı indi yandırmaq təhlükəlidir. Bir az sonra yenidən yoxlayın.';
            } elseif (!$check['ok']) {
                $errors[] = 'Yandırmaq üçün əvvəlcə aşağıdakı “robot deyiləm” yoxlamasını keçin.';
            }
        }

        if (!$errors) {
            admin_settings_save([
                'turnstile_form'  => $wantForm ? true : null,
                'turnstile_login' => $wantLogin ? true : null,
            ]);
            admin_redirect(['section' => 'security'], 'Yadda saxlanıldı.');
        }
    }
}

admin_shell_start('security', 'Təhlükəsizlik');
f_errors($errors);

$configured = turnstile_configured();
$onForm     = turnstile_on('form');
$onLogin    = turnstile_on('login');
$host       = (string) (parse_url((string) cfg('site_url', ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'itkin.az'));
?>
<div class="narrow narrow--wide">
<?php f_open(['section' => 'security', 'action' => 'edit'], ['autocomplete' => 'off']); ?>
	<input type="hidden" name="form" value="keys">
	<div class="card">
		<div class="card__head">
			Cloudflare Turnstile (kapça)
			<?php if ($onForm || $onLogin): ?><span class="badge">aktivdir</span>
			<?php elseif ($configured): ?><span class="badge badge--warn">açarlar var, yandırılmayıb</span>
			<?php else: ?><span class="badge badge--off">söndürülüb</span><?php endif; ?>
		</div>
		<div class="card__body">
			<p class="field__hint" style="margin-top:0">Saytdakı əlaqə formasını və bu panelə girişi robotlardan qoruyur.</p>
			<?php
            f_text('turnstile_site', 'Açıq açar (Site Key)', (string) cfg('turnstile_site', ''), [
                'placeholder' => '0x4AAAAAAA…',
            ]);
            f_text('turnstile_secret', 'Gizli açar (Secret Key)', '', [
                'type' => 'password',
                'placeholder' => cfg('turnstile_secret') ? 'saxlanılıb — dəyişmək istəmirsinizsə boş buraxın' : '0x4AAAAAAA…',
                'hint' => 'Saxlananda açar Cloudflare-də yoxlanılır. Gizli açar heç vaxt səhifədə göstərilmir.',
            ]);
            ?>
			<div class="actions">
				<button class="btn btn--primary" type="submit">Yadda saxla</button>
				<?php if ($configured): ?>
				<button class="btn btn--danger" type="submit" name="clear" value="1" formnovalidate
				        data-confirm="Kapça söndürülsün və açarlar silinsin?">Söndür və açarları sil</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php f_close(); ?>

<?php if ($configured): ?>
<?php f_open(['section' => 'security', 'action' => 'edit']); ?>
	<input type="hidden" name="form" value="switch">
	<div class="card">
		<div class="card__head">Harada işləsin</div>
		<div class="card__body">
			<label class="check"><input type="checkbox" name="turnstile_form" value="1"<?= $onForm ? ' checked' : '' ?>> Saytdakı əlaqə formasında</label>
			<label class="check" style="margin-top:6px"><input type="checkbox" name="turnstile_login" value="1"<?= $onLogin ? ' checked' : '' ?>> Panelə girişdə</label>
			<p class="field__hint">
				Yandırmaq üçün aşağıdakı yoxlamanı keçin — beləcə açarların bu domendə (<strong><?= e($host) ?></strong>)
				işlədiyi təsdiqlənir və səhv açarla paneldən kənarda qalmırsınız.
			</p>
			<div class="field"><?= turnstile_widget() ?></div>
			<button class="btn btn--primary" type="submit">Yadda saxla</button>
		</div>
	</div>
<?php f_close(); ?>
<?php endif; ?>

	<div class="card">
		<div class="card__head">Açarları haradan götürmək</div>
		<div class="card__body">
			<ol class="steps">
				<li><strong>dash.cloudflare.com</strong> saytına daxil olun → <strong>Turnstile</strong> bölməsi.</li>
				<li><strong>Add widget</strong> düyməsinə basın.</li>
				<li>Domain — <strong><?= e($host) ?></strong> yazın.</li>
				<li>Widget Mode — <strong>Managed</strong>.</li>
				<li>Site Key və Secret Key-i yuxarıdakı sahələrə köçürün, saxlayın, sonra yoxlamanı keçib kapçanı yandırın.</li>
			</ol>
			<p class="field__hint" style="margin-bottom:0">
				Kapça girişi bağlayıbsa: cPanel → File Manager-də <code>data/settings.php</code> faylından
				<code>turnstile_login</code> sətrini silin.
			</p>
		</div>
	</div>

	<div class="card">
		<div class="card__head">Artıq qorunanlar</div>
		<div class="card__body">
			<ul class="steps">
				<li>Şifrə geri qaytarıla bilməyən hash kimi saxlanılır (bcrypt).</li>
				<li>Panelə giriş: bir ünvandan 15 dəqiqədə <?= ADMIN_MAX_TRIES ?> səhv cəhddən sonra giriş bağlanır (Cloudflare arxasında da həqiqi ünvan nəzərə alınır).</li>
				<li>Paneldəki bütün formalar və silmə əməliyyatları CSRF tokeni ilə qorunur.</li>
				<li>Yüklənən faylın məzmunu uzantısı ilə yoxlanılır; SVG-də skript və aktiv məzmun qəbul edilmir.</li>
				<li>Saytdakı əlaqə formasında botlar üçün tələ sahəsi var.</li>
				<li>Əlaqə jurnalı, ehtiyat nüsxələr və sessiyalar <code>storage/</code>-dadır və kənardan açılmır.</li>
				<li>Panel axtarış sistemlərindən gizlədilib, 2 saat hərəkətsizlikdən sonra çıxış olur.</li>
			</ul>
		</div>
	</div>
</div>
<?php
admin_shell_end();
