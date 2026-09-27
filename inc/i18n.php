<?php
/**
 * Dillər / languages.
 *
 * Sayt iki dildədir: azərbaycanca (əsas, ünvanlar prefikssiz) və ingiliscə
 * (/en/ prefiksi ilə). Ünvanların qalan hissəsi eynidir: /haqqimizda/ və
 * /en/haqqimizda/ eyni səhifənin iki dilidir.
 *
 * Tərcümə üç yerdən gəlir:
 *   inc/lang/en/*.php      — şablonlardakı sabit mətnlər, t() funksiyası ilə;
 *                            açar azərbaycanca mətnin özüdür;
 *   data/<ad>-en.php       — məzmunun tərcüməsi (xəbərlər, kitablar, menyular …),
 *                            id-yə görə orijinal sətrin üstünə yazılır;
 *   data/page-texts-en.php — statik səhifələrin paneldən dəyişdirilmiş mətnləri.
 *
 * Tərcümə yoxdursa azərbaycanca mətn göstərilir — sayt heç vaxt boş qalmır.
 * Azərbaycanca versiyada bu funksiyaların heç biri çıxışı dəyişmir.
 */

const LANGS = [
    'az' => ['name' => 'Azərbaycan',   'short' => 'AZ', 'locale' => 'az_AZ'],
    'en' => ['name' => 'English',      'short' => 'EN', 'locale' => 'en_US'],
];
const LANG_DEFAULT = 'az';

/** Tərcüməsi data/<ad>-en.php faylında saxlanılan məzmun */
const I18N_DATA = [
    'posts', 'kitabxana', 'itkinlr', 'categories', 'pages', 'archives',
    'menu', 'menu-footer-1', 'menu-footer-2', 'mejs',
];

/**
 * Dildən asılı ayarlar: ingiliscə variant <açar>_en adı ilə saxlanılır.
 * true — ayrıca yazılmayıbsa lüğətdəki tərcümə işlənir (t()),
 * false — orijinal dəyər qalır (məsələn saytın adı “İtkin” — marka).
 */
const I18N_CFG = ['site_name' => false, 'site_tagline' => true];

/** Cari dil. index.php ünvandan təyin edir; panel həmişə azərbaycancadır. */
function lang(?string $set = null): string
{
    static $current = LANG_DEFAULT;
    if ($set !== null && isset(LANGS[$set])) {
        $current = $set;
    }
    return $current;
}

/** Ünvan prefiksi: azərbaycanca '' , ingiliscə '/en' */
function lang_prefix(?string $lang = null): string
{
    $lang = $lang ?? lang();
    return $lang === LANG_DEFAULT ? '' : '/' . $lang;
}

/**
 * Sabit mətnin tərcüməsi. Azərbaycancada mətnin özü qaytarılır,
 * ingiliscədə lüğətdən götürülür; lüğətdə yoxdursa yenə orijinal.
 */
function t(string $az): string
{
    if (lang() === LANG_DEFAULT) {
        return $az;
    }
    $dict = t_dict();
    return $dict[$az] ?? $az;
}

/**
 * Cari dilin lüğəti: inc/lang/<dil>/*.php faylları (hər biri azərbaycanca => tərcümə
 * massivi qaytarır; saytın hissələrinə görə bölünüb — ümumi, ana səhifə, səhifələr …).
 */
function t_dict(?string $lang = null): array
{
    static $dict = [];
    $lang = $lang ?? lang();
    if (!isset($dict[$lang])) {
        $dict[$lang] = [];
        foreach (glob(__DIR__ . '/lang/' . $lang . '/*.php') ?: [] as $file) {
            $dict[$lang] += (array) include $file;
        }
    }
    return $dict[$lang];
}

/** Verilmiş dildəki tərcümə (panel üçün — cari dildən asılı olmayaraq) */
function t_lang(string $az, string $lang): string
{
    if ($lang === LANG_DEFAULT) {
        return $az;
    }
    return t_dict($lang)[$az] ?? $az;
}

/**
 * Sorğunun ünvanı — sayt kökü və dil prefiksi çıxılmaqla, kodlaşdırılmış
 * şəkildə (məsələn "xeberler/page/2/"). index.php təyin edir.
 */
