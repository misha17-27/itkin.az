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

    // Google Analytics (Site Kit). Boş buraxsanız sayğac ümumiyyətlə yüklənmir.
    'ga_id'         => 'GT-T5MGVVRX',

    // Əlaqə formasının müraciətlərini fayla yazmaq
    'log_contact'   => false,
    'log_file'      => __DIR__ . '/storage/contact.log',
];
