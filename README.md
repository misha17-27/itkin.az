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
`storage/contact-log.php` faylına da yazılacaq (PHP faylıdır ki, kənardan açılsa da məzmunu görünməsin; cPanel File Manager-də adi mətn kimi oxunur).

---

## İdarə paneli

Ünvan: **`/mguliyev/`** — `https://itkin.az/mguliyev/`.

Ünvan `config.php`-dəki `admin_path` ayarındadır (hərf, rəqəm, `-`, `_`). Qovluğun
öz adı ilə (`/admin/`) gələn sorğu saytın adi 404 səhifəsini görür — panelin yerini
təxmin etmək çətinləşir. Ünvanı dəyişmək üçün yalnız `admin_path`-ı dəyişin.

### İlk giriş

Repozitoriya açıqdır, ona görə **standart şifrə yoxdur**: təzə quraşdırılmış saytda panelə
heç kim daxil ola bilmir (giriş səhifəsi bunu yazır). Şifrə serverdə, repozitoriyaya
düşməyən `data/settings.php` faylında olmalıdır. İki yol:

1. **Kompüterdəki (lokal) paneldən** — orada «Mənim profilim»də şifrəni qoyun, sonra
   `data/settings.php` faylını cPanel → File Manager ilə serverdəki sayt qovluğunun
   `data/` qovluğuna yükləyin.
2. **cPanel → Terminal** ilə (sayt qovluğunda):

   ```bash
   php -r 'echo password_hash("YENİ-UZUN-ŞİFRƏ", PASSWORD_DEFAULT), PHP_EOL;'
   ```

   Çıxan sətri (`$2y$…`) `data/settings.php` faylına yazın:

   ```php
   <?php return ['admin_user' => 'admin', 'admin_password' => '$2y$…'];
   ```

Sonra `/mguliyev/`-də `admin` və həmin şifrə ilə daxil olun. Köhnə quraşdırmalarda
ilkin şifrə `itkin2026` idi — o hələ də işləyirsə, panel əvvəlcə şifrəni dəyişməyi tələb edir.

### İstifadəçilər və rollar

Panelə bir neçə nəfər öz hesabı ilə daxil ola bilər («Ayarlar» → «İstifadəçilər»).

| Rol | Nə edə bilər |
|---|---|
| Administrator | Hər şey, o cümlədən istifadəçilər, ümumi ayarlar, poçt və təhlükəsizlik |
| Redaktor | Yalnız məzmun: xəbərlər, kitabxana, itkinlər, kateqoriyalar, səhifələr, menyular, qalereya, zibil qutusu, əlaqə məlumatları — və öz profili |

- Yeni istifadəçiyə administrator **müvəqqəti şifrə** verir və onu özü çatdırır.
  İlk girişdə istifadəçi öz şifrəsini yazmalıdır — o vaxta qədər yalnız «Mənim profilim» açılır.
- Şifrə sıfırlananda, giriş adı dəyişəndə və ya istifadəçi silinəndə onun açıq
  sessiyaları dərhal bağlanır. Rol dəyişikliyi növbəti səhifədə qüvvəyə minir.
- Öz rolunuzu dəyişmək və özünüzü silmək olmur — sistemdə həmişə ən azı bir
  administrator qalır. Öz şifrənizi «Mənim profilim»də dəyişin.
- Ayarlar bölmələri redaktordan yalnız menyuda gizlədilmir — birbaşa ünvanla da açılmır.
- İstifadəçilər `data/users.php` faylında saxlanılır (şifrələr hash şəklində). Bu fayl
  repozitoriyaya düşmür (`.gitignore`) və brauzerdən açılmır. Fayl kilid altında yazılır —
  eyni anda edilən dəyişikliklər bir-birini silmir. Son giriş vaxtları ayrıca,
  `storage/user-logins.json`-dadır.
- Əvvəlki tək hesab (`config.php` / `data/settings.php`-dəki `admin_user`, `admin_password`)
  ilk administrator olur — şifrəsi dəyişmir. Fayl ilk istifadəçi əlavə olunanda və ya
  profil yadda saxlananda yaranır.
- Silinmiş istifadəçinin nömrəsi (id) yeni istifadəçiyə verilmir — onun əlavə etdiyi
  məzmun başqasının adına keçməsin (`storage/user-seq.json`, `storage/users-deleted.json`).

