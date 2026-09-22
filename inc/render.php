<?php
/**
 * Şablonların birləşdirilməsi / layout renderer.
 */

/** Hər səhifədə yüklənən üslublar */
const CSS_BASE = [
    'assets/vendor/plugins/elementor/assets/css/frontend-lite.min.css',
    'assets/vendor/plugins/elementor-pro/assets/css/frontend-lite.min.css',
    'assets/vendor/plugins/elementor/assets/lib/swiper/v8/css/swiper.min.css',
    'assets/vendor/plugins/sitepress-multilingual-cms/templates/language-switchers/legacy-list-horizontal/style.min.css',
    'assets/vendor/plugins/wpml-cms-nav/res/css/cms-navigation-base.css',
    'assets/vendor/plugins/wpml-cms-nav/res/css/cms-navigation.css',
    'assets/vendor/themes/hello-elementor/style.min.css',
    'assets/vendor/themes/hello-elementor/theme.min.css',
    'assets/vendor/themes/hello-elementor/header-footer.min.css',
    'assets/css/wp-inline.css',
    // Elementor vidjetlərinin inline üslubları — orijinalda səhifədən asılı olaraq
    // markupun içində verilir, burada hamısı bir faylda toplanıb
    'assets/css/elementor-widgets.css',
    'uploads/elementor/css/post-7.css',
    'uploads/elementor/css/global.css',
    'uploads/elementor/css/custom-jet-blocks.css',
    'uploads/elementor/css/post-245.css',
];

/** Şablona görə əlavə üslublar (Elementor şablon ID-ləri) */
const CSS_VIEW = [
    'home' => [
        'assets/vendor/plugins/elementor/assets/lib/animations/animations.min.css',
        'uploads/elementor/css/post-50.css',
        'uploads/elementor/css/post-23.css',
        'uploads/elementor/css/post-150.css',
        'uploads/elementor/css/post-214.css',
    ],
    'page-xeberler'             => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-327.css', 'uploads/elementor/css/post-214.css'],
    'page-haqqimizda'           => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-399.css', 'assets/vendor/plugins/elementor/assets/lib/e-gallery/css/e-gallery.min.css'],
    'page-elaqe'                => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-374.css'],
    'page-sekiller'             => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-662.css', 'assets/vendor/plugins/elementor/assets/lib/e-gallery/css/e-gallery.min.css'],
    'page-beynelxalq-senedler'  => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-830.css'],
    'page-milli-qanunvericilik' => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-869.css'],
    'page-kitabxana'            => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-904.css', 'uploads/elementor/css/post-906.css'],
    'single-post'               => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-581.css', 'uploads/elementor/css/post-214.css'],
    // post-942.css orijinal saytda da 404 qaytarır — kart üslubu şablonun içində inline verilib
    'single-kitabxana'          => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-928.css'],
    'single-itkinlr'            => ['uploads/elementor/css/post-334.css'],
    'archive-itkinlr'           => ['uploads/elementor/css/post-334.css'],
    'archive-category'          => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-588.css', 'uploads/elementor/css/post-214.css'],
    'archive-kitabxana'         => ['uploads/elementor/css/post-334.css', 'uploads/elementor/css/post-968.css', 'uploads/elementor/css/post-906.css'],
    '404'                       => ['uploads/elementor/css/post-334.css'],
];

/** Elementor qalereyası olan şablonlarda əlavə skript lazımdır */
const JS_VIEW = [
    'page-haqqimizda' => ['assets/vendor/plugins/elementor/assets/lib/e-gallery/js/e-gallery.min.js'],
    'page-sekiller'   => ['assets/vendor/plugins/elementor/assets/lib/e-gallery/js/e-gallery.min.js'],
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
    ];

    extract($vars, EXTR_SKIP);

    // Şablonu bufer içində işə salırıq ki, $meta-nı <head>-dən əvvəl bilək
    ob_start();
    include $file;
    $content = ob_get_clean();

    $styles  = array_merge(CSS_BASE, CSS_VIEW[$view] ?? []);
    $scripts = JS_VIEW[$view] ?? [];
    $header  = $view === 'home' ? 'header-home' : 'header-main';

    include dirname(__DIR__) . '/inc/head.php';
    include dirname(__DIR__) . '/inc/' . $header . '.php';
    echo $content;
    include dirname(__DIR__) . '/inc/footer.php';
}
