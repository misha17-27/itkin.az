<?php
/** Kateqoriyalar bölməsi */

$rows = data_load('categories');
$id   = (int) ($_GET['id'] ?? 0);

if ($action === 'delete' && $id > 0) {
    admin_require_delete('categories');
    if (!admin_find($rows, $id)) {
        admin_redirect(['section' => 'categories'], 'Belə kateqoriya tapılmadı — yəqin artıq silinib.', 'error');
    }
    $used = 0;
    foreach (data_load('posts') as $p) {
        if (in_array($id, array_map('intval', $p['categories'] ?? []), true)) { $used++; }
    }
    if ($used > 0) {
        admin_redirect(['section' => 'categories'],
            'Silinmədi: bu kateqoriyada ' . $used . ' yazı var. Əvvəlcə onları başqa kateqoriyaya keçirin.', 'error');
    }
    $item = admin_find($rows, $id);
    store_save('categories', admin_delete($rows, $id), 'Kateqoriyalar / categories');
    admin_redirect(['section' => 'categories'], '“' . ($item['name'] ?? '') . '” silindi.');
}

$errors = [];
$item = $id > 0 ? admin_find($rows, $id) : null;
if ($id > 0 && !$item) {
    admin_redirect(['section' => 'categories'], 'Belə kateqoriya tapılmadı.', 'error');
}

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) { $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.'; }

    $name = post_str('name');
    if ($name === '') { $errors[] = 'Ad boş ola bilməz.'; }

    $slug = store_slug(post_str('slug') ?: $name);
    $slug = store_unique_slug($rows, $slug, $id > 0 ? $id : null);

    if (!$errors) {
        $isNew = $id === 0;
        $newId = $isNew ? store_next_id($rows) : $id;
        $prev  = $item ?? [];
        $saved = [
            'id'          => $newId,
            'slug'        => $slug,
            'name'        => $name,
            'description' => post_str('description'),
            'count'       => (int) ($prev['count'] ?? 0),
            'doc_title'   => $name . ' Archives - ' . cfg('site_name'),
            'head_meta'   => admin_sync_meta($prev['head_meta'] ?? [], ['og:title' => $name . ' Archives']),
            'schema'      => $prev['schema'] ?? '',
        ];
        store_save('categories', admin_upsert($rows, $saved), 'Kateqoriyalar / categories');
        admin_redirect(['section' => 'categories'], $isNew ? 'Kateqoriya əlavə olundu.' : 'Yadda saxlanıldı.');
    }
}

if ($action === 'edit') {
    $isNew = $id === 0;
    $item  = $item ?? ['id' => 0, 'name' => '', 'slug' => '', 'description' => ''];
    admin_shell_start('categories', $isNew ? 'Yeni kateqoriya' : 'Kateqoriyanı redaktə et');
    f_errors($errors);
    f_open(['section' => 'categories', 'action' => 'edit', 'id' => $id]);
    ?>
	<div class="card"><div class="card__body" style="max-width:640px">
		<?php
        f_text('name', 'Ad', (string) $item['name'], ['required' => true, 'data' => 'slug-source']);
        f_text('slug', 'Ünvan (slug)', (string) $item['slug'], [
            'data' => 'slug-target',
            'hint' => 'Səhifənin ünvanı: <code>/category/<b>slug</b>/</code>',
        ]);
        f_textarea('description', 'Təsvir', (string) $item['description'], ['rows' => 3]);
        ?>
	</div></div>
	<?php f_actions(admin_url(['section' => 'categories']),
        $isNew ? null : admin_url(['section' => 'categories', 'action' => 'delete', 'id' => $id])); ?>
    <?php
    f_close();
    admin_shell_end();
    return;
}

$counts = [];
foreach (data_load('posts') as $p) {
    foreach (array_map('intval', $p['categories'] ?? []) as $cid) {
        $counts[$cid] = ($counts[$cid] ?? 0) + 1;
    }
}

admin_shell_start('categories', 'Kateqoriyalar', [
    ['href' => admin_url(['section' => 'categories', 'action' => 'edit']), 'label' => '+ Yeni kateqoriya', 'primary' => true],
]);
?>
<table class="table">
	<thead><tr><th>Ad</th><th style="width:220px">Ünvan</th><th style="width:100px">Yazı</th><th></th></tr></thead>
	<tbody>
<?php foreach ($rows as $row): ?>
		<tr>
			<td><a class="table__title" href="<?= e(admin_url(['section' => 'categories', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['name']) ?></a></td>
			<td class="table__meta">/category/<?= e($row['slug']) ?>/</td>
			<td class="table__meta"><?= (int) ($counts[$row['id']] ?? 0) ?></td>
			<td class="is-right"><div class="table__actions">
				<a class="btn btn--sm" href="<?= e(url('category/' . $row['slug'])) ?>" target="_blank" rel="noopener">Bax</a>
				<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'categories', 'action' => 'edit', 'id' => $row['id']])) ?>">Redaktə</a>
			</div></td>
		</tr>
<?php endforeach; ?>
	</tbody>
</table>
<?php
admin_shell_end();
