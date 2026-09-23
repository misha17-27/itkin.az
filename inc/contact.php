<?php
/**
 * Əlaqə formasının emalı / contact form handler.
 * Elementor formasının sahə adları saxlanılıb ki, markup dəyişməsin.
 */

const CONTACT_FIELDS = [
    'name'    => 'form_fields[name]',
    'surname' => 'form_fields[field_4df86f7]',
    'email'   => 'form_fields[email]',
    'phone'   => 'form_fields[field_5057311]',
    'message' => 'form_fields[field_71766c7]',
];

/**
 * Formanın CSRF nişanı.
 * Sessiya açmırıq ki, adi GET sorğusunda cookie və "no-store" başlıqları
 * yaranmasın — nişan gizli açar və vaxt pəncərəsi əsasında hesablanır.
 */
function contact_token(?int $window = null): string
{
    $secret = (string) cfg('form_secret');
    if ($secret === '') {
        // Açar təyin olunmayıbsa, quraşdırmaya bağlı sabit dəyərdən istifadə edirik
        $secret = 'itkin-' . __DIR__;
    }
    $window = $window ?? (int) floor(time() / 7200);
    return hash_hmac('sha256', 'contact:' . $window, $secret);
}

/** Nişan cari və ya əvvəlki pəncərəyə uyğun gəlirmi? */
function contact_token_valid(string $given): bool
{
    $now = (int) floor(time() / 7200);
    foreach ([$now, $now - 1] as $w) {
        if (hash_equals(contact_token($w), $given)) {
            return true;
        }
    }
    return false;
}

/**
 * Formanı emal edir və vəziyyəti qaytarır.
 *
 * @return array{sent:bool,errors:array<string,string>,values:array<string,string>,notice:string}
 */
function contact_handle(): array
{
    $state = ['sent' => false, 'errors' => [], 'values' => [], 'notice' => ''];

    $posted = $_POST['form_fields'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !is_array($posted)) {
        return $state;
    }

    $v = static function (string $key) use ($posted): string {
        $name = CONTACT_FIELDS[$key];
        $inner = substr($name, strlen('form_fields['), -1);
        return trim((string) ($posted[$inner] ?? ''));
    };

    $values = [
        'name'    => $v('name'),
        'surname' => $v('surname'),
        'email'   => $v('email'),
        'phone'   => $v('phone'),
        'message' => $v('message'),
    ];
    $state['values'] = $values;

    // Sadə spam tələsi — robotlar bu sahəni doldurur
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $state['sent'] = true;
        return $state;
    }

    if (!contact_token_valid((string) ($_POST['_token'] ?? ''))) {
        $state['notice'] = 'Formanın etibarlılıq müddəti bitib. Zəhmət olmasa səhifəni yeniləyib yenidən cəhd edin.';
        return $state;
    }

    if ($values['name'] === '')    { $state['errors']['name']    = 'Adınızı yazın.'; }
    if ($values['surname'] === '') { $state['errors']['surname'] = 'Soyadınızı yazın.'; }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $state['errors']['email'] = 'Düzgün e-poçt ünvanı yazın.';
    }
    if ($values['phone'] === '')   { $state['errors']['phone']   = 'Telefon nömrənizi yazın.'; }

    if ($state['errors']) {
        $state['notice'] = 'Zəhmət olmasa bütün məcburi sahələri doldurun.';
        return $state;
    }

    $to      = (string) cfg('contact_email');
    $subject = 'Yeni müraciət — ' . cfg('site_name');
    $body    = "Ad: {$values['name']}\n"
             . "Soyad: {$values['surname']}\n"
             . "E-poçt: {$values['email']}\n"
             . "Telefon: {$values['phone']}\n\n"
             . "Mesaj:\n{$values['message']}\n";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . cfg('site_name') . ' <' . cfg('contact_from') . '>',
        'Reply-To: ' . $values['email'],
    ];

    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

    if (cfg('log_contact')) {
        $file = (string) cfg('log_file');
        storage_dir();   // qoruyucu .htaccess yerində olsun — jurnalda şəxsi məlumat var
        @mkdir(dirname($file), 0775, true);
        @file_put_contents(
            $file,
            '[' . date('c') . '] ' . ($ok ? 'SENT' : 'MAIL-FAILED') . "\n" . $body . str_repeat('-', 40) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    if ($ok) {
        $state['sent'] = true;
        $state['values'] = [];
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