function route_path(?string $set = null): string
{
    static $path = null;
    if ($set !== null) {
        $path = route_clean($set);
    }
    if ($path === null) {
        $path = route_clean((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: ''));
    }
    return $path;
}

/**
 * Ünvandan qurulan keçid və yönləndirmə başqa sayta apara bilməsin:
 * əvvəldəki "/" və "\" atılır (brauzer "/\evil.com"-u "//evil.com" kimi oxuyur),
 * qalan "\" kodlaşdırılır.
 */
function route_clean(string $path): string
{
    return str_replace('\\', '%5C', ltrim($path, '/\\'));
}

/** Keçid üçün dil: cari dil, səhifənin o dildə versiyası yoxdursa — əsas dil */
function lang_for(string $path): string
{
    $lang = lang();
    return $lang === LANG_DEFAULT || lang_has($path, $lang) ? $lang : LANG_DEFAULT;
}

/** Cari səhifənin dilləri: hansı dillərdə versiyası var (dil düyməsi, hreflang) */
function lang_versions(): array
{
    // tapılmayan səhifənin başqa dildə versiyası yoxdur (index.php 404-ü render-dən əvvəl verir)
    if (http_response_code() === 404) {
        return [lang()];
    }
    $path = route_path();
    return array_values(array_filter(array_keys(LANGS), static function (string $code) use ($path) {
        return lang_has($path, $code);
    }));
}

/** Verilmiş dildə daxili ünvan: url_lang('xeberler', 'en') => /en/xeberler/ */
function url_lang(string $path, string $lang): string
{
    $path = trim($path, '/');
    return base_path() . lang_prefix($lang) . '/' . ($path === '' ? '' : $path . '/');
}

/** Verilmiş dildə tam ünvan */
function abs_url_lang(string $path, string $lang): string
{
    $configured = trim((string) cfg('site_url'));
    if ($configured !== '') {
        return rtrim($configured, '/') . lang_prefix($lang) . '/'
            . ltrim($path === '' ? '' : trim($path, '/') . '/', '/');
    }
    $scheme = request_is_https() ? 'https' : 'http';
    return $scheme . '://' . request_host() . url_lang($path, $lang);
}

/**
 * Saytın tam ünvanını başqa dilə çevirir (hreflang üçün):
 * https://itkin.az/xeberler/?sehife=2 => https://itkin.az/en/xeberler/?sehife=2
 */
function lang_swap_url(string $abs, string $to): string
{
    $root = abs_url_lang('', LANG_DEFAULT);
    if (strpos($abs, $root) !== 0) {
        return $abs;
    }
    $rest = (string) substr($abs, strlen($root));
    foreach (array_keys(LANGS) as $code) {
        if ($code !== LANG_DEFAULT && ($rest === $code || strpos($rest, $code . '/') === 0
            || strpos($rest, $code . '?') === 0)) {
            $rest = ltrim((string) substr($rest, strlen($code)), '/');
            break;
        }
    }
    return rtrim($root, '/') . lang_prefix($to) . '/' . $rest;
}

/** Cari səhifənin başqa dildəki ünvanı (dil düyməsi üçün), sorğu parametrləri ilə */
function lang_switch_url(string $to): string
{
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    return base_path() . lang_prefix($to) . '/' . route_path() . ($query !== '' ? '?' . $query : '');
}

/**
 * Məzmunun tərcüməsini orijinalın üstünə yazır (data_load() çağırır).
 * Tərcümə faylında sətir id-yə (menyularda bəndin id-sinə, arxivlərdə açara)
 * görə tapılır; boş sahə orijinalı saxlayır.
 */
function i18n_merge(string $name, array $rows): array
{
    $lang = lang();
    if ($lang === LANG_DEFAULT || !in_array($name, I18N_DATA, true)) {
        return $rows;
    }
    $tr = data_load($name . '-' . $lang);
    return $tr ? i18n_apply($rows, $tr) : $rows;
}