**Hamı paneldən kənarda qalıbsa** (şifrə unudulub): cPanel → File Manager →
`data/users.php` faylını silin (əvvəlcə nüsxəsini götürün). Giriş yenidən köhnə tək
hesabla açılacaq — `data/settings.php`-dəki şifrə ilə; onu «İlk giriş»dəki kimi yeniləyin.
Sonra istifadəçiləri yenidən əlavə edin.

### Müəllif: kim əlavə edib, kim dəyişib

Panel hər qeydə kimin və nə vaxt əlavə etdiyini və son dəfə kimin dəyişdiyini yazır.

| Harada | Nə göstərilir |
|---|---|
| Xəbərlər, Kitabxana, İtkinlər, Kateqoriyalar — siyahı | «Əlavə edib» sütunu. Xəbərlərdə bu sütuna görə süzgəc var (kateqoriya süzgəci ilə birlikdə işləyir) |
| Həmin bölmələrin redaktə forması | «Kim əlavə edib» kartı: kim əlavə edib və kim son dəyişib — tarix və saatla |
| Səhifələr | Siyahıda «Əlavə edib» və «Son dəyişiklik», səhifənin yuxarısında da |
| Menyular, Altlıq, Əlaqə və sosial şəbəkələr | Yuxarıda «Son dəyişiklik» |
| Qalereya | Faylın altında «Yükləyib: …» |
| Zibil qutusu, İcmal | Silinmiş elementi kim əlavə etmişdi; son xəbərləri kim əlavə edib |

- «Son dəyişiklik» yalnız məzmun həqiqətən dəyişəndə yenilənir — yazını açıb heç nəyə
  toxunmadan yadda saxlamaq onu dəyişmir. Yalnız ingiliscə mətn dəyişəndə də yenilənir.
- **Köhnə saytdan gələnlər** — WordPress-dəki müəllif «köhnə sayt» qeydi ilə: xəbərlər
  (Konul, jgitd), kitablar, itkinlər, statik səhifələr və ~990 fayl. Bu məlumat WordPress
  bazasının 22.09.2026 nüsxəsindən bir dəfə götürülüb — `data/wp-authors.php`.
  WordPress kateqoriyaların müəllifini saxlamırdı — köhnə kateqoriyalarda «—» görünür.
- Panel müəllifi yazmağa başlamazdan əvvəl paneldə əlavə olunmuş qeydlərdə «—»
  (məlum deyil) görünür.
- İstifadəçinin adı dəyişəndə siyahılarda yeni adı görünür. İstifadəçi silinəndə onun
  məzmunu saytda qalır, paneldə isə adı «istifadəçi silinib» qeydi ilə göstərilir.
- Saxlanılır: sətrin özündə (`created_by`, `modified_by` — istifadəçinin nömrəsi, adı və
  vaxt; giriş adı yazılmır); faylların yükləyəni — `storage/uploads-by.json`; menyu və
  əlaqə — `storage/section-edits.json`. Saytın özündə (ziyarətçilərə) müəllif göstərilmir.
- Vaxtlar Bakı vaxtı ilədir: `config.php`-dəki `timezone` (`Asia/Baku`) hostinqin saat
  qurşağından asılı olmadan tətbiq olunur.

### İngiliscə versiya

Saytın ingiliscə versiyası `/en/` ünvanındadır: `https://itkin.az/en/`,
`https://itkin.az/en/haqqimizda/` və s. — ünvanların qalanı azərbaycancadakı kimidir.
Başlıqda qlobuslu dil seçimi (AZ / EN) var.

- **Tərcümə olunub:** bütün statik səhifələr (ana səhifə, Haqqımızda, Əlaqə, sənədlər,
  qanunvericilik, Foto, Kitabxana), menyular, kateqoriyaların adları, kitablar, itkin
  düşmüş şəxslərin səhifələri, başlıq və altlıqdakı sabit mətnlər, əlaqə formasının
  mesajları.
- **Xəbərlər tərcümə olunmur.** Tərcüməsi olmayan səhifədə dil seçimi göstərilmir;
  onun `/en/...` ünvanı azərbaycanca səhifəyə yönləndirilir, ingiliscə səhifələrdəki
  keçidlər də birbaşa azərbaycanca versiyaya aparır. «Xəbərlər» siyahısı və
  kateqoriyalar da buna görə yalnız azərbaycancadır.
