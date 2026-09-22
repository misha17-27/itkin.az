<?php
/**
 * Tək kitab səhifəsi / single library book.
 * $item — data/kitabxana.php-dən bir sətir.
 */

require_once dirname(__DIR__) . '/inc/nav.php';

$post = $item;
$post['cat_class'] = '';
$current = $post;

nav_context(['type' => 'kitabxana-blog', 'slug' => 'kitabxana-blog/' . $post['slug'], 'categories' => []]);

$crumbs = [
    ['label' => 'Kitabxana', 'href' => url('kitabxana-blog'), 'rel' => 'tag'],
    ['label' => $post['title'], 'href' => null],
];

$books = all_books();
$idx = null;
foreach ($books as $i => $b) {
    if ($b['id'] === $post['id']) {
        $idx = $i;
        break;
    }
}
// Siyahı yenidən köhnəyə düzülüb: "əvvəlki" daha köhnə kitabdır
$prev = $idx !== null ? ($books[$idx + 1] ?? null) : null;
$next = $idx !== null ? ($books[$idx - 1] ?? null) : null;
$prev_base = 'kitabxana-blog/';

$related = array_values(array_filter($books, static function (array $b) use ($post) {
    return $b['id'] !== $post['id'];
}));

$meta['title']       = $post['title'] . ' - ' . cfg('site_name');
$meta['og_title']    = $post['title'];
$meta['description'] = $post['description'] !== '' ? $post['description'] : excerpt($post['content'], 30);
$meta['image']       = !empty($post['thumb']['url']) ? abs_url_file($post['thumb']['url']) : '';
$meta['canonical']   = abs_url('kitabxana-blog/' . $post['slug']);
$meta['og_type']     = 'article';
$meta['body_class']  = body_class('wp-singular kitabxana-blog-template-default single single-kitabxana-blog postid-' . $post['id'], false, 'elementor-page-928');
$meta['head_meta']   = $post['head_meta'] ?? [];
$meta['schema']      = $post['schema'] ?? '';
$meta['schema_entry'] = $post;
$meta['elementor_post'] = elementor_post_json($post['id'], $post['title'], $post['thumb']['url'] ?? '');
?>
<?php include __DIR__ . '/single-kitabxana.body.php'; ?>