function i18n_apply(array $rows, array $tr): array
{
    foreach ($rows as $k => $row) {
        if (!is_array($row)) {
            // sadə açar => mətn (məsələn MediaElement-in sətirləri, əlaqə ünvanı)
            if (isset($tr[$k]) && is_string($tr[$k]) && $tr[$k] !== '') {
                $rows[$k] = $tr[$k];
            }
            continue;
        }
        $id   = $row['id'] ?? $k;
        $over = $tr[$id] ?? null;
        if (is_array($over)) {
            foreach ($over as $field => $value) {
                if ($field === 'children' || $field === 'id' || $value === '' || $value === null) {
                    continue;
                }
                if (($field === 'title' || $field === 'doc_title') && is_string($value)) {
                    $value = i18n_brand($value);
                }
                $rows[$k][$field] = $value;
            }
            // Brauzer başlığı ayrıca tərcümə olunmayıbsa, başlıqdan qurulur
            if (isset($over['title']) && $over['title'] !== '' && empty($over['doc_title'])
                && !empty($row['doc_title'])) {
                $rows[$k]['doc_title'] = $over['title'] . ' - ' . cfg('site_name');
            }
        }
        if (!empty($row['children']) && is_array($row['children'])) {
            $rows[$k]['children'] = i18n_apply($row['children'], $tr);
        }
    }
    return $rows;
}

/**
 * Tərcümə faylında başlıq " - İtkin" ilə saxlanılıb (panel azərbaycanca işləyir);
 * «Ümumi ayarlar»da saytın ingiliscə adı yazılıbsa, sonluq o adla əvəz olunur —
 * yoxsa səhifələmədə " - Page 2 of 3" əlavə olunmaz və marka səhifədən səhifəyə dəyişər.
 */
function i18n_brand(string $title): string
{
    $az    = (string) (cfg_all()['site_name'] ?? '');
    $local = (string) cfg('site_name');
    $tail  = ' - ' . $az;
    if ($az === '' || $local === $az || substr($title, -strlen($tail)) !== $tail) {
        return $title;
    }
    return substr($title, 0, -strlen($az)) . $local;
}

/**
 * Orijinal saytdan gələn paylaşma teqləri (head_meta) ingiliscə səhifədə
 * cari başlıq və təsvirlə yenilənir; dil teqi en_US olur.
 */
function i18n_head_meta(array $tags, array $meta): array
{
    if (lang() === LANG_DEFAULT) {
        return $tags;
    }
    $title = $meta['og_title'] !== '' ? $meta['og_title'] : $meta['title'];
    foreach ($tags as $i => $tag) {
        switch ($tag[1] ?? '') {
            case 'og:locale':
                $tags[$i][2] = LANGS[lang()]['locale'];
                break;
            case 'og:site_name':
                $tags[$i][2] = (string) cfg('site_name');
                break;
            case 'og:title':
            case 'twitter:title':
                $tags[$i][2] = $title;
                break;
            case 'og:description':
            case 'twitter:description':
            case 'description':
                if ($meta['description'] !== '') {
                    $tags[$i][2] = $meta['description'];
                }
                break;
        }
    }
    return $tags;
}

/**
 * Dil seçimi — başlıqda: qlobus, cari dilin kodu və ox; basanda dillərin
 * siyahısı açılır (davranış assets/js/mobile-menu.js-dədir, JS olmasa da
 * keçidlər :focus-within ilə açılır).
 * Səhifənin başqa dildə versiyası yoxdursa (məsələn xəbər) heç nə çap olunmur —
 * sahibin qərarı: tərcüməsi olmayan səhifədə dil düyməsi göstərilmir.
 */
