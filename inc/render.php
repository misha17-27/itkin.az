<?php
/**
 * Şablonların birləşdirilməsi / layout renderer.
 */

/**
 * Üslublar orijinaldakı ardıcıllıqla yüklənir — kaskad eyni qalsın deyə.
 * wp-inline.css və elementor-widgets.css orijinalda <style> blokları kimi
 * bütün <link>-lərdən əvvəl gəlir, ona görə siyahının başındadır.
 * '__FONTS__' Google Fonts keçidinin yeridir.
 */
const CSS_LEAD = [
    'assets/css/wp-inline.css',
    'assets/css/elementor-widgets.css',
];

const CSS_COMMON = [
    'assets/vendor/plugins/sitepress-multilingual-cms/templates/language-switchers/legacy-list-horizontal/style.min.css',
    'assets/vendor/plugins/wpml-cms-nav/res/css/cms-navigation-base.css',
    'assets/vendor/plugins/wpml-cms-nav/res/css/cms-navigation.css',
    'assets/vendor/themes/hello-elementor/style.min.css',
    'assets/vendor/themes/hello-elementor/theme.min.css',
    'assets/vendor/themes/hello-elementor/header-footer.min.css',
    'assets/vendor/plugins/elementor/assets/css/frontend-lite.min.css',
    'uploads/elementor/css/post-7.css',
    'uploads/elementor/css/custom-jet-blocks.css',
    'assets/vendor/plugins/elementor/assets/lib/swiper/v8/css/swiper.min.css',
    'assets/vendor/plugins/elementor-pro/assets/css/frontend-lite.min.css',
    'uploads/elementor/css/global.css',
];

const CSS_TAIL = [
    'home' => [
        'uploads/elementor/css/post-23.css',
        'uploads/elementor/css/post-50.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
        'uploads/elementor/css/post-150.css',
        'uploads/elementor/css/post-214.css',
        'assets/vendor/plugins/elementor/assets/lib/animations/animations.min.css',
    ],
    'page-xeberler' => [
        'uploads/elementor/css/post-327.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
        'uploads/elementor/css/post-214.css',
    ],
    'page-haqqimizda' => [
        'uploads/elementor/css/post-399.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
        'assets/vendor/plugins/elementor/assets/lib/e-gallery/css/e-gallery.min.css',
    ],
    'page-elaqe' => [
        'uploads/elementor/css/post-374.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
    ],
    'page-sekiller' => [
        'uploads/elementor/css/post-662.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
        'assets/vendor/plugins/elementor/assets/lib/e-gallery/css/e-gallery.min.css',
    ],
    'page-beynelxalq-senedler' => [
        'uploads/elementor/css/post-830.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
    ],
    'page-milli-qanunvericilik' => [
        'uploads/elementor/css/post-869.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
    ],
    'page-kitabxana' => [
        'uploads/elementor/css/post-904.css',
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
        'uploads/elementor/css/post-906.css',
    ],
    'single-post' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        'uploads/elementor/css/post-581.css',
        '__FONTS__',
        'uploads/elementor/css/post-214.css',
    ],
    'single-kitabxana' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        'uploads/elementor/css/post-928.css',
        '__FONTS__',
    ],
    'single-itkinlr' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
    ],
    'archive-itkinlr' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
    ],
    'archive-category' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        'uploads/elementor/css/post-588.css',
        '__FONTS__',
        'uploads/elementor/css/post-214.css',
    ],
    'archive-kitabxana' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        'uploads/elementor/css/post-968.css',
        '__FONTS__',
        'uploads/elementor/css/post-906.css',
    ],
    '404' => [
        'uploads/elementor/css/post-334.css',
        'uploads/elementor/css/post-245.css',
        '__FONTS__',
    ],
];

/**
 * Surətə əlavə edilmiş fayllar (orijinalda yoxdur).
 * Üslub ən sonda yüklənir ki, lazım gələndə digərlərini üstələyə bilsin.
 */
const CSS_CUSTOM = 'assets/css/custom.css';
const JS_CUSTOM  = 'assets/js/mobile-menu.js';

