<?php
/**
 * E-poçt göndərmə / mail sender.
 *
 * Ayarlarda SMTP server (smtp_host) göstərilibsə məktub birbaşa həmin serverə
 * göndərilir, göstərilməyibsə PHP-nin mail() funksiyasından istifadə olunur.
 *
 * Ayarlar (cfg):
 *   smtp_host    — server; boşdursa mail() işləyir
 *   smtp_port    — 465 (ssl), 587 (tls), 25 (şifrələməsiz); boşdursa növə görə seçilir
 *   smtp_secure  — 'ssl' (birbaşa TLS), 'tls' (STARTTLS) və ya '' (şifrələməsiz)
 *   smtp_user    — boş deyilsə serverə giriş edilir (AUTH PLAIN və ya LOGIN)
 *   smtp_pass
 *   smtp_verify  — serverin sertifikatını yoxlamaq (standart: bəli)
 *   contact_from — göndərən ünvan
 *   mail_from_name — göndərənin adı; boşdursa site_name
 *
 * mail_send() heç vaxt istisna atmır, nəticəni massiv kimi qaytarır:
 *   ['ok' => bool, 'via' => 'smtp'|'mail', 'error' => '', 'log' => [...]]
 * 'log' SMTP dialoqudur (sazlama üçün); giriş məlumatları orada "***" ilə gizlədilir.
 */

const MAIL_TIMEOUT = 15;   // saniyə — qoşulma və serverin hər cavabı üçün
const MAIL_MAX_BODY = 200000;   // bayt — daha böyük məktub göndərilmir (yaddaşı qorumaq üçün)

/**
 * Jurnalda və xəta mətnində görünməməli sətirlər (giriş məlumatları).
 * Bəzi serverlər qəbul etmədiyi əmri cavabda geri qaytarır — orada da gizlədilir.
 */
function mail_secrets(?array $add = null): array
{
    static $list = [];
    if ($add !== null) {
        $list = $add === [] ? [] : array_merge($list, array_filter($add, 'strlen'));
    }
    return $list;
}

/** Mətndəki gizli dəyərləri *** ilə əvəz edir */
function mail_redact(string $text): string
{
    foreach (mail_secrets() as $secret) {
        $text = str_replace($secret, '***', $text);
    }
    return $text;
}

/**
 * Məktub göndərir.
 *
 * @param array $opt reply_to, from, from_name, host, port, secure, user, pass, verify —
 *                   verilən dəyər ayarlardakının (cfg) yerinə keçir
 */
function mail_send(string $to, string $subject, string $body, array $opt = []): array
{
    $log = [];
    $via = 'mail';

    if (strlen($body) > MAIL_MAX_BODY) {
        return ['ok' => false, 'via' => $via, 'error' => 'Məktub çox böyükdür.', 'log' => []];
    }
    mail_secrets([]);   // əvvəlki göndərişdən qalan siyahı təmizlənir

    // PHP xəbərdarlıqları (məsələn OpenSSL-in sertifikat xətası) ekrana çıxmasın —
    // toplanır və xəta mətninə əlavə olunur
    mail_warnings();
    set_error_handler(static function ($no, $msg) {
        // "stream_socket_client(): " kimi funksiya adı istifadəçiyə lazım deyil
        mail_warnings((string) preg_replace('/^[a-z_]+\(\):\s*/i', '', mail_clean((string) $msg)));
        return true;
    });

    try {
        $error = mail_deliver($to, $subject, $body, $opt, $via, $log);
    } catch (Throwable $e) {
        $error = 'Məktub göndərilmədi, gözlənilməz xəta: ' . mail_clean($e->getMessage());
    } finally {
        restore_error_handler();
        mail_warnings();
    }

    return ['ok' => $error === '', 'via' => $via, 'error' => $error, 'log' => $log];
}

