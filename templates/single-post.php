<?php
/**
 * Tək xəbər səhifəsi / single post.
 * $post — data/posts.php-dən bir sətir.
 */

require_once dirname(__DIR__) . '/inc/nav.php';

$current = $post;
$post['cat_class'] = cat_classes($post);
$cat = post_category($post);

nav_context([
    'type'       => 'post',
    'slug'       => $post['slug'],
    'categories' => array_map(static function (int $id) {
        $c = category_by_id($id);
        return $c ? $c['slug'] : '';
    }, $post['categories'] ?? []),
]);

$crumbs = [];
if ($cat) {
    $crumbs[] = ['label' => $cat['name'], 'href' => url('category/' . $cat['slug']), 'rel' => 'tag'];
}
$crumbs[] = ['label' => $post['title'], 'href' => null];

[$prev, $next] = post_siblings($post);
$prev_base = '';
$related   = related_posts($post, 6);

$meta['title']       = $post['title'] . ' - ' . cfg('site_name');
$meta['og_title']    = $post['title'];
$meta['description'] = $post['description'] !== '' ? $post['description'] : excerpt($post['content'], 30);
$meta['image']       = !empty($post['thumb']['url']) ? abs_url_file($post['thumb']['url']) : '';
$meta['canonical']   = abs_url($post['slug']);
$meta['og_type']     = 'article';
$meta['body_class']  = body_class('wp-singular post-template-default single single-post postid-' . $post['id'] . ' single-format-standard', false, 'elementor-page-581');
$meta['schema_entry'] = $post;
$meta['elementor_post']  = elementor_post_json($post['id'], $post['title'], $post['thumb']['url'] ?? '');
?>
<?php include __DIR__ . '/single-post.body.php'; ?>