function lang_switcher(): string
{
    $versions = lang_versions();
    if (count($versions) < 2) {
        return '';
    }
    $cur = lang();
    $items = '';
    foreach ($versions as $code) {
        $current = $code === $cur;
        $items .= '<li><a class="itk-lang__item' . ($current ? ' is-current' : '') . '"'
            . ' href="' . e(lang_switch_url($code)) . '" hreflang="' . $code . '" lang="' . $code . '"'
            . ($current ? ' aria-current="true"' : '') . '>'
            . '<span class="itk-lang__name">' . e(LANGS[$code]['name']) . '</span>'
            . '<span class="itk-lang__tag" aria-hidden="true">' . e(LANGS[$code]['short']) . '</span></a></li>';
    }
    return "
<!-- itkin: dil seçimi -->"
        . '<div class="itk-lang">'
        . '<button class="itk-lang__btn" type="button" aria-haspopup="true" aria-expanded="false"'
        . ' aria-label="' . e(LANGS[$cur]['short'] . ' — ' . t('Dil seçimi')) . '">'
        . '<svg class="itk-lang__globe" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor"'
        . ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/>'
        . '<path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></svg>'
        . '<span class="itk-lang__code">' . e(LANGS[$cur]['short']) . '</span>'
        . '<svg class="itk-lang__chev" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>'
        . '</button>'
        . '<ul class="itk-lang__menu">' . $items . '</ul>'
        . '</div>'
        . '<!-- /itkin: dil seçimi -->';
}

/**
 * Bu daxili ünvanın verilmiş dildə versiyası varmı.
 *
 * Yalnız məzmunu tərcümə olunmuş səhifələrin ingiliscə versiyası var. Xəbərlər
 * tərcümə olunmur, ona görə onların (və yalnız xəbərlərdən ibarət siyahıların —
 * «Xəbərlər» səhifəsi, kateqoriyalar) ingiliscə versiyası yoxdur:
 *   - belə səhifədə dil düyməsi və hreflang="en" göstərilmir;
 *   - /en/... ünvanı azərbaycanca versiyaya yönləndirilir;
 *   - ingiliscə səhifələrdəki keçidlər (url()) birbaşa azərbaycanca versiyaya aparır.
 * Paneldə hər hansı xəbərə ingiliscə mətn yazılsa, o xəbər (və onun kateqoriyası,
 * «Xəbərlər» siyahısı) avtomatik olaraq ingiliscə versiyaya daxil olur.
 *
 * @param string $path daxili yol, məsələn "haqqimizda", "category/tedbirler/page/2", "kitabxana-blog/x"
 */
function lang_has(string $path, string $lang): bool
{
    if ($lang === LANG_DEFAULT) {
        return true;
    }
    static $memo = [];
    $path = trim(rawurldecode((string) strtok($path, '?#')), '/');
    if (isset($memo[$lang][$path])) {
        return $memo[$lang][$path];
    }

    $tr = static function (string $name) use ($lang): array {
        return data_load($name . '-' . $lang);
    };
    $parts = $path === '' ? [] : explode('/', $path);
    $has = false;

    if ($parts === []) {
        $page = find_by_slug(all_pages(), 'ana-sehife');
        $has = $page !== null && isset($tr('pages')[$page['id']]);
    } elseif ($parts[0] === 'itkinlr') {
        $has = count($parts) === 1 || $parts[1] === 'page'
            ? $tr('itkinlr') !== []
            : ($item = find_by_slug(all_missing(), $parts[1])) !== null && isset($tr('itkinlr')[$item['id']]);
    } elseif ($parts[0] === 'kitabxana-blog') {
        $has = count($parts) === 1
            ? $tr('kitabxana') !== []
            : ($item = find_by_slug(all_books(), $parts[1])) !== null && isset($tr('kitabxana')[$item['id']]);
    } elseif ($parts[0] === 'category' && isset($parts[1])) {
        $cat = find_category($parts[1]);
        if ($cat !== null && isset($tr('categories')[$cat['id']])) {
            foreach (posts_in_category((int) $cat['id']) as $post) {
                if (isset($tr('posts')[$post['id']])) {
                    $has = true;
                    break;
                }
            }
        }
    } elseif (count($parts) <= 3) {
        $page = find_by_slug(all_pages(), $parts[0]);
        if ($page !== null && $page['slug'] !== 'ana-sehife') {
            // «Xəbərlər» səhifəsi yalnız xəbərlərdən ibarətdir
            $has = $page['slug'] === 'xeberler' ? $tr('posts') !== [] : isset($tr('pages')[$page['id']]);
        } elseif (count($parts) === 1 && ($post = find_by_slug(all_posts(), $parts[0])) !== null) {
            $has = isset($tr('posts')[$post['id']]);
        }
    }

    return $memo[$lang][$path] = $has;
}