/**
 * Toplanmış PHP xəbərdarlıqları. Mətn verilsə siyahıya əlavə edir,
 * verilməsə siyahını qaytarıb təmizləyir.
 */
function mail_warnings(?string $add = null): array
{
    static $list = [];
    if ($add !== null) {
        if ($add !== '' && !in_array($add, $list, true)) {
            $list[] = $add;
        }
        return [];
    }
    $out  = $list;
    $list = [];
    return $out;
}

/** Əsas iş: yoxlama, məktubun qurulması və göndərilməsi. Uğurda '' qaytarır. */
function mail_deliver(string $to, string $subject, string $body, array $opt, string &$via, array &$log): string
{
    $c   = mail_config($opt);
    $via = $c['host'] === '' ? 'mail' : 'smtp';

    // Ünvanlar olduğu kimi yoxlanılır: içində sətir sonu olan ünvan düzəldilmir, rədd edilir
    $to      = trim($to);
    $replyTo = trim((string) ($opt['reply_to'] ?? ''));
    if (!mail_valid($to)) {
        return mail_bad_address('Alıcı ünvanı', $to);
    }
    if (!mail_valid($c['from'])) {
        return mail_bad_address('Göndərən ünvanı', $c['from']) . ' Ayarlarda “Göndərən ünvan” sahəsini yoxlayın.';
    }
    if ($replyTo !== '' && !mail_valid($replyTo)) {
        return mail_bad_address('Cavab ünvanı (Reply-To)', $replyTo);
    }
    if ($c['error'] !== '') {
        return $c['error'];
    }

    $subjectValue = mail_subject($subject);
    $headers = [
        'Date'       => 'Date: ' . date('r'),
        'Message-ID' => 'Message-ID: ' . mail_message_id($c['from']),
        'From'       => mail_address('From', $c['from'], $c['from_name']),
        'To'         => 'To: ' . $to,
        'Reply-To'   => $replyTo !== '' ? 'Reply-To: ' . $replyTo : '',
        'Subject'    => 'Subject: ' . $subjectValue,
        'MIME'       => 'MIME-Version: 1.0',
        'Type'       => 'Content-Type: text/plain; charset=UTF-8',
        'Encoding'   => 'Content-Transfer-Encoding: base64',
    ];
    $headers = array_filter($headers, 'strlen');
    $encoded = mail_body($body);

    if ($via === 'mail') {
        // mail() alıcını və mövzunu özü yazır — başlıqlarda təkrarlanmasın
        unset($headers['To'], $headers['Subject']);
        return mail_via_php($to, $subjectValue, $encoded, implode("\r\n", $headers), $log);
    }

    // Zərf göndərəni: SMTP istifadəçisi e-poçtdursa o (serverlər çox vaxt bunu tələb edir)
    $envelope = mail_valid($c['user']) ? $c['user'] : $c['from'];
    $message  = implode("\r\n", $headers) . "\r\n\r\n" . $encoded;

    return mail_smtp($c, $envelope, $to, $message, $log);
}

