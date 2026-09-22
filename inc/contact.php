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

function contact_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['contact_token'])) {
        $_SESSION['contact_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['contact_token'];
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
        contact_token();
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

    if (!hash_equals(contact_token(), (string) ($_POST['_token'] ?? ''))) {
        $state['notice'] = 'Sessiyanın vaxtı bitib. Zəhmət olmasa yenidən cəhd edin.';
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
        unset($_SESSION['contact_token']);
        contact_token();
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