- Xəbəri tərcümə etmək üçün onu paneldə açıb «EN English» nişanında ingiliscə başlığı
  (və mətni) yazın; siyahıdakı «+ EN» düyməsi birbaşa həmin nişanı açır. Bundan sonra
  xəbər (və onun kateqoriyası, «Xəbərlər» siyahısı) ingiliscə versiyada görünür və
  onda dil seçimi çıxır.
- Paneldə hər bölmənin redaktə səhifəsində «İngiliscə versiya» kartı var; statik
  səhifələrdə hər mətn sahəsinin altında ingiliscə variantı. Boş buraxılan sahənin
  yerində azərbaycanca mətn çıxır.

Harada saxlanılır:

| Fayl | Nə var |
|---|---|
| `data/<ad>-en.php` | məzmunun tərcüməsi: `pages-en`, `kitabxana-en`, `itkinlr-en`, `categories-en`, `menu-en`, `menu-footer-1-en`, `menu-footer-2-en`, `archives-en`, `mejs-en` (və paneldə yazılsa `posts-en`) |
| `data/page-texts-en.php` | statik səhifələrin paneldən dəyişdirilmiş ingiliscə mətnləri |
| `inc/lang/en/*.php` | şablonlardakı sabit mətnlərin lüğəti (açar — azərbaycanca mətn) |
| `inc/i18n.php` | dil məntiqi: `/en/` prefiksi, `t()`, tərcümələrin üst-üstə yazılması, dil seçimi |

### Nə redaktə olunur

| Bölmə | Nə edir |
|---|---|
| Xəbərlər | Yazı əlavə etmək, dəyişmək, silmək: başlıq, ünvan, tarix, kateqoriya, şəkil, mətn (içində foto və video); «AZ / EN» nişanında ingiliscə tərcümə; «Saytda aç ↗» yazını saytda göstərir |
| Kitabxana | Kitablar: ad, müəllif, üz qabığı, təsvir və hər dil üçün PDF keçidi (AZ / EN / RU) |
| İtkinlər | İtkin düşmüş şəxslərin siyahısı: ad və foto |
| Kateqoriyalar | Ad, ünvan, təsvir. Yazısı olan kateqoriya silinmir (zibil qutusundakı yazılar da sayılır) |
| Səhifələr | Statik səhifələrin siyahısı; hər səhifədə bütün mətnlər, mətn blokları, şəkillər, qalereya, fon şəkli və videosu, SEO |
| Menyular | Başlıqdakı əsas menyu, alt bəndlərlə birlikdə |
| Altlıq (footer) | Sütun başlıqları («Yararlı linklər», «Media», «Bizimlə əlaqə») və ən aşağıdakı müəllif hüququ sətri — AZ və EN; hər iki sütunun keçidləri (ad, ünvan, sıra, ingiliscə ad). Boş buraxılan yazının yerində orijinal mətn çıxır (`data/footer.php`) |
| Qalereya | Sayta yüklənmiş bütün şəkillər, videolar və PDF-lər: yükləmək, növə görə süzmək, axtarmaq, silmək (bir-bir və ya seçib birdən) |
| Zibil qutusu | Silinən xəbər, kitab, itkin, kateqoriya və fayllar — 30 gün ərzində bərpa etmək olar, sonra avtomatik silinir |
| Əlaqə və sosial şəbəkələr | Altlıqdakı telefon və e-poçt, Facebook / Instagram / YouTube keçidləri |
| Ümumi ayarlar | Sayt adı, ünvanı, səhifələmə, Google Analytics |
| Poçt (SMTP) | Müraciətlər hara gəlsin, SMTP serveri, yoxlama məktubu |
| Təhlükəsizlik | Cloudflare Turnstile kapçası (əlaqə forması və panelə giriş) |
| İstifadəçilər | Panelin istifadəçiləri və rolları (yalnız administrator) — bax: «İstifadəçilər və rollar» |
| Mənim profilim | Ad, e-poçt, giriş adı və şifrə (hazırkı şifrə ilə) |

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

### Qalereya

«Qalereya» bölməsində sayta yüklənmiş bütün fayllar var: yuxarıdakı nişanlarla
yalnız şəkilləri, videoları və ya PDF-ləri göstərmək, adla axtarmaq olar.
Videoların kiçik önizləməsi görünür.

