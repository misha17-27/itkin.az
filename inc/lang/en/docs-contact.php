<?php
/**
 * İngiliscə lüğət — “Əlaqə” səhifəsi, əlaqə formasının mesajları
 * (inc/contact.php), “Beynəlxalq sənədlər” və “Milli qanunvericilik” səhifələri.
 * Açar azərbaycanca mətnin özüdür, dəyər — ingiliscə tərcümə.
 * Təşkilata gedən məktub azərbaycanca qalır — burada yalnız ziyarətçinin gördüyü mətn var.
 * Sənəd səhifələrinin bütün mətni page_text() ilədir, ona görə burada açarı yoxdur.
 */

return [
    // əlaqə forması — sahələr (etiket, placeholder, xəta mesajlarında sahənin adı)
    'Ad'                    => 'Name',
    'Soyad'                 => 'Surname',
    'E-poçt'                => 'Email',
    'Telefon'               => 'Phone',
    'Mesaj'                 => 'Message',
    'Əlaqə - İtkin'         => 'Contact - İtkin',

    // əlaqə forması — nəticə və xəta mesajları
    'Müraciətiniz göndərildi. Təşəkkür edirik!'
        => 'Your message has been sent. Thank you!',
    'Formanın etibarlılıq müddəti bitmişdi. Zəhmət olmasa yenidən göndərin.'
        => 'The form has expired. Please submit it again.',
    'Adınızı yazın.'                => 'Enter your name.',
    'Soyadınızı yazın.'             => 'Enter your surname.',
    'Düzgün e-poçt ünvanı yazın.'   => 'Enter a valid email address.',
    'Telefon nömrənizi yazın.'      => 'Enter your phone number.',
    '{label} çox uzundur (ən çoxu {max} simvol).'
        => '{label} is too long (maximum {max} characters).',
    'Zəhmət olmasa sahələri düzgün doldurun: '
        => 'Please fill in the fields correctly: ',
    'Zəhmət olmasa “robot deyiləm” yoxlamasını keçin və yenidən göndərin.'
        => 'Please complete the “I am not a robot” check and submit the form again.',
    'Bu forma artıq göndərilib. Yeni müraciət üçün səhifəni yeniləyin.'
        => 'This form has already been submitted. To send a new message, please reload the page.',
    'Hazırda çox sayda müraciət var. Bir az sonra yenidən cəhd edin və ya birbaşa yazın: '
        => 'We are receiving a large number of messages at the moment. Please try again later or write to us directly: ',
    'Mesaj göndərilə bilmədi. Zəhmət olmasa bizimlə birbaşa əlaqə saxlayın: '
        => 'The message could not be sent. Please contact us directly: ',
];