/** Ayarlar: $opt-dakı dəyər cfg()-nin üstünə yazılır */
function mail_config(array $opt): array
{
    $pick = static function (string $key, string $setting, $default) use ($opt) {
        if (array_key_exists($key, $opt) && $opt[$key] !== null) {
            return $opt[$key];
        }
        return cfg($setting, $default);
    };

    $c = [
        'host'   => trim((string) $pick('host', 'smtp_host', '')),
        'port'   => (int) $pick('port', 'smtp_port', 0),
        'secure' => strtolower(trim((string) $pick('secure', 'smtp_secure', ''))),
        'user'   => trim((string) $pick('user', 'smtp_user', '')),
        'pass'   => (string) $pick('pass', 'smtp_pass', ''),
        'verify' => (bool) $pick('verify', 'smtp_verify', true),
        'from'   => trim((string) $pick('from', 'contact_from', '')),
        'error'  => '',
    ];

    // Ad: açıq verilibsə o (boş da ola bilər), yoxsa mail_from_name, o da boşdursa sayt adı
    if (array_key_exists('from_name', $opt) && $opt['from_name'] !== null) {
        $c['from_name'] = (string) $opt['from_name'];
    } else {
        $c['from_name'] = (string) cfg('mail_from_name', '');
        if (trim($c['from_name']) === '') {
            $c['from_name'] = (string) cfg('site_name', '');
        }
    }

    // Göndərən ünvan boş qalıbsa (məsələn ayarlarda silinib) məktub ümumiyyətlə
    // getməməlidir deyə yox — SMTP istifadəçisi, o da yoxdursa config.php-dəki
    // standart ünvan götürülür. Yoxsa saytdakı əlaqə forması susaraq işləməz.
    if ($c['from'] === '') {
        $c['from'] = mail_valid($c['user']) ? $c['user'] : trim((string) (cfg_default('contact_from') ?? ''));
    }

    $aliases = ['starttls' => 'tls', 'none' => '', 'no' => ''];
    $c['secure'] = $aliases[$c['secure']] ?? $c['secure'];

    if ($c['host'] === '') {
        return $c;
    }
    if (!in_array($c['secure'], ['', 'ssl', 'tls'], true)) {
        $c['error'] = 'SMTP şifrələmə növü düzgün deyil: ' . mail_clean($c['secure']) . ' (ssl, tls və ya boş olmalıdır).';
    } elseif (!preg_match('/^(?:\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9._-]+)$/', $c['host'])) {
        $c['error'] = 'SMTP server ünvanı düzgün deyil: ' . mail_clean($c['host']) . ' (məsələn smtp.gmail.com, “ssl://” olmadan).';
    }
    if ($c['port'] <= 0) {
        $c['port'] = $c['secure'] === 'ssl' ? 465 : ($c['secure'] === 'tls' ? 587 : 25);
    } elseif ($c['port'] > 65535) {
        $c['error'] = 'SMTP port düzgün deyil: ' . $c['port'] . '.';
    }
    return $c;
}

function mail_valid(string $addr): bool
{
    return $addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false;
}

function mail_bad_address(string $label, string $addr): string
{
    return $addr === ''
        ? $label . ' göstərilməyib.'
        : $label . ' düzgün deyil: ' . mail_clean($addr) . '.';
}

/**
 * Başlıq dəyərini təmizləyir: sətir sonu və digər idarəedici simvollar
 * boşluqla əvəz olunur — başlığa yeni sətir (məsələn “Bcc:”) əlavə etmək olmasın.
 */
function mail_clean(string $s): string
{
    return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', mail_utf8($s)));
}

/** Səhv UTF-8 baytları “?” olur (serverin cavabı və ya Windows-un xəta mətni) */
function mail_utf8(string $s): string
{
    if (preg_match('//u', $s)) {
        return $s;
    }
    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    return (string) preg_replace('/[\x80-\xFF]/', '?', $s);
}

/** Mətn kodlanmadan başlığa yazıla bilərmi (yalnız çap olunan ASCII, “=?” yoxdur) */
function mail_is_plain(string $s): bool
{
    return !preg_match('/[^\x20-\x7E]/', $s) && strpos($s, '=?') === false;
}

/** Mövzu: ASCII və qısadırsa olduğu kimi, yoxsa RFC 2047 */
function mail_subject(string $subject): string
{
    $subject = mail_clean($subject);
    if (mail_is_plain($subject) && strlen('Subject: ' . $subject) <= 76) {
        return $subject;
    }
    return mail_encode_words($subject, strlen('Subject: '));
}

/**
 * RFC 2047 kodlaması: =?UTF-8?B?...?= sözləri "\r\n " ilə bükülür.
 * Hər söz 75 simvoldan, onu daşıyan sətir 76 simvoldan uzun olmur.
 * UTF-8 hərfi iki sözün arasında bölünmür — yoxsa poçt proqramı onu “?” göstərir.
 *
 * @param int $used birinci sətirdə artıq tutulmuş yer ("Subject: " = 9)
 */
