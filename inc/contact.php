<?php
/**
 * Əlaqə formasının emalı / contact form handler.
 * Elementor formasının sahə adları saxlanılıb ki, markup dəyişməsin.
 *
 * Spama və “poçt bombardmanına” qarşı qatlar (sıra ilə):
 *   1. tələ sahəsi (website) — robot doldurur, biz uğur göstərib heç nə göndərmirik;
 *   2. nişan — hər səhifə açılışına ayrıca, birdəfəlik, 6 saat keçərli;
 *      3 saniyədən tez göndərilən forma robot sayılır;
 *   3. sahələrin uzunluğu məhduddur;
 *   4. Cloudflare Turnstile (panelin «Təhlükəsizlik» bölməsindən yandırılır);
 *   5. limit: bir ünvandan saatda 5, ümumilikdə saatda 40 müraciət.
 */

require_once __DIR__ . '/turnstile.php';
require_once __DIR__ . '/mailer.php';

const CONTACT_FIELDS = [
    'name'    => 'form_fields[name]',
    'surname' => 'form_fields[field_4df86f7]',
    'email'   => 'form_fields[email]',
    'phone'   => 'form_fields[field_5057311]',
    'message' => 'form_fields[field_71766c7]',
];

/** Sahələrin maksimum uzunluğu (simvol) */
const CONTACT_MAX = ['name' => 100, 'surname' => 100, 'email' => 254, 'phone' => 32, 'message' => 5000];

/** Xəta mesajlarında sahənin adı */
const CONTACT_LABELS = ['name' => 'Ad', 'surname' => 'Soyad', 'email' => 'E-poçt', 'phone' => 'Telefon', 'message' => 'Mesaj'];

const CONTACT_MIN_SECONDS = 3;       // bundan tez doldurulan forma robotdur
const CONTACT_MAX_AGE     = 21600;   // nişan 6 saat keçərlidir
const CONTACT_PER_IP      = 5;       // bir ünvandan saatda
const CONTACT_PER_HOUR    = 40;      // ümumilikdə saatda
const CONTACT_LOG_MAX     = 5242880; // jurnal 5 MB-dan böyüyəndə yenisi başlanır

/**
 * Nişanın gizli açarı.
 *
 * config.php-də form_secret yazılmayıbsa, ilk dəfə təsadüfi açar yaradılır və
 * storage/form-secret.php-də saxlanılır (PHP faylı kimi — birbaşa açılsa da
 * heç nə göstərmir). Əvvəl açar sadəcə quraşdırma yolu idi və təxmin oluna bilərdi.
 */
function contact_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $secret = (string) cfg('form_secret');
    if ($secret !== '') {
        return $secret;
    }
    $file = storage_dir() . '/form-secret.php';
    if (is_file($file)) {
        $saved = include $file;
        if (is_string($saved) && strlen($saved) >= 32) {
            return $secret = $saved;
        }
    }
    $fresh = bin2hex(random_bytes(32));
    if (@file_put_contents($file, "<?php return '" . $fresh . "';\n", LOCK_EX) !== false) {
        return $secret = $fresh;
    }
    // storage/ yazılmırsa — son çarə (panelin İcmal səhifəsi bunu xəbərdar edir)
    return $secret = hash('sha256', 'itkin-' . __DIR__ . (function_exists('php_uname') ? php_uname() : ''));
}

/**
 * Formanın nişanı: vaxt.təsadüfi.imza — hər açılışda yenisi.
 * Sessiya açmırıq ki, adi GET sorğusunda cookie və "no-store" başlıqları yaranmasın.
 *
 * $issued — forma xəta ilə geri qayıdanda ilk açılışın vaxtı: sahələr artıq
 * doldurulub, insan onu 3 saniyədə yenidən göndərə bilər və robot sayılmamalıdır.
 */
function contact_token(?int $issued = null): string
{
    $payload = ($issued ?? time()) . '.' . bin2hex(random_bytes(8));
    return $payload . '.' . hash_hmac('sha256', 'contact:' . $payload, contact_secret());
}

/**
 * Nişanı yoxlayır.
 * @return string 'ok' | 'fast' (çox tez göndərilib) | 'bad' (saxta və ya köhnə)
 */
