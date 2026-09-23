<?php
/**
 * Statik səhifələr üçün ümumi köməkçilər.
 * Mövzu bəzi səhifələri <main id="content"> sarğısı ilə, bəzilərini isə
 * (Elementor "tam en" şablonu) sarğısız verir — fərq data/pages.php-də saxlanılır.
 */

/** Səhifənin slug-ı -> redaktə sahələrinin prefiksi (data/page-fields.php) */
const PAGE_KEYS = [
    'ana-sehife'           => 'home',
    'haqqimizda'           => 'haqqimizda',
    'elaqe'                => 'elaqe',
    'beynelxalq-senedler'  => 'senedler',
    'milli-qanunvericilik' => 'qanun',
    'sekiller'             => 'sekiller',
    'kitabxana'            => 'kitabxana',
    'xeberler'             => 'xeberler',
];

/** Səhifənin məlumatı + $meta-nın doldurulması */
function page_setup(string $slug, array &$meta): array
{
    $info = find_by_slug(all_pages(), $slug);
    if (!$info) {
        $info = [
            'id' => 0, 'slug' => $slug, 'title' => $slug, 'description' => '', 'image' => '',
            'body_class' => body_class('wp-singular page', false, '', true), 'main_class' => '',
        ];
    }

    $meta['title']       = ($info['doc_title'] ?? '') ?: ($info['title'] . ' - ' . cfg('site_name'));
    $meta['og_title']    = $info['title'];
    $meta['og_type']     = $slug === 'ana-sehife' ? 'website' : 'article';
    $meta['description'] = $info['description'];
    $meta['image']       = $info['image'] !== '' ? abs_url_file($info['image']) : '';
    $meta['canonical']   = abs_url($slug === 'ana-sehife' ? '' : $slug);
    $meta['body_class']  = $info['body_class'];
    $meta['head_meta']   = $info['head_meta'] ?? [];
    $meta['schema']      = $info['schema'] ?? '';
    $meta['elementor_post'] = elementor_post_json((int) $info['id'], $info['title']);
    $meta['page_key']    = PAGE_KEYS[$slug] ?? '';

    return $info;
}

/** Mövzunun <main> sarğısını açır (lazım olduqda) */
function main_open(array $info): void
{
    if (($info['main_class'] ?? '') === '') {
        return;
    }
    echo '<main id="content" class="' . e($info['main_class']) . '">' . "\n\n\t\n\t"
       . '<div class="page-content">' . "\n\t\t\t\t";
}

/** Mövzunun <main> sarğısını bağlayır */
function main_close(array $info): void
{
    if (($info['main_class'] ?? '') === '') {
        return;
    }
    echo "\n\t\t\t\t" . '<div class="post-tags">' . "\n\t\t\t\t\t" . '</div>' . "\n\t\t\t"
       . '</div>' . "\n\n\t\n" . '</main>' . "\n";
}
