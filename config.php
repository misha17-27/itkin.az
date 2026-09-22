<?php
/**
 * itkin.az — konfiqurasiya / configuration
 */

return [
    // Saytın əsas ünvanı. Boş buraxılsa avtomatik təyin olunur.
    'site_url'      => '',

    'site_name'     => 'İtkin',
    'site_tagline'  => 'Qarabağ İtkin Ailələri',
    'locale'        => 'az',

    // Əlaqə formasının göndəriləcəyi e-poçt
    'contact_email' => 'info@itkin.az',
    'contact_from'  => 'no-reply@itkin.az',

    // Bir səhifədə göstərilən yazı sayı
    'per_page'      => [
        'xeberler'  => 12,
        'category'  => 10,
        'kitabxana' => 12,
        'itkinlr'   => 10,
    ],

    // ---------------------------------------------------------------- admin
    // Admin panelinə giriş: /admin/
    // Şifrəni dəyişmək üçün panelin "Ayarlar" bölməsindən istifadə edin
    // və ya burada password_hash('yeni-şifrə', PASSWORD_DEFAULT) nəticəsini yazın.
    'admin_user'     => 'admin',
    // Standart şifrə: itkin2026 — İLK GİRİŞDƏN SONRA MÜTLƏQ DƏYİŞİN
    'admin_password' => '$2y$10$mPlw9fbIJo0W4CN7wF7N4ucWKNuTRwspN0guCEmDL5EimguQrDsMG',

    // Əlaqə formasının CSRF açarı — quraşdırmadan sonra təsadüfi sətirlə əvəz edin
    'form_secret'   => '',

    // Google Analytics (Site Kit). Boş buraxsanız sayğac ümumiyyətlə yüklənmir.
    'ga_id'         => 'GT-T5MGVVRX',

    // Əlaqə formasının müraciətlərini fayla yazmaq
    'log_contact'   => false,
    'log_file'      => __DIR__ . '/storage/contact.log',
];
