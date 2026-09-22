<?php
/**
 * Menyunun çap edilməsi — Elementor Nav Menu vidjetinin markupu ilə eyni.
 */

/** Cari səhifənin menyu konteksti. Şablonlar bunu təyin edir. */
function nav_context(?array $ctx = null): array
{
    static $current = ['type' => '', 'slug' => '', 'page_id' => 0, 'categories' => []];
    if ($ctx !== null) {
        $current = $ctx + $current;
    }
    return $current;
}

/** Bənd cari səhifəyə uyğun gəlirmi? */
function nav_is_current(array $item): bool
{
    $ctx = nav_context();
    if (empty($item['path']) || !empty($item['external'])) {
        return false;
    }
    if ($item['object'] === 'page') {
        return $ctx['type'] === 'page' && $ctx['slug'] === $item['path'];
    }
    if ($item['object'] === 'category') {
        return $ctx['type'] === 'category' && 'category/' . $ctx['slug'] === $item['path'];
    }
    return false;
}

/** Yazı bu kateqoriya bəndinə aiddirmi? (tək yazı səhifəsində valideyn işarəsi) */
function nav_is_post_parent(array $item): bool
{
    $ctx = nav_context();
    if ($ctx['type'] !== 'post' || $item['object'] !== 'category' || empty($item['path'])) {
        return false;
    }
    foreach ($ctx['categories'] as $slug) {
        if ($item['path'] === 'category/' . $slug) {
            return true;
        }
    }
    return false;
}

/** Alt bəndlərdən biri aktivdirmi? */
function nav_has_current_child(array $item): bool
{
    foreach ($item['children'] as $child) {
        if (nav_is_current($child) || nav_has_current_child($child)) {
            return true;
        }
    }
    return false;
}

/** Bəndin <li> sinifləri — WordPress-in verdiyi ardıcıllıqla */
function nav_li_class(array $item, bool $top): string
{
    $c = ['menu-item', 'menu-item-type-' . $item['type'], 'menu-item-object-' . $item['object']];

    if (nav_is_current($item)) {
        $c[] = 'current-menu-item';
        if ($item['object'] === 'page') {
            $c[] = 'page_item';
            $c[] = 'page-item-' . ($item['page_id'] ?? 0);
            $c[] = 'current_page_item';
        }
    } elseif (nav_is_post_parent($item)) {
        $c[] = 'current-post-ancestor';
        $c[] = 'current-menu-parent';
        $c[] = 'current-post-parent';
    } elseif ($item['children'] && nav_has_current_child($item)) {
        $c[] = 'current-menu-ancestor';
        $c[] = 'current-menu-parent';
    }

    if ($item['children']) {
        $c[] = 'menu-item-has-children';
    }
    $c[] = 'menu-item-' . $item['id'];

    return implode(' ', $c);
}

/** Bəndin <a> sinifləri */
function nav_a_class(array $item, bool $top): string
{
    $c = [$top ? 'elementor-item' : 'elementor-sub-item'];
    if ($top && $item['path'] === null) {
        $c[] = 'elementor-item-anchor';
    }
    if (nav_is_current($item)) {
        $c[] = 'elementor-item-active';
    }
    return implode(' ', $c);
}

/** Bəndin ünvanı */
function nav_href(array $item): string
{
    if ($item['path'] === null) {
        return '#';
    }
    if (!empty($item['external'])) {
        return $item['path'];
    }
    return url($item['path']);
}

/**
 * Menyunu çap edir.
 *
 * @param string $ulId     <ul> elementinin id-si
 * @param bool   $dropdown mobil (dropdown) variant — bəndlərə tabindex="-1" əlavə olunur
 */
function nav_menu(string $ulId, bool $dropdown = false, string $menu = 'menu', string $extraClass = ''): void
{
    $items = data_load($menu);
    $class = 'elementor-nav-menu' . ($extraClass !== '' ? ' ' . $extraClass : '');
    echo '<ul id="' . e($ulId) . '" class="' . e($class) . '">';
    nav_items($items, true, $dropdown);
    echo '</ul>';
}

function nav_items(array $items, bool $top, bool $dropdown): void
{
    foreach ($items as $item) {
        $tab = $dropdown ? ' tabindex="-1"' : '';
        echo '<li class="' . nav_li_class($item, $top) . '">';
        echo '<a href="' . e(nav_href($item)) . '"'
            . (nav_is_current($item) ? ' aria-current="page"' : '')
            . ' class="' . nav_a_class($item, $top) . '"' . $tab . '>'
            . $item['label'] . '</a>';
        if ($item['children']) {
            echo "\n" . '<ul class="sub-menu elementor-nav-menu--dropdown">' . "\n";
            nav_items($item['children'], false, $dropdown);
            echo '</ul>' . "\n";
        }
        echo '</li>' . "\n";
    }
}
