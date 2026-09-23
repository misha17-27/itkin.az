<?php
/**
 * Schema.org JSON-LD.
 *
 * Qraf data/ fayllarında orijinaldakı şəklində saxlanılır; sayt ünvanı orada
 * {{SITE}} nişanı ilə əvəzlənib, burada isə real ünvana çevrilir. Beləliklə
 * domen dəyişsə belə struktur məlumat düzgün qalır.
 */

/** Saytın kök ünvanı, sonda kəsik olmadan: https://itkin.az */
function site_origin(): string
{
    $configured = trim((string) cfg('site_url'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return rtrim(abs_url(), '/');
}

/**
 * Hazır qrafı çap üçün qaytarır.
 *
 * @param string $stored data/ faylından gələn qraf ({{SITE}} nişanı ilə)
 */
function schema_from_store(string $stored): string
{
    // Ünvan JSON sətri kimi qaçırılır: </script> və dırnaq heç vaxt qrafı qıra bilməsin
    $origin = substr((string) json_encode(site_origin(),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 1, -1);
    return str_replace('{{SITE}}', $origin, $stored);
}

/**
 * Saxlanılmış qraf yoxdursa (məsələn 404 səhifəsi) minimal qraf qurur.
 */
function schema_fallback(array $meta, array $crumbs = []): string
{
    $home = site_origin() . '/';
    $url  = $meta['canonical'];
    $lang = cfg('locale', 'az');

    $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana səhifə', 'item' => $home]];
    $pos = 2;
    foreach ($crumbs as $crumb) {
        $item = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $crumb['label']];
        if (!empty($crumb['href'])) {
            $item['item'] = abs_url(trim(str_replace(base_path(), '', $crumb['href']), '/'));
        }
        $list[] = $item;
    }

    $logo = abs_url_file('uploads/2023/11/logo.png');

    return json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage', '@id' => $url, 'url' => $url, 'name' => $meta['title'],
                'isPartOf' => ['@id' => $home . '#website'],
                'breadcrumb' => ['@id' => $url . '#breadcrumb'],
                'inLanguage' => $lang,
                'potentialAction' => [['@type' => 'ReadAction', 'target' => [$url]]],
            ],
            ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $list],
            [
                '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home,
                'name' => cfg('site_name'), 'description' => cfg('site_name'),
                'publisher' => ['@id' => $home . '#organization'],
                'alternateName' => cfg('site_tagline'),
                'potentialAction' => [[
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ]],
                'inLanguage' => $lang,
            ],
            [
                '@type' => 'Organization', '@id' => $home . '#organization',
                'name' => cfg('site_tagline'), 'alternateName' => 'İctimai Birliyi', 'url' => $home,
                'logo' => [
                    '@type' => 'ImageObject', 'inLanguage' => $lang,
                    '@id' => $home . '#/schema/logo/image/',
                    'url' => $logo, 'contentUrl' => $logo,
                    'width' => 560, 'height' => 415, 'caption' => cfg('site_tagline'),
                ],
                'image' => ['@id' => $home . '#/schema/logo/image/'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);   // başlıqdakı </script> qrafı qırmasın
}

/** <head> üçün hazır JSON-LD */
function schema_graph(array $meta, array $crumbs = []): string
{
    $stored = (string) ($meta['schema'] ?? '');
    return $stored !== '' ? schema_from_store($stored) : schema_fallback($meta, $crumbs);
}