- Hər faylın «Sil» düyməsi var; qutucuqlarla bir neçəsini seçib birdən silmək də olur.
- Saytda istifadə olunan fayl «İstifadədə» nişanı ilə göstərilir. Onu silmək üçün
  təsdiq soruşulur və harada işlədiyi (hansı xəbər, kitab, itkin, səhifə) yazılır —
  silinsə, orada şəkil görünməyəcək.
- Saytın dizaynındakı fayllar (loqo, fonlar, səhifələrin orijinal şəkilləri) silinmir.

### Zibil qutusu

Paneldə silinən heç nə dərhal itmir — «Zibil qutusu»na düşür: xəbərlər (ingiliscə
tərcüməsi ilə birlikdə), kitablar, itkinlər, kateqoriyalar və qalereyadan silinən fayllar.

- **Bərpa et** elementi əvvəlki yerinə qaytarır. Bu arada eyni ünvanlı (slug) yeni
  yazı yaranıbsa, bərpa olunana yeni ünvan verilir — heç nə üst-üstə düşmür.
- **Birdəfəlik sil** və **Zibil qutusunu boşalt** — geri qaytarmaq olmur.
- **30 gündən köhnə** elementlər avtomatik silinir (panel açılanda, gündə bir dəfə
  yoxlanılır). Hər elementin yanında neçə gün qaldığı yazılır.
- Hamısı `storage/trash/` qovluğundadır (`items/*.json` — qeydlər, `<tarix>/uploads/…` —
  fayllar). Bu qovluq brauzerdən açılmır.

### Mətn redaktoru

Mətn sahələrində düymələr zolağı var: **B**, *I*, H2, H3, ¶, nişanlı və
nömrəli siyahı, keçid əlavə etmək və götürmək, formatı təmizləmək,
**Şəkil** və **Video**. Sağdakı **HTML** düyməsi kodu açır.

- **Şəkil** kitabxananı açır (orada yeni şəkil yükləmək də olur); seçilən şəkil
  kursorun yerinə ayrıca blok kimi düşür, alt mətni soruşulur.
- **Video** — kitabxanadan MP4 faylı və ya YouTube / Vimeo linki (`youtu.be/…`,
  `youtube.com/watch?v=…`, `vimeo.com/…`). Link saytda ekranın eninə uyğunlaşan
  video çərçivəsi olur. Başqa saytların çərçivələri (iframe) qəbul edilmir.
- Abzasın ortasında əlavə olunan şəkil/video abzası ikiyə bölür, cədvəlin və ya
  siyahının içində isə onlardan sonra qoyulur.

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
nisbi yola (`uploads/…`) salınır. Redaktorun öz şəkil və video blokları
kəsilib-yapışdırılanda olduğu kimi qalır.

### Şəkil seçmək

Şəkil sahəsində yol göstərilmir — yalnız şəklin özü görünür. Şəklə və ya
“Kitabxanadan seç” düyməsinə basanda forma üzərində kitabxana açılır:
orada faylın üstünə basırsınız, pəncərə bağlanır və şəkil sahəyə düşür.
“Təmizlə” şəkli götürür.

Pəncərədə axtarış, səhifələmə və fayl yükləmək də var — yeni şəkli elə
oradan yükləyib dərhal seçmək olar. Bu pəncərədə yalnız şəkillər görünür;
PDF və videolar “Qalereya” bölməsindədir.

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

Bütün mövcud səhifələrə, xəbərlərə, kitablara, itkinlərə, kateqoriyalara və
arxiv səhifələrinə SEO başlıq və təsvir artıq yazılıb (başlıq ~60, təsvir
~150–160 simvol, açar sözlər mətnin özündən). Yeni əlavə olunan yazıda sahələr
boşdursa, başlıq addan, təsvir mətnin əvvəlindən avtomatik götürülür —
amma əl ilə yazmaq daha yaxşıdır.

### Poçt

Əlaqə formasının müraciətləri «Poçt (SMTP)» bölməsində göstərilən ünvana gedir.
SMTP serveri yazılmayıbsa hostinqin `mail()` funksiyası işlənir — bəzi hostinqlərdə
belə məktublar spama düşür və ya heç çatmır. Etibarlı yol SMTP-dir:

- cPanel → **Email Accounts** → qutunun yanında **Connect Devices**: server
  (adətən `mail.itkin.az`), port 465, SSL/TLS; istifadəçi — poçtun tam ünvanı.
- Yadda saxladıqdan sonra həmin səhifədən **yoxlama məktubu** göndərin. Alınmasa,
  səhifə serverin cavabını göstərir (məsələn `535 Authentication failed` — şifrə səhvdir).

SMTP şifrəsi `data/settings.php`-də saxlanılır: bu fayl repozitoriyaya düşmür və
`data/` qovluğu kənardan açılmır.

### Təhlükəsizlik

- **Kapça (Cloudflare Turnstile).** Açarları dash.cloudflare.com → Turnstile-dan
  götürüb «Təhlükəsizlik»də saxlayın. Gizli açar saxlananda Cloudflare-də yoxlanılır.
  Kapçanı formada və ya girişdə yandırmaq üçün həmin səhifədəki yoxlamanı keçmək
  lazımdır — beləcə səhv açarla paneldən kənarda qalmaq olmur.
  Yenə də giriş bağlanıbsa: cPanel → File Manager → `data/settings.php` faylından
  `turnstile_login` sətrini silin.
- **Cəhd limiti.** Bir ünvandan 15 dəqiqədə 8 səhv şifrədən sonra giriş bağlanır.
  Sayğac IP-yə görə `storage/login-attempts.json`-da saxlanılır (əvvəl sessiyada idi
  və cookie-ni silməklə sıfırlanırdı). Sayt Cloudflare arxasındadırsa, həqiqi ünvan
  `CF-Connecting-IP`-dən götürülür — yalnız sorğu həqiqətən Cloudflare-dən gələndə.
  Uğurlu giriş əvvəlki səhvləri silmir (yalnız öz cəhdini qaytarır) — bir hesabla girib
  başqa hesabın şifrəsini sonsuz təxmin etmək olmur. Profildə hazırkı şifrənin
  yoxlanmasının hər istifadəçi üçün ayrıca sayğacı var.
  Özünüz bağlanmısınızsa, bu faylı silin.
- **Mətn redaktorundakı kod** (xəbər, kitab, itkin, səhifə mətni — AZ və EN) serverdə
  DOM ağacı kimi təmizlənir: skript, forma, “xam mətn” elementləri, hadisə atributları
  (`onerror` və s.) və `javascript:` ünvanları atılır; iframe yalnız YouTube və Vimeo
  videosu üçün saxlanılır. Redaktor rolunda olan biri administratorun brauzerində kod
  işlədə bilməsin deyə panelin öz redaktoru da kodu əvvəlcə “ölü” sənəddə oxuyur, panel
  səhifələri isə `Content-Security-Policy: script-src` ilə yalnız öz skriptlərinə icazə verir.
- **Yüklənən fayllar** uzantısı ilə yanaşı məzmununa görə də yoxlanılır: JPG adlı mətn,
  PNG adlı PHP kodu, skriptli SVG qəbul edilmir. `uploads/.htaccess` orada heç bir
  skriptin işə düşməsinə imkan vermir.
- **Sessiyalar** `storage/sessions/`-dadır: paylaşılan hostinqdə ümumi qovluğu başqa
  saytların təmizləyicisi 24 dəqiqədən sonra boşaldır, panel isə 2 saat gözləyir.
  Şifrə dəyişəndə bütün başqa sessiyalar (başqa brauzer, oğurlanmış cookie) bağlanır.
- **Əlaqə forması** spama və poçt bombardmanına qarşı bir neçə qatla qorunur:
  - görünməz tələ sahəsi — robot onu doldurur, ona uğur göstərilir, məktub getmir;
  - hər səhifə açılışına ayrıca, **birdəfəlik** nişan (6 saat keçərli). Eyni formanı
    ikinci dəfə göndərmək olmur; 3 saniyədən tez göndərilən forma robot sayılır;
  - sahələrin uzunluğu məhduddur (mesaj 5000 simvol), sətir sonu ilə saxta
    məktub başlığı əlavə etmək mümkün deyil;
  - limit: bir ünvandan saatda 5, ümumilikdə saatda 40 müraciət
    (`storage/contact-guard.json`). `X-Forwarded-For` kimi başlıqlarla aldatmaq olmur;
  - istəsəniz üstəlik Turnstile kapçası.

  Nişanın gizli açarı ilk dəfə avtomatik yaranır və `storage/form-secret.php`-də
  saxlanılır (`config.php`-də `form_secret` yazsanız, o işlənir).