/** Bəzi üslublar orijinalda media="screen" ilə verilir */
const CSS_MEDIA = [
    'assets/vendor/plugins/wpml-cms-nav/res/css/cms-navigation-base.css' => 'screen',
    'assets/vendor/plugins/wpml-cms-nav/res/css/cms-navigation.css'      => 'screen',
];

/**
 * Google Fonts ailələri şablona görə dəyişir (Elementor yalnız istifadə olunan
 * şriftləri sifariş edir) — orijinaldakı siyahı və ardıcıllıq saxlanılıb.
 */
const FONTS_DEFAULT = ['Roboto', 'Roboto+Slab', 'Montserrat', 'Inter'];
const FONTS_VIEW = [
    'home'                      => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-xeberler'             => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-haqqimizda'           => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-kitabxana'            => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-milli-qanunvericilik' => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-beynelxalq-senedler'  => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-sekiller'             => ['Roboto', 'Roboto+Slab', 'Inter', 'Montserrat'],
    'page-elaqe'                => ['Roboto', 'Roboto+Slab', 'Inter', 'Poppins', 'Montserrat'],
    'single-post'               => ['Roboto', 'Roboto+Slab', 'Montserrat', 'Inter', 'Poppins'],
    'single-kitabxana'          => ['Roboto', 'Roboto+Slab', 'Montserrat', 'Inter', 'Poppins'],
];

/** Orijinalda hreflang yalnız bu şablonlarda verilmir */
const NO_HREFLANG = ['single-itkinlr', 'archive-itkinlr', 'archive-kitabxana', 'single-kitabxana', '404'];


/**
 * Şablondan asılı olan skriptlər.
 * WordPress bunları yalnız lazım olan səhifədə yükləyir — orijinaldakı
 * ardıcıllıq və id-lər saxlanılıb (smartmenus ilə elementor runtime arasında).
 */
const JS_LIB = [
    'mediaelement-core'    => ['mediaelement-core-js',    'assets/vendor/wp-includes/js/mediaelement/mediaelement-and-player.min.js', 'mediaelement-core-js-before', 'l10n'],
    'mediaelement-migrate' => ['mediaelement-migrate-js', 'assets/vendor/wp-includes/js/mediaelement/mediaelement-migrate.min.js'],
    'wp-mediaelement'      => ['wp-mediaelement-js',      'assets/vendor/wp-includes/js/mediaelement/wp-mediaelement.min.js', 'mediaelement-js-extra', 'settings'],
    'mediaelement-vimeo'   => ['mediaelement-vimeo-js',   'assets/vendor/wp-includes/js/mediaelement/renderers/vimeo.min.js'],
    'imagesloaded'         => ['imagesloaded-js',         'assets/vendor/wp-includes/js/imagesloaded.min.js'],
    'jquery-numerator'     => ['jquery-numerator-js',     'assets/vendor/plugins/elementor/assets/lib/jquery-numerator/jquery-numerator.min.js'],
    'e-gallery'            => ['elementor-gallery-js',    'assets/vendor/plugins/elementor/assets/lib/e-gallery/js/e-gallery.min.js'],
];

const JS_VIEW = [
    // sayğac vidjetləri jquery-numerator olmadan 0-da qalır
    'home'               => ['imagesloaded', 'jquery-numerator'],
    'page-xeberler'      => ['imagesloaded'],
    'page-kitabxana'     => ['imagesloaded'],
    'page-haqqimizda'    => ['e-gallery'],
    'page-sekiller'      => ['e-gallery'],
    'single-post'        => ['imagesloaded'],
    'single-kitabxana'   => ['imagesloaded'],
    'archive-category'   => ['imagesloaded'],
    'archive-kitabxana'  => ['imagesloaded'],
    // itkinlr, elaqe, sənəd səhifələri və 404 əlavə skript yükləmir
];

