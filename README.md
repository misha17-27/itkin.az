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

## İdarə paneli

Ünvan: **`/admin/`** (məsələn `https://itkin.az/admin/`).

İlk giriş: istifadəçi `admin`, şifrə `itkin2026`.
**Girdikdən dərhal sonra “Ayarlar” bölməsindən şifrəni dəyişin.**

### Nə redaktə olunur

| Bölmə | Nə edir |
|---|---|
| Xəbərlər | Yazı əlavə etmək, dəyişmək, silmək: başlıq, ünvan, tarix, kateqoriya, şəkil, mətn |
| Kitabxana | Kitablar: ad, müəllif, üz qabığı, təsvir və hər dil üçün PDF keçidi (AZ / EN / RU) |
| İtkinlər | İtkin düşmüş şəxslərin siyahısı: ad və foto |
| Kateqoriyalar | Ad, ünvan, təsvir. Yazısı olan kateqoriya silinmir |
| Səhifələr | Statik səhifələrin siyahısı; hər səhifədə bütün mətnlər, mətn blokları, şəkillər, qalereya, fon şəkli və videosu, SEO |
| Menyular | Əsas menyu və altlıqdakı iki sütun; alt bəndlərlə birlikdə |
| Şəkillər | Fayl yükləmək, axtarmaq və istifadə olunmayan faylları silmək |
| Ayarlar | Sayt adı, əlaqə e-poçtu, səhifələmə, Google Analytics, şifrə |

### Necə işləyir

Panel məzmunu `data/*.php` fayllarına yazır — verilənlər bazası yoxdur.
Hər yazıdan əvvəl köhnə nüsxə `storage/backups/` altında saxlanılır
(son 20 versiya), fayl isə əvvəlcə müvəqqəti ada yazılıb yoxlanır və
yalnız sonra yerinə keçirilir. Beləliklə yarımçıq yazı saytı sındırmır.

Statik səhifələr ayrıca işləyir: şablonlardakı orijinal toxunulmaz qalır,
dəyişdirdikləriniz isə `data/page-texts.php` faylına düşür.

- Boşaltdığınız sahə saytda da boş qalır — abzası, başlığı, şəkli, fonu və ya
  bütün qalereyanı götürmək olur. Şəkil, fon və video üçün «Təmizlə» düyməsi var.
- Dəyişdirilmiş sahənin altında «Orijinala qaytar» işarəsi var: işarələyib yadda
  saxlasanız, qeyd silinir və o hissə yenidən bayt-bayt orijinal kimi çıxır.
- Dəyəri orijinalla eyni olan sahə üçün qeyd yaranmır — səhifəni açıb heç nəyə
  toxunmadan yadda saxlamaq heç nəyi dəyişmir.

Səhifədə nə redaktə olunur (səhifədə göründüyü ardıcıllıqla):

- başlıqlar, düymələrin mətni və ünvanı, sayğacların adı və rəqəmi;
- mətn blokları — vizual redaktorla;
- şəkillər, karusel şəkilləri, blokların fon şəkli və ana səhifədəki fon videosu —
  kitabxanadan seçilir; yeni şəkil qoyulanda köhnə `srcset` atılır, ölçülər yeni
  fayldan götürülür;
- qalereyalar (Haqqımızda — 60, Şəkillər — 69 foto): şəkil əlavə etmək (bir neçəsini
  birdən), silmək, sürüşdürərək və ya ‹ › ilə sıralamaq. Hamısını silsəniz qalereya
  saytda boş qalır.

Telefonla çəkilmiş şəkillər çox vaxt yan saxlanılır və düz vəziyyət EXIF-dəki
oriyentasiya ilə verilir. Qalereya bunu nəzərə alır — portret şəkil portret
kafel alır (hostinqdə `exif` modulu olmasa da).

Xəbərlər və Kitabxana səhifələrinin məzmunu avtomatik yığılır — onlarda yalnız
SEO sahələri var, məzmun «Xəbərlər» və «Kitabxana» bölmələrindədir.

### Faylı silmək

«Şəkillər» bölməsində hər faylın yanında «Sil» düyməsi var. Saytda istifadə
olunan fayllar «İstifadədə» nişanı ilə göstərilir və silinmir — yazıda, səhifədə
və ya dizaynda qırıq şəkil qalmasın deyə. Əvvəlcə faylı yazıdan və ya
səhifədən götürün, sonra silin.

Silinən fayl birdəfəlik itmir: `storage/trash/<tarix>/uploads/…` qovluğuna
köçürülür. Səhv silinibsə, cPanel-in File Manager-i ilə oradan geri qaytarmaq
olar; lazım deyilsə, `storage/trash/` qovluğunu vaxtaşırı boşaldın.

### Mətn redaktoru

Mətn sahələrində düymələr zolağı var: **B**, *I*, H2, H3, ¶, nişanlı və
nömrəli siyahı, keçid əlavə etmək və götürmək, formatı təmizləmək.
Sağdakı **HTML** düyməsi kodu açır.

Kənar kitabxana işlədilmir — sadə `contenteditable` sahədir, ona görə
əlavə fayl yüklənmir və internetsiz də işləyir.

İki şeyi bilmək faydalıdır:

1. **Toxunmadığınız mətn dəyişmir.** Redaktor `<textarea>`-nı yalnız siz
   həqiqətən nəsə yazandan sonra yeniləyir. Yazını açıb heç nə etmədən
   yadda saxlasanız, məzmun bayt-bayt olduğu kimi qalır.
2. **İlk redaktədən sonra kod bir az səliqəyə salınır.** Brauzer HTML-i
   öz qaydası ilə yazır: `&#8221;` → `”`, `<br />` → `<br>` və s. Görünüş
   dəyişmir, cədvəl, video, şəkil və `srcset` toxunulmur — sadəcə kod
   fərqli yazılır.

Başlıq və siyahı düymələri yalnız abzas və başlıqlara toxunur. Kursor
cədvəl xanasında və ya Elementor sarğısının içindədirsə, düymə heç nə
etmir — quruluşu dağıtmaqdansa dinc dayanmağı seçir. Cədvəl, video və
`<figure>` kimi hissələr üçün **HTML** düyməsini açıb kodu birbaşa
redaktə edin.

Başqa yerdən yapışdırılanda kod sadələşdirilir: `<script>`, hadisə
atributları və artıq işarələr atılır, bu saytın şəkilləri isə yenidən
nisbi yola (`uploads/…`) salınır.

### Şəkil seçmək

Şəkil sahəsində yol göstərilmir — yalnız şəklin özü görünür. Şəklə və ya
“Kitabxanadan seç” düyməsinə basanda forma üzərində kitabxana açılır:
orada faylın üstünə basırsınız, pəncərə bağlanır və şəkil sahəyə düşür.
“Təmizlə” şəkli götürür.

Pəncərədə axtarış, səhifələmə və fayl yükləmək də var — yeni şəkli elə
oradan yükləyib dərhal seçmək olar. Bu pəncərədə yalnız şəkillər görünür;
PDF və video “Şəkillər” bölməsindədir.

### SEO

Hər bölmədə “Axtarış sistemləri” kartı var:

- **SEO başlıq** — brauzerin başlığında və Google nəticələrində görünən mətn
  (`<title>`). Boş buraxsanız addan avtomatik qurulur, yəni adı dəyişəndə
  başlıq da özü yenilənir.
- **Təsvir (meta description)** — axtarış nəticələrində başlığın altındakı
  izah. 150–160 simvol yaxşı ölçüdür. Bu mətn eyni zamanda sosial şəbəkə
  üçün `og:description` teqinə də yazılır.

Sahələr xəbərlərdə, kitabxanada, itkinlər siyahısında, kateqoriyalarda və
statik səhifələrdə var.

### Hostinqdə

Bu qovluqlara yazma icazəsi lazımdır (cPanel-də 755 və ya 775):

```
data/       uploads/       storage/
```

Panelin “İcmal” səhifəsi giriş zamanı bunu özü yoxlayır və problem varsa
xəbərdarlıq göstərir.

`data/`, `storage/` və `/admin/` üçün `.htaccess` faylları repozitoriyadadır.
`storage/` xüsusilə vacibdir — orada əlaqə formasının jurnalı (müraciət edənlərin
adı, telefonu, e-poçtu), ehtiyat nüsxələr və zibil qutusu saxlanılır. Qoruyucu fayl
nədənsə serverə düşməsə, sayt onu ilk yazıda özü yaradır. Nginx hostinqində
`.htaccess` işləmir — orada `storage/` və `data/` üçün girişi server ayarında bağlayın.
Qısası:
məzmun faylları birbaşa açılmır, panel isə axtarış sistemlərinə düşmür.

### Sayt yeniləndikdə — diqqət

Panel məzmunu `data/*.php` fayllarına yazır, bu fayllar isə repozitoriyada da
var. Deməli **serverdə `git pull` etsəniz, paneldən etdiyiniz dəyişikliklər
itə bilər** — pull gələn nüsxəni yerindəkinin üstünə yazır.

Kod yeniləyəndə belə edin:

1. Serverdə `data/` qovluğunun və `uploads/` qovluğunun nüsxəsini götürün
   (cPanel → File Manager → Compress, yaxud panelin `storage/backups/` nüsxələri).
2. `git pull` edin.
3. `data/` qovluğunu geri qaytarın.

Ən rahatı isə serverdə məzmunu git-dən ayırmaqdır — bir dəfə icra edin:

```bash
git update-index --skip-worktree data/posts.php data/kitabxana.php data/itkinlr.php data/categories.php data/menu.php data/menu-footer-1.php data/menu-footer-2.php data/page-texts.php
```

Bundan sonra `git pull` bu faylları toxunmadan buraxır.

Eyni səbəbdən **şifrəni dəyişəndən sonra `data/settings.php` faylını
repozitoriyaya göndərməyin** — orada şifrənin hash-i saxlanılır. `config.php`
faylındakı `form_secret` də serverdə öz dəyəri ilə qalmalıdır.

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

- **`<meta name="description">` əlavə olunub.** Orijinal saytda bu teq heç bir
  səhifədə yox idi (Yoast yalnız `og:description` verirdi, bəzi səhifələrdə
  isə ümumiyyətlə heç nə). İndi təsvir varsa, teq də yazılır. Boş qalmış
  təsvirlər — 13 itkin qeydi, 6 kateqoriya, 1 yazı və 2 arxiv səhifəsi —
  doldurulub. Mövcud təsvirlərə toxunulmayıb.

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