- **Poçt şifrəsi** yalnız şifrələnmiş bağlantı ilə (SSL/TLS və ya STARTTLS) göndərilir;
  yoxlama jurnalında giriş məlumatları `***` ilə gizlədilir.
- **Brauzer başlıqları:** sayt başqa saytın çərçivəsinə (iframe) yerləşdirilə bilmir
  (klikcekinqə qarşı), `nosniff`, `Referrer-Policy`, `Permissions-Policy`; HTTPS-də
  `Strict-Transport-Security`. PHP versiyası (`X-Powered-By`) gizlədilir.
- **HTTPS.** «Ümumi ayarlar»da (və ya `config.php`-də) sayt ünvanı `https://` ilə
  yazılıbsa, `http://` sorğuları 301 ilə HTTPS-ə yönləndirilir.
- **Host başlığı.** Keçidlər və `canonical` yalnız `itkin.az`, `www.itkin.az` və
  sayt ünvanındakı domenlə qurulur — saxta `Host` başlığı səhifəyə düşmür. Başqa domen
  lazımdırsa, `config.php`-də `'allowed_hosts' => ['yeni-domen.az']` əlavə edin.
- **Birbaşa açılmayan fayllar:** gizli fayl və qovluqlar (`.git/`, `.gitignore`,
  `.user.ini` …), `README.md`, `router.php`, `config.php`, `inc/`, `templates/`,
  `data/`, `storage/`, `admin/inc|sections|views/` — hamısı 403 qaytarır.
  `/.well-known/` (SSL sertifikatı üçün) açıq qalır.
- **Xəta mətni** hostinqdə ekrana çıxmır (fayl yolları görünməsin) — `.user.ini` və
  kodun özü bunu bağlayır; xətalar hostinqin `error_log`-una yazılır.
- **Paneldən gələn qısa mətnlər** (menyu adları, kateqoriya adı, SEO başlıq, altlıq
  yazıları) saytda həmişə mətn kimi çıxır — HTML/skript kimi işləmir; `<script>` içinə
  düşən JSON `</script>`-i bağlaya bilmir. Şəkil yolları yalnız `uploads/` altındakı
  mövcud fayl ola bilər; səhifələrdəki keçid sahələrində `javascript:` və oxşarları
  (HTML-varlıqlarla gizlədilmiş olsa da) qəbul edilmir.
- **Arxivlər yüklənmir:** sayt qovluğunda qalan `*.zip`, `*.sql`, `*.bak`, `*.log` və s.
  brauzerdən açılmır (məsələn, cPanel-də «Compress» ilə yaradılan `data.zip`).
- **Əlaqə forması başqa saytdan göndərilə bilmir** (brauzerin `Origin` / `Sec-Fetch-Site`
  başlıqları yoxlanılır); jurnal yandırılıbsa, onun yalnız son 5 arxivi saxlanılır.
- **Canlıya keçəndən sonra** «Ümumi ayarlar»da sayt ünvanını `https://itkin.az` yazın —
  bütün sorğular HTTPS-ə yönləndirilir, keçidlər Host başlığından asılı olmur.

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

### Yeniləmə: GitHub → server (Terminalsız, cron ilə)

Sayt serverdə `public_html`-də git deposudur. cPanel-də Terminal olmasa da, git əmrlərini
**cPanel → Cron Jobs** icra edə bilir — GitHub-a göndərilən dəyişiklik bir neçə dəqiqəyə
özü sayta düşür.

cPanel → **Cron Jobs** → «Add New Cron Job»: vaxt — `*/10 * * * *` (hər 10 dəqiqə), əmr:

```sh
export PATH="$PATH:/usr/local/cpanel/3rdparty/bin"; cd /home/itkin/public_html && ( date; git config core.fileMode false; git status --short | head -30; git pull --ff-only origin main; git log --oneline -1 ) > /home/itkin/git-update.log 2>&1
```

- `--ff-only` təhlükəsizdir: serverdə paneldən dəyişdirilmiş fayl GitHub-dakı dəyişikliklə
  toqquşsa, git heç nəyi silmir — yeniləməni dayandırır və səbəbini jurnala yazır.
