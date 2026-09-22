# itkin.az — PHP versiyası

[itkin.az](https://itkin.az/) saytının (WordPress + Elementor Pro) təmiz PHP-də surəti.
Verilənlər bazası, WordPress və plaginlər tələb olunmur — yalnız PHP 7.4+ olan istənilən
paylaşımlı hostinqdə (cPanel) işləyir.

Dizayn, markup və CSS orijinal saytdan olduğu kimi götürülüb; məzmun isə `data/`
qovluğundakı PHP massivlərində saxlanılır və şablonlar tərəfindən dinamik çap olunur.

---

## Quraşdırma (cPanel)

1. Repozitoriyanı `public_html/` qovluğuna yükləyin (cPanel → Git Version Control
   və ya File Manager ilə arxiv şəklində).
2. `config.php` faylını açıb saytın ünvanını və e-poçtu yoxlayın:

   ```php
   'site_url'      => '',                 // boş = avtomatik təyin olunur
   'contact_email' => 'info@itkin.az',    // formanın gedəcəyi ünvan
   'contact_from'  => 'no-reply@itkin.az' // göndərən ünvan (domeninizdə mövcud olmalıdır)
   'ga_id'         => 'GT-T5MGVVRX',      // Google Analytics; boş buraxsanız yüklənmir
   ```

3. `mod_rewrite` aktiv olmalıdır — cPanel-də standart olaraq aktivdir.
   `.htaccess` faylı repozitoriyadadır.
4. Sayt alt qovluqda yerləşirsə (məsələn `public_html/itkin/`), `.htaccess`-dəki
   `RewriteBase` sətrini uyğunlaşdırın: `RewriteBase /itkin/`.

Başqa heç bir addım lazım deyil.

### Yoxlama siyahısı

| Nə | Necə yoxlanılır |
|---|---|
| Ünvanlar işləyir | `/xeberler/`, `/itkinlr/`, `/category/tedbirler/` açılır |
| Şəkillər görünür | Ana səhifədə fon videosu və karusellər işləyir |
| Forma göndərir | `/elaqe/` səhifəsindən test müraciəti göndərin |

Forma işləmirsə, `config.php`-də `'log_contact' => true` edin — müraciətlər
`storage/contact.log` faylına da yazılacaq.

---

## Lokal işə salma

```bash
php -S localhost:8099 -t . router.php
```

`router.php` yalnız PHP-nin daxili serveri üçündür; hostinqdə `.htaccess` işləyir.

---

## Quruluş

```
index.php              ön nəzarətçi — bütün sorğular buradan keçir
config.php             sayt ayarları (ünvan, e-poçt, səhifələmə)
.htaccess              Apache üçün yönləndirmə və keşləmə

inc/
  helpers.php          köməkçi funksiyalar (url, asset, tarix, şəkil)
  data.php             məzmunun oxunması, filtrlənməsi, səhifələnməsi
  render.php           şablonların birləşdirilməsi, CSS/JS siyahıları
  head.php             <head> hissəsi və meta teqlər
  header-home.php      ana səhifənin başlığı (Elementor şablonu #50)
  header-main.php      daxili səhifələrin başlığı (#334)
  footer.php           altlıq və skriptlər (#245)
  nav.php              menyunun çapı və aktiv bəndin işarələnməsi
  page.php             statik səhifələr üçün ümumi məntiq
  schema.php           Schema.org JSON-LD
  contact.php          əlaqə formasının emalı

templates/
  home.php             ana səhifə
  page-*.php           statik səhifələr
  single-post.php      tək xəbər
  single-kitabxana.php tək kitab
  single-itkinlr.php   itkin şəxsin səhifəsi
  archive-*.php        kateqoriya və siyahı səhifələri
  404.php
  *.body.php           orijinal Elementor markupu (avtomatik çıxarılıb)
  partials/            kartlar, yol göstəricisi, səhifələmə

data/                  məzmun (PHP massivləri)
  posts.php            61 xəbər
  kitabxana.php        7 kitab
  itkinlr.php          13 itkin şəxs
  categories.php       6 kateqoriya
  pages.php            8 statik səhifənin meta məlumatı
  archives.php         itkinlr / kitabxana-blog arxivlərinin meta məlumatı
  menu.php             əsas menyu
  menu-footer-1.php    altlıqdakı birinci menyu
  menu-footer-2.php    altlıqdakı ikinci menyu

Hər sətirdə məzmundan başqa iki sahə də var:
`head_meta` — Open Graph / Twitter teqləri orijinaldakı ardıcıllıqla,
`schema` — Yoast-ın JSON-LD qrafı (sayt ünvanı `{{SITE}}` nişanı ilə, ona görə
domen dəyişsə belə struktur məlumat düzgün qalır).

assets/
  css/                 orijinalda inline olan üslublar + custom.css
  js/                  mobile-menu.js (surətə əlavə edilib)
  vendor/              Elementor, Hello Elementor, jQuery və s.
uploads/               şəkillər, PDF kitablar, video
```

`*.body.php` faylları orijinal saytın markupudur: dinamik yerlər (başlıq, məzmin,
şəkil, dövrlər) PHP çağırışları ilə əvəzlənib, qalan hər şey toxunulmazdır.

---

## Məzmunun redaktəsi

Yeni xəbər əlavə etmək üçün `data/posts.php` faylına bir sətir əlavə edin:

```php
[
    'id'         => 1800,
    'slug'       => 'yeni-xeber',
    'title'      => 'Yeni xəbərin başlığı',
    'date'       => '2026-10-01T10:00:00',
    'modified'   => '2026-10-01T10:00:00',
    'categories' => [13],                      // bax: data/categories.php
    'excerpt'    => 'Qısa təsvir',
    'description'=> 'Axtarış sistemləri üçün təsvir',
    'thumb'      => [
        'url'    => 'uploads/2026/10/sekil.jpg',
        'srcset' => '', 'sizes' => '',
        'width'  => '1000', 'height' => '450',
        'alt'    => '', 'class' => 'attachment-full size-full',
    ],
    'content'    => '<p>Xəbərin mətni</p>',
],
```

Şəkilləri `uploads/` qovluğuna qoyun və yolu `uploads/...` şəklində yazın —
`asset()` funksiyası saytın ünvanını özü əlavə edir.

Menyunu dəyişmək üçün `data/menu.php`, altlıq menyularını isə
`data/menu-footer-1.php` və `data/menu-footer-2.php` faylında redaktə edin.

---

## Surətə əlavə edilmiş dəyişikliklər

- **Mobil menyu yenidən dizayn olunub.** Orijinalda menyu başlığın altından
  açılan tünd panel idi; burada burger düyməsinə basanda bütün səhifəni
  örtən ağ panel açılır: yuxarıda loqo və bağlama düyməsi, altında
  mərkəzdə, qalın şriftlə bəndlər, cari səhifə yumşaq yaşıl fonla.
  Panel başlığı da örtdüyü üçün loqo və "×" panelin öz zolağındadır;
  loqonun tünd variantı (`uploads/2023/11/fav.png`) işlədilir, çünki
  başlıqdakı loqo ağdır və ağ fonda görünmür.
  Dil seçimi, telefon və sosial şəbəkə düymələri panelə əlavə edilməyib.
  Kod: `assets/css/custom.css` və `assets/js/mobile-menu.js` — hər ikisi
  yalnız bizim dəyişikliyimizdir və digər fayllardan sonra yüklənir,
  ona görə geri qaytarmaq üçün bu iki faylı çıxarmaq kifayətdir.
  Kompüter versiyasındakı menyuya toxunulmayıb.

---

## Orijinaldan fərqlər

Qalan hər şey vizual olaraq eynidir. Texniki fərqlər:

- **E-poçt ünvanları açıq yazılıb.** Orijinalda Cloudflare onları şifrələyir və
  JavaScript ilə açır; burada birbaşa `mailto:` keçidi var.
- **Inline üslublar bir faylda toplanıb.** WordPress və Elementor bəzi CSS-i hər
  səhifənin içinə yazır; burada onlar `assets/css/` altındakı iki fayldadır.
  Bu, səhifələri yüngülləşdirir və üslubların bəzi səhifələrdə itməsinin qarşısını alır.
- **WordPress-ə xas keçidlər yoxdur:** RSS (`/feed/`), oEmbed, `wp-json` — bu
  ünvanlar surətdə mövcud olmadığı üçün çıxarılıb.
- **Skriptlər səhifədən asılıdır.** WordPress kimi, hər şablon yalnız özünə lazım
  olan faylı yükləyir: ana səhifə `jquery-numerator` (statistika sayğacları üçün),
  qalereya səhifələri `e-gallery`, video olan yazı isə MediaElement dəsti.
  Siyahısı `inc/render.php`-dəki `JS_VIEW` sabitindədir.
- **Əlaqə forması** Elementor Pro-nun AJAX emalı əvəzinə PHP `mail()` ilə işləyir;
  əlavə olaraq CSRF nişanı və spam tələsi var. Vidjetin `data-widget_type` dəyəri
  `contact-form.default`-a dəyişdirilib ki, Elementor Pro-nun JavaScript emalı
  formanı ələ keçirməsin — CSS sinifləri toxunulmazdır.
- **Ana səhifədəki böyük inline şəkil** (441 KB base64) ayrıca fayla çıxarılıb:
  `uploads/inline/`.
- `itkinlr` siyahısı mövzunun standart şablonu ilə səhifələnir (`/itkinlr/page/2/`),
  Elementor siyahıları isə orijinaldakı kimi `?e-page-...=2` parametrini qəbul edir
  (`?sehife=2` də işləyir).
- **Şəkillərin yüklənmə işarələri.** WordPress səhifədəki şəkilləri sayıb bir
  hissəsinə `loading="lazy"`, birinciyə isə `fetchpriority="high"` qoyur. Orijinalda
  bu sayğac ardıcıl işləmir (bəzi səhifədə iki şəkil "high" alır, bəzisində heç biri),
  ona görə surətdə təkrarlanmayıb. Şəkillərin sayı, ünvanı, ölçüsü və `decoding`
  atributu isə orijinalla eynidir — fərq yalnız brauzerə verilən yükləmə
  məsləhətindədir, görünüşə və axtarış sistemlərinə təsiri yoxdur.

---

## Lisenziya və məzmun

Sayt məzmunu (mətnlər, şəkillər, sənədlər) “Qarabağ İtkin Ailələri” İctimai
Birliyinə aiddir. Elementor, Hello Elementor və jQuery öz lisenziyaları altındadır.
