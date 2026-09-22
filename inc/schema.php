<?php
/**
 * Schema.org JSON-LD qrafı — Yoast SEO-nun verdiyi quruluşun eynisi.
 */

function schema_graph(array $meta, array $crumbs = [], ?array $entry = null, string $entryType = ''): string
{
    $home = rtrim(abs_url(), '/') . '/';
    $url  = $meta['canonical'];
    $lang = cfg('locale', 'az');

    $graph = [];

    $hasImage = !empty($meta['image']);
    $imageId  = $url . '#primaryimage';

    // --- Article (yalnız yazı və kitablar üçün)
    if ($entry !== null) {
        $article = [
            '@type'    => 'Article',
            '@id'      => $url . '#article',
            'isPartOf' => ['@id' => $url],
            'author'   => ['name' => 'jgitd', '@id' => $home . '#/schema/person/c03ab98ad06c7410a10a2560d00ae3eb'],
            'headline' => $entry['title'],
            'datePublished'    => date('c', strtotime($entry['date'])),
            'dateModified'     => date('c', strtotime($entry['modified'] ?? $entry['date'])),
            'mainEntityOfPage' => ['@id' => $url],
            'publisher'        => ['@id' => $home . '#organization'],
        ];
        if ($hasImage) {
            $article['image']        = ['@id' => $imageId];
            $article['thumbnailUrl'] = $meta['image'];
        }
        $sections = [];
        foreach ($entry['categories'] ?? [] as $id) {
            $cat = category_by_id((int) $id);
            if ($cat) {
                $sections[] = $cat['name'];
            }
        }
        if ($sections) {
            $article['articleSection'] = $sections;
        }
        $article['inLanguage'] = $lang;
        $graph[] = $article;
    }

    // --- WebPage
    $page = [
        '@type'    => 'WebPage',
        '@id'      => $url,
        'url'      => $url,
        'name'     => $meta['title'],
        'isPartOf' => ['@id' => $home . '#website'],
    ];
    if ($hasImage) {
        $page['primaryImageOfPage'] = ['@id' => $imageId];
        $page['image']             = ['@id' => $imageId];
        $page['thumbnailUrl']      = $meta['image'];
    }
    if ($entry !== null) {
        $page['datePublished'] = date('c', strtotime($entry['date']));
        $page['dateModified']  = date('c', strtotime($entry['modified'] ?? $entry['date']));
    }
    $page['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
    $page['inLanguage'] = $lang;
    $page['potentialAction'] = [['@type' => 'ReadAction', 'target' => [$url]]];
    if ($meta['description'] !== '') {
        $page['description'] = $meta['description'];
    }
    $graph[] = $page;

    // --- ImageObject
    if ($hasImage) {
        $graph[] = [
            '@type'      => 'ImageObject',
            'inLanguage' => $lang,
            '@id'        => $imageId,
            'url'        => $meta['image'],
            'contentUrl' => $meta['image'],
        ];
    }

    // --- BreadcrumbList
    $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana səhifə', 'item' => $home]];
    $pos = 2;
    foreach ($crumbs as $crumb) {
        $item = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $crumb['label']];
        if (!empty($crumb['href'])) {
            $item['item'] = abs_url(trim(str_replace(base_path(), '', $crumb['href']), '/'));
        }
        $list[] = $item;
    }
    $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $list];

    // --- WebSite
    $graph[] = [
        '@type'         => 'WebSite',
        '@id'           => $home . '#website',
        'url'           => $home,
        'name'          => cfg('site_name'),
        'description'   => cfg('site_name'),
        'publisher'     => ['@id' => $home . '#organization'],
        'alternateName' => cfg('site_tagline'),
        'potentialAction' => [[
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ]],
        'inLanguage' => $lang,
    ];

    // --- Organization
    $logo = abs_url_file('uploads/2023/11/logo.png');
    $graph[] = [
        '@type'         => 'Organization',
        '@id'           => $home . '#organization',
        'name'          => cfg('site_tagline'),
        'alternateName' => 'İctimai Birliyi',
        'url'           => $home,
        'logo' => [
            '@type'      => 'ImageObject',
            'inLanguage' => $lang,
            '@id'        => $home . '#/schema/logo/image/',
            'url'        => $logo,
            'contentUrl' => $logo,
            'width'      => 560,
            'height'     => 415,
            'caption'    => cfg('site_tagline'),
        ],
        'image' => ['@id' => $home . '#/schema/logo/image/'],
    ];

    // --- Person (müəllif)
    if ($entry !== null) {
        $graph[] = [
            '@type'  => 'Person',
            '@id'    => $home . '#/schema/person/c03ab98ad06c7410a10a2560d00ae3eb',
            'name'   => 'jgitd',
            'sameAs' => [rtrim($home, '/')],
        ];
    }

    return json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