function mail_encode_words(string $text, int $used = 0): string
{
    // Sözün 12 simvolu =?UTF-8?B? və ?= üçündür; base64-ün hər 4 simvolu 3 bayt daşıyır
    $limit = max(12, intdiv(min(75, 76 - $used) - 12, 4) * 3);
    $words = [];
    $chunk = '';
    preg_match_all('/./su', mail_utf8($text), $m);
    foreach ($m[0] as $char) {
        if ($chunk !== '' && strlen($chunk) + strlen($char) > $limit) {
            // Mümkünsə boşluqdan sonra bölürük: sözlər arasındakı boşluğu atmayan
            // (RFC 2047-yə tam əməl etməyən) proqramlarda da söz ortadan qırılmasın
            $rest = '';
            $cut  = strrpos($chunk, ' ');
            if ($cut !== false && $cut + 1 < strlen($chunk) && $cut + 1 >= strlen($chunk) / 2) {
                $rest  = substr($chunk, $cut + 1);
                $chunk = substr($chunk, 0, $cut + 1);
            }
            $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
            $chunk   = $rest;
            $limit   = 45;   // növbəti sətirlər: 1 boşluq + 75 simvolluq söz (60 simvol base64)
        }
        $chunk .= $char;
    }
    if ($chunk !== '' || !$words) {
        $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
    }
    return implode("\r\n ", $words);
}

/** Ünvan başlığı: From: "Ad" <ünvan>; ad ASCII deyilsə RFC 2047 ilə kodlanır */
function mail_address(string $header, string $addr, string $name): string
{
    $line = $header . ': ';
    $name = mail_clean($name);
    if ($name === '') {
        return $line . $addr;
    }
    $value = mail_is_plain($name)
        ? '"' . addcslashes($name, '"\\') . '"'
        : mail_encode_words($name, strlen($line));

    // Ünvan sonuncu sətirə sığmırsa yeni sətrə keçirilir
    $nl   = strrpos($value, "\n");
    $tail = $nl === false ? strlen($line . $value) : strlen($value) - $nl - 1;
    $sep  = $tail + strlen($addr) + 3 > 76 ? "\r\n " : ' ';
    return $line . $value . $sep . '<' . $addr . '>';
}

/** Message-ID göndərənin domeni ilə: <tarix.təsadüfi@itkin.az> */
function mail_message_id(string $from): string
{
    try {
        $rand = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $rand = str_replace('.', '', uniqid('', true));
    }
    $at = strrpos($from, '@');
    return '<' . date('YmdHis') . '.' . $rand . '@' . substr($from, $at === false ? 0 : $at + 1) . '>';
}

/** Mətn CRLF sətir sonları ilə, base64 kodlu, 76 simvolluq sətirlərlə */
function mail_body(string $body): string
{
    $body = (string) preg_replace('/\r\n|\r|\n/', "\r\n", $body);
    if ($body === '') {
        return '';
    }
    return chunk_split(base64_encode($body), 76, "\r\n");
}

/** SMTP ayarları yoxdursa — PHP-nin mail() funksiyası */
function mail_via_php(string $to, string $subject, string $body, string $headers, array &$log): string
{
    if (!function_exists('mail')) {
        return 'Serverdə mail() funksiyası söndürülüb — ayarlarda SMTP serveri göstərin.';
    }
    $log[] = '* mail(): ' . $to;
    mail_warnings();
    if (mail($to, $subject, $body, $headers)) {
        return '';
    }
    $warn = mail_warnings();
    return 'mail() məktubu qəbul etmədi' . ($warn ? ': ' . implode('; ', $warn) : '')
        . '. Ayarlarda SMTP serveri göstərmək məsləhətdir.';
}

