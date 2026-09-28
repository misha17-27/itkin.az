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

    // Saat qurşağı: paneldə yazılan tarix və saatlar (xəbərin tarixi, kim nə vaxt
    // əlavə edib, zibil qutusu) hostinqin ayarından asılı olmadan Bakı vaxtı ilə
    'timezone'      => 'Asia/Baku',

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
    // İdarə panelinin ünvanı: https://itkin.az/mguliyev/  (hərf, rəqəm, "-" və "_")
    // /admin/ ünvanı 404 qaytarır — panelin yerini təxmin etmək çətinləşsin
    'admin_path'     => 'mguliyev',
    'admin_user'     => 'admin',
    // Standart şifrə YOXDUR: repozitoriya açıqdır, burada yazılan hash hamıya məlum olardı.
    // Şifrə serverdə data/settings.php-də (və ya data/users.php-də) saxlanılır — bu fayllar
    // repozitoriyaya düşmür. Onlar olmayanda panelə giriş bağlıdır (README → «İlk giriş»).
    'admin_password' => '',

    // Əlaqə formasının CSRF açarı — quraşdırmadan sonra təsadüfi sətirlə əvəz edin
    'form_secret'   => '',

    // Google Analytics (Site Kit). Boş buraxsanız sayğac ümumiyyətlə yüklənmir.
    'ga_id'         => 'GT-T5MGVVRX',

    // Əlaqə formasının müraciətlərini fayla yazmaq
    'log_contact'   => false,
    'log_file'      => __DIR__ . '/storage/contact-log.php',   // PHP faylı: birbaşa açılsa da heç nə göstərmir
];