function contact_token_state(string $token, ?string &$nonce = null, ?int &$issued = null): string
{
    $parts = explode('.', $token);
    if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_xdigit($parts[1])) {
        return 'bad';
    }
    $expected = hash_hmac('sha256', 'contact:' . $parts[0] . '.' . $parts[1], contact_secret());
    if (!hash_equals($expected, $parts[2])) {
        return 'bad';
    }
    $age = time() - (int) $parts[0];
    if ($age < 0 || $age > CONTACT_MAX_AGE) {
        return 'bad';
    }
    $nonce  = $parts[1];
    $issued = (int) $parts[0];
    return $age < CONTACT_MIN_SECONDS ? 'fast' : 'ok';
}

/**
 * Göndərişi qeydə alır: nişan əvvəl işlənibsə və ya limit dolubsa false.
 * Yoxlama və qeyd bir kilid altındadır — eyni anda gələn sorğular limiti keçə bilmir.
 */
function contact_take_slot(string $nonce, string $who, string &$why): bool
{
    $ok = false;
    storage_json('contact-guard.json', static function (array $d) use ($nonce, $who, &$ok, &$why) {
        $now = time();
        $sent = (array) ($d['sent'] ?? []);
        $used = (array) ($d['nonces'] ?? []);

        // köhnəlmiş qeydlər atılır, fayl sonsuz böyümür
        foreach ($sent as $k => $times) {
            $times = array_values(array_filter((array) $times, static function ($t) use ($now) {
                return (int) $t > $now - 3600;
            }));
            if ($times) {
                $sent[$k] = $times;
            } else {
                unset($sent[$k]);
            }
        }
        $used = array_filter($used, static function ($t) use ($now) {
            return (int) $t > $now - CONTACT_MAX_AGE;
        });
        if (count($used) > 20000) {
            $used = array_slice($used, -20000, null, true);
        }

        if (isset($used[$nonce])) {
            $why = 'reused';
        } elseif (count($sent[$who] ?? []) >= CONTACT_PER_IP) {
            $why = 'ip';
        } elseif (array_sum(array_map('count', $sent)) >= CONTACT_PER_HOUR) {
            $why = 'global';
        } else {
            $ok = true;
            $used[$nonce] = $now;
            $sent[$who][] = $now;
        }
        return ['sent' => $sent, 'nonces' => $used];
    });
    return $ok;
}

/** Müraciəti jurnala yazır (panelin «Poçt» bölməsində yandırılır) */
function contact_log(string $status, string $body): void
{
    $file = (string) cfg('log_file');
    if ($file === '') {
        return;
    }
    storage_dir();   // qoruyucu .htaccess yerində olsun — jurnalda şəxsi məlumat var
    @mkdir(dirname($file), 0775, true);

    // böyüyəndə köhnəsi arxivə çıxır
    if (is_file($file) && filesize($file) > CONTACT_LOG_MAX) {
        @rename($file, preg_replace('/(\.php)?$/', '-' . date('Ymd-His') . '$1', $file, 1));
    }
    // PHP faylı kimi başlayır: .htaccess işləməsə də brauzerdə açılanda heç nə göstərmir
    $head = !is_file($file) && substr($file, -4) === '.php' ? "<?php exit; ?>\n" : '';
    @file_put_contents($file, $head . '[' . date('c') . '] ' . $status . "\n" . $body . str_repeat('-', 40) . "\n",
        FILE_APPEND | LOCK_EX);
}

/**
 * Formanı emal edir və vəziyyəti qaytarır.
 *
 * @return array{sent:bool,errors:array<string,string>,values:array<string,string>,notice:string}
 */