/* ---------------------------------------------------------------- SMTP */

/** Bağlantı açır, dialoqu aparır və bağlantını hər halda bağlayır */
function mail_smtp(array $c, string $envelope, string $to, string $message, array &$log): string
{
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $ctx    = stream_context_create(['ssl' => [
        'verify_peer'      => $c['verify'],
        'verify_peer_name' => $c['verify'],
        'peer_name'        => trim($c['host'], '[]'),
        'crypto_method'    => mail_tls_method(),
    ]]);

    $log[] = '* ' . $remote . ($c['secure'] === 'tls' ? ' (STARTTLS)' : '')
           . ($c['verify'] ? '' : ' — sertifikat yoxlanmır');
    mail_warnings();
    $errno  = 0;
    $errstr = '';
    $fp = stream_socket_client($remote, $errno, $errstr, MAIL_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
    if (!is_resource($fp)) {
        $error = mail_connect_error($c, (int) $errno, (string) $errstr, mail_warnings());
        $log[] = '! ' . $error;
        return $error;
    }

    $alive = false;
    try {
        if ($c['secure'] === 'ssl') {
            $log[] = '* ' . mail_tls_info($fp);
        }
        $error = mail_smtp_session($fp, $c, $envelope, $to, $message, $log, $alive);
        // Server hələ cavab verirsə nəzakətlə ayrılırıq (nəticəyə təsir etmir)
        if ($alive) {
            mail_smtp_cmd($fp, 'QUIT', [221], $log);
        }
    } finally {
        fclose($fp);
    }
    if ($error !== '') {
        $log[] = '! ' . $error;
    }
    return $error;
}

/**
 * SMTP dialoqu. Uğurda '' qaytarır.
 * $alive — bağlantı sağdırmı (QUIT göndərmək olarmı).
 */
function mail_smtp_session($fp, array $c, string $envelope, string $to, string $message, array &$log, bool &$alive): string
{
    $r = mail_smtp_read($fp, $log);
    $alive = $r['code'] > 0;
    if ($r['code'] !== 220) {
        return mail_smtp_fail('SMTP server bağlantını qəbul etmədi', $r)
            . ($r['code'] === 0 ? ' Port və şifrələmə növünü (ssl/tls) yoxlayın.' : '');
    }

    $name = mail_ehlo_name();
    $r = mail_smtp_ehlo($fp, $name, $log);
    $alive = $r['code'] > 0;
    if (!$r['ok']) {
        return mail_smtp_fail('Server EHLO əmrini qəbul etmədi', $r);
    }

    if ($c['secure'] === 'tls') {
        if (!isset($r['ext']['STARTTLS'])) {
            return 'Server STARTTLS dəstəkləmir — şifrələmə növünü “ssl” seçin və ya boş buraxın.';
        }
        $r = mail_smtp_cmd($fp, 'STARTTLS', [220], $log);
        $alive = $r['code'] > 0;
        if (!$r['ok']) {
            return mail_smtp_fail('Server STARTTLS əmrini qəbul etmədi', $r);
        }
        mail_warnings();
        $alive = false;   // şifrələmə yarımçıq qalsa bağlantı yararsızdır
        if (stream_socket_enable_crypto($fp, true, mail_tls_method()) !== true) {
            $warn = mail_warnings();
            return 'TLS şifrələməsi qurulmadı' . ($warn ? ': ' . implode('; ', $warn) : '.')
                . mail_tls_hint($warn);
        }
        $log[] = '* ' . mail_tls_info($fp);

        // Şifrələmədən sonra server imkanlarını yenidən elan edir (AUTH adətən indi görünür)
        $r = mail_smtp_ehlo($fp, $name, $log);
        $alive = $r['code'] > 0;
        if (!$r['ok']) {
            return mail_smtp_fail('Server EHLO əmrini qəbul etmədi', $r);
        }
    }

    if ($c['user'] !== '') {
        $error = mail_smtp_auth($fp, $c, $r['ext'], $log, $alive);
        if ($error !== '') {
            return $error;
        }
    }

    $steps = [
        ['MAIL FROM:<' . $envelope . '>', [250], 'Göndərən ünvan qəbul edilmədi'],
        ['RCPT TO:<' . $to . '>', [250, 251], 'Alıcı qəbul edilmədi'],
        ['DATA', [354], 'Server DATA əmrini qəbul etmədi'],
    ];
    foreach ($steps as $step) {
        $r = mail_smtp_cmd($fp, $step[0], $step[1], $log);
        $alive = $r['code'] > 0;
        if (!$r['ok']) {
            return mail_smtp_fail($step[2], $r);
        }
    }

    $data = mail_dot_stuff($message);
    $log[] = '> [məktub: ' . strlen($data) . ' bayt]';
    $log[] = '> .';
    if (!mail_smtp_write($fp, $data . ".\r\n")) {
        $alive = false;
        return 'Məktubu göndərmək alınmadı: server bağlantını kəsdi.';
    }
    $r = mail_smtp_read($fp, $log);
    $alive = $r['code'] > 0;
    if ($r['code'] !== 250) {
        return mail_smtp_fail('Server məktubu qəbul etmədi', $r);
    }
    return '';
}

/** Giriş: EHLO-da elan olunan üsulla; ikisi də varsa PLAIN */
function mail_smtp_auth($fp, array $c, array $ext, array &$log, bool &$alive): string
{
    // Şifrə açıq kanalda göndərilmir: SSL/TLS və ya STARTTLS olmalıdır.
    // (Yalnız şifrələməsiz daxili relay üçün ayarlarda smtp_allow_plain = true.)
    $meta = stream_get_meta_data($fp);
    if (empty($meta['crypto']) && !cfg('smtp_allow_plain')) {
        return 'Giriş məlumatları şifrələnməmiş bağlantı ilə göndərilmir — şifrələmə növünü SSL/TLS və ya STARTTLS seçin.';
    }

    $methods = preg_split('/\s+/', strtoupper($ext['AUTH'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    mail_secrets([
        base64_encode("\0" . $c['user'] . "\0" . $c['pass']),
        base64_encode($c['user']),
        base64_encode($c['pass']),
        strlen($c['pass']) >= 6 ? $c['pass'] : '',   // qısa şifrə jurnalda adi hərfləri də gizlədərdi
    ]);

    if (in_array('PLAIN', $methods, true)) {
        $secret = base64_encode("\0" . $c['user'] . "\0" . $c['pass']);
        $r = mail_smtp_cmd($fp, 'AUTH PLAIN ' . $secret, [235], $log, 'AUTH PLAIN ***');
    } elseif (in_array('LOGIN', $methods, true)) {
        $r = mail_smtp_cmd($fp, 'AUTH LOGIN', [334], $log);
        if ($r['ok']) {
            $r = mail_smtp_cmd($fp, base64_encode($c['user']), [334], $log, '***');
        }
        if ($r['ok']) {
            $r = mail_smtp_cmd($fp, base64_encode($c['pass']), [235], $log, '***');
        }
    } elseif (!$methods) {
        return 'Server giriş (AUTH) təklif etmir'
            . ($c['secure'] === '' ? ' — şifrələmə növünü “tls” və ya “ssl” seçin.' : '.');
    } else {
        return 'Server yalnız bu giriş üsullarını dəstəkləyir: ' . implode(', ', $methods) . ' (PLAIN və ya LOGIN lazımdır).';
    }

    $alive = $r['code'] > 0;
    return $r['ok'] ? '' : mail_smtp_fail('Giriş rədd edildi', $r);
}

/** EHLO göndərir və elan olunan imkanları (STARTTLS, AUTH ...) ayırır */
function mail_smtp_ehlo($fp, string $name, array &$log): array
{
    $r = mail_smtp_cmd($fp, 'EHLO ' . $name, [250], $log);
    $r['ext'] = [];
    if ($r['ok']) {
        foreach (array_slice($r['lines'], 1) as $line) {
            // "AUTH PLAIN LOGIN" və köhnə yazılış "AUTH=PLAIN LOGIN"
            if (preg_match('/^([A-Za-z0-9-]+)(?:[ =](.*))?$/', trim(substr($line, 4)), $m)) {
                $key = strtoupper($m[1]);
                $r['ext'][$key] = trim(($r['ext'][$key] ?? '') . ' ' . ($m[2] ?? ''));
            }
        }
    }
    return $r;
}

/** Əmri göndərir və cavabı yoxlayır. $shown — jurnala yazılan (gizli) variant. */
function mail_smtp_cmd($fp, string $cmd, array $expect, array &$log, ?string $shown = null): array
{
    $log[] = '> ' . ($shown ?? $cmd);
    if (!mail_smtp_write($fp, $cmd . "\r\n")) {
        return ['code' => 0, 'text' => '', 'lines' => [], 'timeout' => false, 'ok' => false];
    }
    $r = mail_smtp_read($fp, $log);
    $r['ok'] = in_array($r['code'], $expect, true);
    return $r;
}

/** Hissə-hissə yazır (şifrəli axın bir dəfəyə hamısını yazmaya bilər) */
function mail_smtp_write($fp, string $data): bool
{
    $len  = strlen($data);
    $done = 0;
    while ($done < $len) {
        $n = fwrite($fp, substr($data, $done, 8192));
        if ($n === false || $n === 0) {
            return false;
        }
        $done += $n;
    }
    return true;
}

/**
 * Serverin cavabını oxuyur. Çoxsətirli cavabda 4-cü simvol “-” olduqca davam edir.
 * Bütün cavab üçün MAIL_TIMEOUT saniyə gözlənilir; cavab yoxdursa code = 0.
 */
function mail_smtp_read($fp, array &$log): array
{
    $lines    = [];
    $deadline = microtime(true) + MAIL_TIMEOUT;
    while (count($lines) < 100) {
        $left = $deadline - microtime(true);
        if ($left <= 0) {
            return mail_smtp_reply($lines, 0, true);
        }
        stream_set_timeout($fp, (int) $left, (int) (($left - floor($left)) * 1000000));
        $line = fgets($fp, 2048);
        if ($line === false || $line === '') {
            $meta = stream_get_meta_data($fp);
            return mail_smtp_reply($lines, 0, !empty($meta['timed_out']));
        }
        $line = mail_redact(mail_clean(rtrim($line, "\r\n")));
        $log[] = '< ' . $line;
        $lines[] = $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    $last = (string) end($lines);
    $code = preg_match('/^\d{3}/', $last) ? (int) substr($last, 0, 3) : -1;
    return mail_smtp_reply($lines, $code, false);
}

function mail_smtp_reply(array $lines, int $code, bool $timeout): array
{
    $text = implode(' ', $lines);
    if (strlen($text) > 500) {
        $text = substr($text, 0, 500) . '…';
    }
    return ['code' => $code, 'text' => mail_utf8($text), 'lines' => $lines, 'timeout' => $timeout, 'ok' => false];
}

/** Xəta mətni: “Giriş rədd edildi: 535 5.7.8 Authentication failed” */
function mail_smtp_fail(string $what, array $r): string
{
    if ($r['code'] === 0) {
        return $what . ': ' . ($r['timeout']
            ? 'server ' . MAIL_TIMEOUT . ' saniyə ərzində cavab vermədi.'
            : 'server bağlantını kəsdi.');
    }
    if ($r['code'] < 0) {
        return $what . ': server SMTP dilində cavab vermədi (' . $r['text'] . ').';
    }
    return $what . ': ' . $r['text'];
}

/** DATA üçün: nöqtə ilə başlayan sətirə bir nöqtə də əlavə olunur (RFC 5321, 4.5.2) */
function mail_dot_stuff(string $data): string
{
    $data = (string) preg_replace('/\r\n|\r|\n/', "\r\n", $data);
    $data = (string) preg_replace('/^\./m', '..', $data);
    if ($data !== '' && substr($data, -2) !== "\r\n") {
        $data .= "\r\n";
    }
    return $data;
}

/** TLS 1.2 və daha yenisi */
function mail_tls_method(): int
{
    $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    }
    return $method;
}

/** Jurnal üçün: “TLS açıldı (TLSv1.3, TLS_AES_256_GCM_SHA384)” */
function mail_tls_info($fp): string
{
    $meta   = stream_get_meta_data($fp);
    $crypto = isset($meta['crypto']) && is_array($meta['crypto']) ? $meta['crypto'] : [];
    $parts  = array_filter([$crypto['protocol'] ?? '', $crypto['cipher_name'] ?? '']);
    return 'TLS açıldı' . ($parts ? ' (' . implode(', ', $parts) . ')' : '');
}

/** Sertifikat xətasında nə etmək lazım olduğunu izah edir */
function mail_tls_hint(array $warn): string
{
    $all = strtolower(implode(' ', $warn));
    if (strpos($all, 'certificate') !== false || strpos($all, 'peer') !== false) {
        return ' Serverin sertifikatı yoxlamadan keçmədi — server ünvanının sertifikatdakı adla eyni olduğunu yoxlayın.';
    }
    return '';
}

/** Qoşulma xətasını başa düşülən mətnə çevirir */
function mail_connect_error(array $c, int $errno, string $errstr, array $warn): string
{
    $where = $c['host'] . ':' . $c['port'];

    // Təfərrüat: PHP-nin xəbərdarlıqları, təkrarlanmadan
    $details = [];
    foreach ($warn as $w) {
        if (stripos($w, 'Unable to connect to') === 0 || stripos($w, 'Failed to enable crypto') === 0) {
            continue;
        }
        $details[] = $w;
    }
    $errstr = mail_clean($errstr);
    if ($errstr !== '' && strpos(implode(' ', $details), $errstr) === false) {
        $details[] = $errstr;
    }
    $detail = $details ? ' (' . implode('; ', $details) . ')' : '';
    $all    = strtolower(implode(' ', $details));

    if (strpos($all, 'getaddrinfo') !== false || strpos($all, 'name or service not known') !== false) {
        return 'SMTP server tapılmadı (DNS): ' . $c['host'] . $detail;
    }
    if (strpos($all, 'ssl') !== false || strpos($all, 'tls') !== false || strpos($all, 'certificate') !== false) {
        return 'SMTP serverlə TLS bağlantısı qurulmadı: ' . $where . $detail . mail_tls_hint($details)
            . ' Şifrələmə növünü (ssl/tls) və portu yoxlayın.';
    }
    if (in_array($errno, [110, 10060], true) || strpos($all, 'timed out') !== false) {
        return 'SMTP serverə qoşulmaq üçün vaxt bitdi (' . MAIL_TIMEOUT . ' saniyə): ' . $where . $detail;
    }
    if (in_array($errno, [111, 10061], true) || strpos($all, 'refused') !== false) {
        return 'SMTP server bağlantını rədd etdi: ' . $where . ' — port nömrəsini yoxlayın' . $detail;
    }
    return 'SMTP serverə qoşulmaq alınmadı: ' . $where . $detail;
}

/** EHLO-da təqdim olunan ad: saytın və ya serverin tam domen adı */
function mail_ehlo_name(): string
{
    foreach ([$_SERVER['SERVER_NAME'] ?? '', gethostname()] as $name) {
        $name = strtolower(trim((string) $name));
        if (preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', $name)) {
            return $name;
        }
    }
    return 'localhost.localdomain';
}
