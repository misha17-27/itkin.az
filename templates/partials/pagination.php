<?php
/**
 * Səhifələmə / pagination (Elementor Loop Grid).
 * $pager — paginate() nəticəsi, $page_param — sorğu parametrinin adı,
 * $pager_base — səhifənin yolu (məsələn "xeberler" və ya "category/tedbirler").
 */

$total   = (int) $pager['pages'];
$current = (int) $pager['page'];

if ($total < 2) {
    return;
}

$end_size = 1;
$mid_size = 2;

$link = static function (int $n) use ($page_param, $pager_base) {
    return url($pager_base) . '?' . rawurlencode($page_param) . '=' . $n;
};
?>
<div class="e-load-more-anchor" data-page="<?= $current ?>" data-max-page="<?= $total ?>" data-next-page="<?= $current < $total ? e($link($current + 1)) : '' ?>"></div>
<nav class="elementor-pagination" aria-label="Pagination">
<?php
$dots = false;
for ($n = 1; $n <= $total; $n++) {
    if ($n === $current) {
        echo '<span aria-current="page" class="page-numbers current"><span class="elementor-screen-only">Page</span>' . $n . '</span>' . "\n";
        $dots = true;
    } elseif (
        $n <= $end_size
        || ($n > $current - $mid_size - 1 && $n < $current + $mid_size + 1)
        || $n > $total - $end_size
    ) {
        echo '<a class="page-numbers" href="' . e($link($n)) . '"><span class="elementor-screen-only">Page</span>' . $n . '</a>' . "\n";
        $dots = true;
    } elseif ($dots) {
        echo '<span class="page-numbers dots">&hellip;</span>' . "\n";
        $dots = false;
    }
}
?>
</nav>