function contact_handle(): array
{
    // issued — geri qaytarılan formanın yeni nişanına yazılacaq vaxt (contact_token)
    $state = ['sent' => false, 'errors' => [], 'values' => [], 'notice' => '', 'issued' => null];

    $posted = $_POST['form_fields'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !is_array($posted)) {
        return $state;
    }

    // Yalnız sətir qəbul edilir: massiv göndərən xəbərdarlıq (və fayl yolu) görməsin
    $str = static function ($x): string {
        return is_string($x) ? trim($x) : '';
    };
    $v = static function (string $key) use ($posted, $str): string {
        $inner = substr(CONTACT_FIELDS[$key], strlen('form_fields['), -1);
        $value = $str($posted[$inner] ?? '');
        // birsətirlik sahələrdə sətir sonu olmur — məktubda və jurnalda saxta sətir yaratmasın
        return $key === 'message' ? str_replace(["\r\n", "\r"], "\n", $value) : trim((string) preg_replace('/[\r\n\t]+/', ' ', $value));
    };

    $values = [
        'name'    => $v('name'),
        'surname' => $v('surname'),
        'email'   => $v('email'),
        'phone'   => $v('phone'),
        'message' => $v('message'),
    ];
    $state['values'] = $values;

    // Tələ sahəsi — robotlar bu sahəni doldurur; uğur göstəririk, heç nə göndərmirik
    if ($str($_POST['website'] ?? '') !== '') {
        $state['sent'] = true;
        $state['values'] = [];
        return $state;
    }

    $nonce  = '';
    $issued = null;
    $token  = contact_token_state($str($_POST['_token'] ?? ''), $nonce, $issued);

    // Forma bundan sonra yalnız xəta ilə geri qayıda bilər — onun yeni nişanı
    // “təzə” olmasın, yoxsa düzəliş edib dərhal göndərən insan robot sayılardı
    // və mesajı səssizcə itərdi
    $state['issued'] = $token === 'ok' ? $issued : time() - CONTACT_MIN_SECONDS;

    if ($token === 'bad') {
        // yeni nişan artıq formadadır — yenidən basmaq kifayətdir
        $state['notice'] = 'Formanın etibarlılıq müddəti bitmişdi. Zəhmət olmasa yenidən göndərin.';
        return $state;
    }
    if ($token === 'fast') {
        // İnsan formanı 3 saniyədə doldura bilməz — robotdur; tələdəki kimi davranırıq
        $state['sent'] = true;
        $state['values'] = [];
        $state['issued'] = null;
        return $state;
    }

    if ($values['name'] === '')    { $state['errors']['name']    = 'Adınızı yazın.'; }
    if ($values['surname'] === '') { $state['errors']['surname'] = 'Soyadınızı yazın.'; }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $state['errors']['email'] = 'Düzgün e-poçt ünvanı yazın.';
    }
    if ($values['phone'] === '')   { $state['errors']['phone']   = 'Telefon nömrənizi yazın.'; }
    foreach (CONTACT_MAX as $key => $max) {
        if (mb_strlen($values[$key], 'UTF-8') > $max) {
            $state['errors'][$key] = CONTACT_LABELS[$key] . ' çox uzundur (ən çoxu ' . $max . ' simvol).';
            $state['values'][$key] = mb_substr($values[$key], 0, $max, 'UTF-8');
        }
    }

    if ($state['errors']) {
        // Şablonda sahələrin yanında yer yoxdur — səbəblər ümumi mesajda sadalanır
        $state['notice'] = 'Zəhmət olmasa sahələri düzgün doldurun: ' . implode(' ', $state['errors']);
        return $state;
    }

    // Kapça yalnız qalan hər şey düzgün olanda soruşulur — cavab birdəfəlikdir
    if (turnstile_on('form') && !turnstile_verify(client_ip())) {
        $state['notice'] = 'Zəhmət olmasa “robot deyiləm” yoxlamasını keçin və yenidən göndərin.';
        return $state;
    }

    $why = '';
    if (!contact_take_slot($nonce, client_key(), $why)) {
        $state['notice'] = $why === 'reused'
            ? 'Bu forma artıq göndərilib. Yeni müraciət üçün səhifəni yeniləyin.'
            : 'Hazırda çox sayda müraciət var. Bir az sonra yenidən cəhd edin və ya birbaşa yazın: ' . cfg('contact_email');
        return $state;
    }

    $to      = (string) cfg('contact_email');
    $subject = 'Yeni müraciət — ' . cfg('site_name');
    $body    = "Ad: {$values['name']}\n"
             . "Soyad: {$values['surname']}\n"
             . "E-poçt: {$values['email']}\n"
             . "Telefon: {$values['phone']}\n\n"
             . "Mesaj:\n{$values['message']}\n";

    // SMTP ayarlanıbsa onun üzərindən, yoxsa hostinqin mail() funksiyası ilə
    $sent = mail_send($to, $subject, $body, ['reply_to' => $values['email']]);
    $ok   = $sent['ok'];

    if (cfg('log_contact')) {
        contact_log($ok ? 'SENT via ' . $sent['via'] : 'MAIL-FAILED: ' . $sent['error'], $body);
    }

    if ($ok) {
        $state['sent'] = true;
        $state['values'] = [];
        $state['issued'] = null;
    } else {
        $state['notice'] = 'Mesaj göndərilə bilmədi. Zəhmət olmasa bizimlə birbaşa əlaqə saxlayın: ' . cfg('contact_email');
    }

    return $state;
}

/** Sahənin əvvəlki dəyəri */
function contact_value(array $form, string $key): string
{
    return (string) ($form['values'][$key] ?? '');
}