/** Səhifədə video/audio varsa lazım olan MediaElement dəsti */
const JS_MEDIAELEMENT = ['mediaelement-core', 'mediaelement-migrate', 'wp-mediaelement', 'mediaelement-vimeo'];
const CSS_MEDIAELEMENT = [
    'assets/vendor/wp-includes/js/mediaelement/mediaelementplayer-legacy.min.css',
    'assets/vendor/wp-includes/js/mediaelement/wp-mediaelement.min.css',
];

/**
 * Səhifəni çap edir.
 *
 * @param string $view templates/ qovluğundakı fayl adı
 * @param array  $vars şablona ötürülən dəyişənlər
 */
function render(string $view, array $vars = []): void
{
    $file = dirname(__DIR__) . '/templates/' . $view . '.php';
    if (!is_file($file)) {
        $view = '404';
        $file = dirname(__DIR__) . '/templates/404.php';
    }

    // Şablon öz meta məlumatını bu dəyişənlərlə təyin edir
    $meta = [
        'title'       => cfg('site_name'),
        'description' => '',
        'image'       => '',
        'canonical'   => abs_url(ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '', '/')),
        'body_class'  => '',
        'og_title'    => '',
        'og_type'     => 'article',
        // şablon öz ehtiyacına görə əlavə fayl tələb edə bilər
        'robots'      => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
        'rel_prev'    => '',
        'rel_next'    => '',
        'head_meta'   => [],
        'schema'      => '',
        'extra_js'    => [],
        'extra_css'   => [],
    ];

    extract($vars, EXTR_SKIP);

    // Şablonu bufer içində işə salırıq ki, $meta-nı <head>-dən əvvəl bilək
    ob_start();
    include $file;
    $content = ob_get_clean();

    $styles = array_merge(CSS_LEAD, CSS_COMMON, CSS_TAIL[$view] ?? CSS_TAIL['404']);

    // Səhifəyə xas fayllar şablonun standart dəstindən əvvəl gəlir (orijinaldakı kimi)
    $scripts = [];
    foreach (array_merge($meta['extra_js'], JS_VIEW[$view] ?? []) as $key) {
        if (isset(JS_LIB[$key]) && !isset($scripts[$key])) {
            $scripts[$key] = JS_LIB[$key];
        }
    }

    $header   = $view === 'home' ? 'header-home' : 'header-main';
    $fonts    = FONTS_VIEW[$view] ?? FONTS_DEFAULT;
    $hreflang = !in_array($view, NO_HREFLANG, true);

    ob_start();
    include dirname(__DIR__) . '/inc/head.php';
    include dirname(__DIR__) . '/inc/' . $header . '.php';
    echo $content;
    include dirname(__DIR__) . '/inc/footer.php';
    echo dedupe_stylesheets(ob_get_clean());
}

/**
 * Eyni üslub bir neçə vidjet tərəfindən çağırıla bilər; WordPress onu səhifəyə
 * bir dəfə qoşur. Həm <link> fayllarının, həm də eyni məzmunlu inline <style>
 * bloklarının təkrarını atırıq (ilk nüsxə saxlanılır).
 *
 * Kartların öz <style id="loop-dynamic-...">-ları hər dəfə fərqli olduğuna görə
 * məzmuna görə müqayisə onlara toxunmur.
 */
function dedupe_stylesheets(string $html): string
{
    $seenLinks = [];
    $html = preg_replace_callback(
        '#<link[^>]*rel=["\']stylesheet["\'][^>]*>#i',
        static function (array $m) use (&$seenLinks) {
            if (!preg_match('#href=["\']([^"\']+)["\']#i', $m[0], $h)) {
                return $m[0];
            }
            $key = strtok($h[1], '?');
            if (isset($seenLinks[$key])) {
                return '';
            }
            $seenLinks[$key] = true;
            return $m[0];
        },
        $html
    );

    $seenStyles = [];
    return preg_replace_callback(
        '#<style(?:\s[^>]*)?>(.*?)</style>#is',
        static function (array $m) use (&$seenStyles) {
            $key = md5($m[1]);
            if (isset($seenStyles[$key])) {
                return '';
            }
            $seenStyles[$key] = true;
            return $m[0];
        },
        $html
    );
}
