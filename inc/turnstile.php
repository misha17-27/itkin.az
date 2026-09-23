<?php
/**
 * Cloudflare Turnstile — botlara qarşı yoxlama (kapça).
 *
 * Açarlar və harada işlədiləcəyi admin panelinin «Təhlükəsizlik» bölməsindən
 * verilir (data/settings.php): turnstile_site, turnstile_secret,
 * turnstile_form (əlaqə forması), turnstile_login (panelə giriş).
 *
 * Cloudflare-in serverinə çatmaq mümkün olmayanda (şəbəkə xətası, Cloudflare-in
 * öz 5xx xətası) yoxlama keçmiş sayılır: formanı və girişi Cloudflare-in işindən
 * asılı etmirik — tələ sahəsi və cəhd limiti onsuz da işləyir.
 *
 * Bu güzəşti hücumçu özü yarada bilməməlidir. Əvvəl nəhəng cavab (onlarla MB)
 * göndərib bizim Cloudflare sorğumuzu vaxt aşımına salmaq olurdu — o zaman
 * yoxlama “keçmiş” sayılırdı. İndi cavabın uzunluğu və simvolları əvvəlcə
 * yoxlanılır, Cloudflare-in istənilən aydın cavabı (4xx, JSON olmayan mətn də)
 * isə “yox” sayılır.
 */

/** Turnstile cavabı adətən bir neçə yüz simvoldur; Cloudflare limiti 2048-dir */
const TURNSTILE_TOKEN_MAX = 2048;

const TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const TURNSTILE_SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

/** İki açar da verilibmi */
function turnstile_configured(): bool
{
    return trim((string) cfg('turnstile_site', '')) !== '' && trim((string) cfg('turnstile_secret', '')) !== '';
}

/** Yoxlama bu yerdə açıqdırmı: 'form' və ya 'login' */
function turnstile_on(string $where): bool
{
    return turnstile_configured() && !empty(cfg('turnstile_' . $where));
}

/** Formaya qoyulan pəncərə */
function turnstile_widget(): string
{
    // data-language vermirik: Cloudflare azərbaycan dilini tanımır, brauzerin dili götürülür
    return '<div class="cf-turnstile" data-sitekey="' . e((string) cfg('turnstile_site')) . '"></div>'
        . '<script src="' . TURNSTILE_SCRIPT . '" async defer></script>';
}

/**
 * Cloudflare-dən cavabı soruşur.
 *
 * @return array ['ok' => bool, 'reached' => bool, 'codes' => string[]]
 *   reached=false — Cloudflare-ə çatmaq olmadı (ok bu halda true sayılır)
 */
function turnstile_check(string $token, string $secret = '', string $ip = ''): array
{
    // Formatı pozulmuş cavab Cloudflare-ə heç göndərilmir — birbaşa “yox”
    if (!turnstile_token_ok($token)) {
        return ['ok' => false, 'reached' => true, 'codes' => ['invalid-input-response']];
    }
    $secret = $secret !== '' ? $secret : (string) cfg('turnstile_secret', '');
    $fields = ['secret' => $secret, 'response' => $token];
    if ($ip !== '') {
        $fields['remoteip'] = $ip;
    }

    $resp = turnstile_post(TURNSTILE_VERIFY, $fields);
    // Şəbəkə xətası və ya Cloudflare-in öz nasazlığı (5xx) — güzəşt
    if ($resp === null || $resp['code'] >= 500) {
        return ['ok' => true, 'reached' => false, 'codes' => []];
    }
    $data = json_decode($resp['body'], true);
    if (!is_array($data)) {
        // Cloudflare cavab verdi, amma anlaşılmayan — bunu “yox” sayırıq
        return ['ok' => false, 'reached' => true, 'codes' => ['bad-response']];
    }
    return [
        'ok'      => !empty($data['success']),
        'reached' => true,
        'codes'   => array_map('strval', (array) ($data['error-codes'] ?? [])),
    ];
}

/** Cavab tokeni: boş olmayan, 2048 simvola qədər, yalnız görünən ASCII */
function turnstile_token_ok(string $token): bool
{
    return $token !== '' && strlen($token) <= TURNSTILE_TOKEN_MAX && !preg_match('/[^\x21-\x7E]/', $token);
}

/**
 * Formadan gələn cavabı yoxlayır.
 *
 * Cavab boşdursa da Cloudflare-dən soruşuruq: Cloudflare işləmirsə pəncərə
 * heç yüklənməmiş ola bilər — o halda forma və giriş kilidlənməsin.
 */
function turnstile_verify(string $ip = ''): bool
{
    $token = $_POST['cf-turnstile-response'] ?? '';
    $token = is_string($token) ? $token : '';
    if ($token !== '' && !turnstile_token_ok($token)) {
        return false;   // nəhəng və ya qəribə simvollu cavab — güzəştə yol vermirik
    }
    $r = turnstile_check($token !== '' ? $token : 'bos-cavab', '', $ip);
    return $r['ok'] && ($token !== '' || !$r['reached']);
}

/**
 * Gizli açarı yoxlayır: saxta cavabla sorğu göndəririk. Açar səhvdirsə
 * Cloudflare “invalid-input-secret” deyir, düzdürsə yalnız cavabı bəyənmir.
 *
 * @return string '' — açar düzgündür; əks halda səbəb
 */
function turnstile_secret_problem(string $secret): string
{
    $r = turnstile_check('itkin-acar-yoxlamasi', $secret);
    if (!$r['reached']) {
        return 'Cloudflare-ə qoşulmaq olmadı — açarı yoxlamaq mümkün olmadı. Bir az sonra yenidən yoxlayın.';
    }
    if (in_array('invalid-input-secret', $r['codes'], true) || in_array('missing-input-secret', $r['codes'], true)) {
        return 'Gizli açar (Secret Key) səhvdir.';
    }
    return '';
}

/**
 * POST sorğusu: cURL varsa onunla, yoxsa stream ilə.
 * @return array|null ['code' => int, 'body' => string]; şəbəkə xətasında null
 */
function turnstile_post(string $url, array $fields): ?array
{
    $body = http_build_query($fields);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $out  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return is_string($out) && $code > 0 ? ['code' => $code, 'body' => $out] : null;
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => $body,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents($url, false, $ctx);
    if (!is_string($out)) {
        return null;
    }
    // $http_response_header file_get_contents tərəfindən doldurulur
    $code = 0;
    foreach ((array) ($http_response_header ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $m)) {
            $code = (int) $m[1];
        }
    }
    return $code > 0 ? ['code' => $code, 'body' => $out] : null;
}