- Jurnal: `/home/itkin/git-update.log` (sayt qovluğundan kənarda, brauzerdən açılmır).
  Panelin «İcmal» səhifəsində «Saytın versiyası» kartı var: saytın hazırkı versiyası,
  son yoxlamanın vaxtı və nəticəsi; administrator jurnalı orada oxuya bilir.
- cPanel-in «Update from Remote» düyməsi də işləyir; «Deploy HEAD Commit» lazım deyil
  (`.cpanel.yml` yoxdur — sayt elə depo qovluğundan açılır).

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
git update-index --skip-worktree data/posts.php data/kitabxana.php data/itkinlr.php data/categories.php data/menu.php data/menu-footer-1.php data/menu-footer-2.php data/page-texts.php data/pages.php data/archives.php
```

Bundan sonra `git pull` bu faylları toxunmadan buraxır.

İngiliscə versiyanın faylları (`data/*-en.php`) ilk dəfə adi `git pull` ilə gəlir —
onlar yeni fayllardır və yuxarıdakı əmr onlara aid deyil. Serverə gəldikdən sonra
paneldə ingiliscə mətnləri dəyişəcəksinizsə, onları da ayırın:

```bash
git update-index --skip-worktree data/pages-en.php data/kitabxana-en.php data/itkinlr-en.php data/categories-en.php data/menu-en.php data/menu-footer-1-en.php data/menu-footer-2-en.php data/archives-en.php data/mejs-en.php
```

Eyni səbəbdən **şifrəni dəyişəndən sonra `data/settings.php` və `data/users.php`
fayllarını repozitoriyaya göndərməyin** — orada şifrələrin hash-i saxlanılır. `storage/`
qovluğu (formanın gizli açarı, jurnal, sessiyalar) da repozitoriyaya düşmür.

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
  i18n.php             dillər: /en/ prefiksi, t(), tərcümələrin üst-üstə yazılması, dil seçimi
  lang/en/*.php        ingiliscə lüğət (şablonlardakı sabit mətnlər)

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
  *-en.php             ingiliscə tərcümələr (id => sahələr)
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

- **Daxili səhifələrin başlığında Facebook ikonu işləyir.** Orijinalda həmin ikonun
  keçidi yox idi (Instagram və YouTube-un da). İndi ikonlar «Əlaqə və sosial
  şəbəkələr»dəki ünvanlara aparır; Instagram və YouTube ünvanı yazılmayınca
  orijinaldakı kimi keçidsiz qalır.

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
- **İngiliscə versiya əlavə olunub** (`/en/`). Orijinalda WPML quraşdırılmışdı, amma
  ingiliscə versiya dərc olunmamışdı (`/en/` ana səhifəyə yönləndirirdi). Tərcüməsi
  olan səhifələrdə başlıqda dil seçimi və `hreflang="en"` var; itkinlər və kitabxana
  səhifələrində də indi `hreflang` verilir (orijinalda WPML onları tərcümə etmədiyi
  üçün verilmirdi). Xəbərlər, kateqoriyalar və «Xəbərlər» siyahısı orijinaldakı kimidir.
- **Açılış sürətləndirilib** (`inc/render.php`, `speed_hints()`):
  - Elementor karusel, menyu və sayğac skriptlərini səhifə hazır olandan sonra
    zəncirlə yükləyirdi (əvvəl vidjetin faylı, sonra Swiper). İndi bu fayllar
    `<head>`-də əvvəlcədən istənir (`rel="preload"`) və CSS ilə eyni vaxtda endirilir.
  - Karusellər JS işə düşənə qədər slaydı bütün enə açırdı — ana səhifədəki itkinlər
    karuseli əvvəl 1485px, sonra 363px olurdu və səhifə “sıçrayırdı”. İndi işə
    düşməmiş karusel də son enindədir (slayd sayı vidjetin öz ayarlarından götürülür).
  - Ana səhifənin fon videosu yenidən sıxılıb: 6,3 MB → 2,8 MB (səssiz, eyni ölçü və
    uzunluq, görünüş fərqi yoxdur).
- **Surətə xas `custom.css` və `mobile-menu.js`** versiya parametri ilə yüklənir
  (`?ver=<faylın vaxtı>`) — yenilənəndə brauzer köhnə nüsxəni keşdən götürmür.
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
