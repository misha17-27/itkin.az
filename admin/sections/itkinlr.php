<?php
/** İtkinlər bölməsi / missing persons section */

$rows = data_load('itkinlr');
$id   = (int) ($_GET['id'] ?? 0);

if ($action === 'delete' && $id > 0) {
    admin_require_delete('itkinlr');
    $item = admin_find($rows, $id);
    if (!$item) {
        admin_redirect(['section' => 'itkinlr'], 'Belə qeyd tapılmadı — yəqin artıq silinib.', 'error');
    }
    store_save('itkinlr', admin_delete($rows, $id), 'İtkin düşmüş şəxslər / missing persons');
    admin_redirect(['section' => 'itkinlr'], '“' . ($item['title'] ?? '') . '” silindi.');
}

$errors = [];
$item = $id > 0 ? admin_find($rows, $id) : null;
if ($id > 0 && !$item) {
    admin_redirect(['section' => 'itkinlr'], 'Belə qeyd tapılmadı.', 'error');
}

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }

    $title = post_str('title');
    if ($title === '') {
        $errors[] = 'Ad, soyad boş ola bilməz.';
    }

    $slug = store_slug(post_str('slug') ?: $title);
    $slug = store_unique_slug($rows, $slug, $id > 0 ? $id : null);

    if (!$errors) {
        $isNew = $id === 0;
        $newId = $isNew ? store_next_id($rows) : $id;
        $prev  = $item ?? [];

        $thumb = admin_thumb_from_path(post_str('thumb'), $prev['thumb'] ?? []);
        // arxivdə və ana səhifədə fərqli siniflər işlədilir
        $thumb['class'] = 'attachment-large size-large wp-post-image';
        $thumb['home_class'] = 'attachment-full size-full';

        $saved = [
            'id'          => $newId,
            'slug'        => $slug,
            'title'       => $title,
            'date'        => $prev['date'] ?? date('Y-m-d\TH:i:s'),
            'modified'    => date('Y-m-d\TH:i:s'),
            'categories'  => [],
            'excerpt'     => '',
            'description' => post_str('description'),
            'doc_title'   => $title . ' - ' . cfg('site_name'),
            'head_meta'   => $prev['head_meta'] ?? [],
            'schema'      => $prev['schema'] ?? '',
            'thumb'       => $thumb,
            'content'     => post_html('content'),
        ];

        $saved['head_meta'] = admin_sync_meta($saved['head_meta'], [
            'og:title' => $title,
            'og:image' => $thumb['url'],
        ]);

        store_save('itkinlr', admin_upsert($rows, $saved), 'İtkin düşmüş şəxslər / missing persons');
        admin_redirect(['section' => 'itkinlr', 'action' => 'edit', 'id' => $newId],
            $isNew ? 'Qeyd əlavə olundu.' : 'Dəyişikliklər yadda saxlanıldı.');
    }
}

if ($action === 'edit') {
    $isNew = $id === 0;
    $item  = $item ?? ['id' => 0, 'title' => '', 'slug' => '', 'description' => '',
                       'thumb' => admin_empty_thumb(), 'content' => ''];

    admin_shell_start('itkinlr', $isNew ? 'Yeni qeyd' : 'Qeydi redaktə et', $isNew ? [] : [
        ['href' => url('itkinlr/' . $item['slug']), 'label' => 'Saytda bax ↗'],
    ]);

    f_errors($errors);
    f_open(['section' => 'itkinlr', 'action' => 'edit', 'id' => $id]);
    ?>
	<div class="grid2">
		<div>
			<div class="card"><div class="card__body">
				<?php
                f_text('title', 'Ad, soyad, ata adı', (string) $item['title'], ['required' => true, 'data' => 'slug-source']);
                f_text('slug', 'Ünvan (slug)', (string) $item['slug'], ['data' => 'slug-target']);
                f_textarea('content', 'Əlavə məlumat', (string) $item['content'], [
                    'rows' => 8,
                    'hint' => 'Orijinal saytda bu səhifələr yalnız ad və şəkildən ibarətdir — boş buraxa bilərsiniz.',
                ]);
                f_textarea('description', 'Təsvir (meta description)', (string) $item['description'], ['rows' => 3]);
                ?>
			</div></div>
		</div>
		<div>
			<div class="card">
				<div class="card__head">Şəkil</div>
				<div class="card__body">
					<?php f_image('thumb', 'Foto', (string) ($item['thumb']['url'] ?? ''), [
                        'clearable' => true,
                        'hint' => 'Siyahıda və ana səhifədəki karuseldə görünür.',
                    ]); ?>
				</div>
			</div>
		</div>
	</div>

	<?php f_actions(admin_url(['section' => 'itkinlr']),
        $isNew ? null : admin_url(['section' => 'itkinlr', 'action' => 'delete', 'id' => $id])); ?>
    <?php
    f_close();
    admin_shell_end();
    return;
}

admin_shell_start('itkinlr', 'İtkinlər', [
    ['href' => admin_url(['section' => 'itkinlr', 'action' => 'edit']), 'label' => '+ Yeni qeyd', 'primary' => true],
]);
?>
<table class="table">
	<thead><tr><th style="width:70px"></th><th>Ad, soyad</th><th></th></tr></thead>
	<tbody>
<?php foreach ($rows as $row): ?>
		<tr>
			<td><?php if (!empty($row['thumb']['url'])): ?><img class="table__thumb" src="<?= asset($row['thumb']['url']) ?>" alt=""><?php endif; ?></td>
			<td>
				<a class="table__title" href="<?= e(admin_url(['section' => 'itkinlr', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['title']) ?></a>
				<div class="table__meta">/itkinlr/<?= e($row['slug']) ?>/</div>
			</td>
			<td class="is-right"><div class="table__actions">
				<a class="btn btn--sm" href="<?= e(url('itkinlr/' . $row['slug'])) ?>" target="_blank" rel="noopener">Bax</a>
				<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'itkinlr', 'action' => 'edit', 'id' => $row['id']])) ?>">Redaktə</a>
			</div></td>
		</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="3" class="empty">Hələ qeyd yoxdur.</td></tr><?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
