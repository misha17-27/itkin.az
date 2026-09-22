<?php
/** Xəbərlər bölməsi / news section */

$rows = data_load('posts');
$cats = data_load('categories');

$catOptions = [];
foreach ($cats as $c) {
    $catOptions[$c['id']] = $c['name'];
}

$id = (int) ($_GET['id'] ?? 0);

/* ---------------------------------------------------------------- silmək */

if ($action === 'delete' && $id > 0) {
    if (!admin_token_ok() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        admin_redirect(['section' => 'posts'], 'Forma köhnəlib.', 'error');
    }
    $item = admin_find($rows, $id);
    store_save('posts', admin_delete($rows, $id), 'Xəbərlər və yazılar / posts');
    admin_redirect(['section' => 'posts'], '“' . ($item['title'] ?? '') . '” silindi.');
}

/* ---------------------------------------------------------------- yazmaq */

$errors = [];
$item = $id > 0 ? admin_find($rows, $id) : null;

if ($id > 0 && !$item) {
    admin_redirect(['section' => 'posts'], 'Belə yazı tapılmadı.', 'error');
}

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }

    $title = post_str('title');
    if ($title === '') {
        $errors[] = 'Başlıq boş ola bilməz.';
    }

    $slug = post_str('slug');
    $slug = store_slug($slug !== '' ? $slug : $title);
    $slug = store_unique_slug($rows, $slug, $id > 0 ? $id : null);

    if (!$errors) {
        $isNew   = $id === 0;
        $newId   = $isNew ? store_next_id($rows) : $id;
        $prev    = $item ?? [];
        $date    = admin_datetime_store(post_str('date'), $prev['date'] ?? date('Y-m-d\TH:i:s'));
        $content = post_html('content');

        $saved = [
            'id'          => $newId,
            'slug'        => $slug,
            'title'       => $title,
            'date'        => $date,
            'modified'    => date('Y-m-d\TH:i:s'),
            'categories'  => post_ints('categories'),
            'excerpt'     => post_str('excerpt'),
            'description' => post_str('description') ?: admin_excerpt($content, 28),
            'doc_title'   => $title . ' - ' . cfg('site_name'),
            'head_meta'   => $prev['head_meta'] ?? [],
            'schema'      => $prev['schema'] ?? '',
            'thumb'       => admin_thumb_from_path(post_str('thumb'), $prev['thumb'] ?? []),
            'content'     => $content,
        ];

        // Paylaşma teqləri başlıq/şəkil dəyişəndə yenilənsin
        $saved['head_meta'] = admin_sync_meta($saved['head_meta'], [
            'og:title'       => $title,
            'og:description' => $saved['description'],
            'og:image'       => $saved['thumb']['url'],
        ]);

        store_save('posts', admin_upsert($rows, $saved), 'Xəbərlər və yazılar / posts');
        admin_redirect(['section' => 'posts', 'action' => 'edit', 'id' => $newId],
            $isNew ? 'Yeni xəbər əlavə olundu.' : 'Dəyişikliklər yadda saxlanıldı.');
    }

    // Xəta olsa formanı doldurulmuş halda göstəririk
    $item = array_merge($item ?? [], [
        'title' => $title, 'slug' => $slug, 'date' => post_str('date'),
        'excerpt' => post_str('excerpt'), 'description' => post_str('description'),
        'categories' => post_ints('categories'),
        'thumb' => admin_thumb_from_path(post_str('thumb'), $item['thumb'] ?? []),
        'content' => post_html('content'),
    ]);
}

/* ---------------------------------------------------------------- forma */

if ($action === 'edit') {
    $isNew = $id === 0;
    $item  = $item ?? [
        'id' => 0, 'title' => '', 'slug' => '', 'date' => date('Y-m-d\TH:i:s'),
        'categories' => [], 'excerpt' => '', 'description' => '',
        'thumb' => admin_empty_thumb(), 'content' => '',
    ];

    admin_shell_start('posts', $isNew ? 'Yeni xəbər' : 'Xəbəri redaktə et', $isNew ? [] : [
        ['href' => url($item['slug']), 'label' => 'Saytda bax ↗'],
    ]);

    f_errors($errors);
    f_open(['section' => 'posts', 'action' => 'edit', 'id' => $id]);
    ?>
	<div class="grid2">
		<div>
			<div class="card"><div class="card__body">
				<?php
                f_text('title', 'Başlıq', (string) $item['title'], ['required' => true, 'data' => 'slug-source']);
                f_text('slug', 'Ünvan (slug)', (string) $item['slug'], [
                    'hint' => 'Boş buraxsanız başlıqdan yaradılacaq. Nümunə: <code>yeni-xeber</code>',
                    'data' => 'slug-target',
                ]);
                f_textarea('content', 'Mətn', (string) $item['content'], [
                    'tall' => true,
                    'hint' => 'HTML işlədə bilərsiniz: &lt;p&gt;abzas&lt;/p&gt;, &lt;strong&gt;qalın&lt;/strong&gt;, &lt;img src="uploads/..."&gt;.',
                ]);
                ?>
			</div></div>
		</div>

		<div>
			<div class="card">
				<div class="card__head">Nəşr</div>
				<div class="card__body">
					<?php
                    f_text('date', 'Tarix', admin_datetime_value((string) $item['date']), ['type' => 'datetime-local']);
                    f_checks('categories', 'Kateqoriyalar', $catOptions, array_map('intval', $item['categories'] ?? []));
                    ?>
				</div>
			</div>

			<div class="card">
				<div class="card__head">Şəkil</div>
				<div class="card__body">
					<?php f_image('thumb', 'Əsas şəkil', (string) ($item['thumb']['url'] ?? ''), ['clearable' => true]); ?>
				</div>
			</div>

			<div class="card">
				<div class="card__head">Axtarış sistemləri</div>
				<div class="card__body">
					<?php
                    f_textarea('excerpt', 'Qısa mətn', (string) $item['excerpt'], ['rows' => 3]);
                    f_textarea('description', 'Təsvir (meta description)', (string) $item['description'], [
                        'rows' => 3,
                        'hint' => 'Boş buraxsanız mətnin əvvəlindən götürüləcək.',
                    ]);
                    ?>
				</div>
			</div>
		</div>
	</div>

	<?php f_actions(admin_url(['section' => 'posts']),
        $isNew ? null : admin_url(['section' => 'posts', 'action' => 'delete', 'id' => $id])); ?>
    <?php
    f_close();
    admin_shell_end();
    return;
}

/* ---------------------------------------------------------------- siyahı */

usort($rows, static function (array $a, array $b) {
    return strcmp($b['date'], $a['date']);
});

admin_shell_start('posts', 'Xəbərlər', [
    ['href' => admin_url(['section' => 'posts', 'action' => 'edit']), 'label' => '+ Yeni xəbər', 'primary' => true],
]);
?>
<table class="table">
	<thead>
		<tr><th style="width:70px"></th><th>Başlıq</th><th style="width:170px">Kateqoriya</th><th style="width:120px">Tarix</th><th></th></tr>
	</thead>
	<tbody>
<?php foreach ($rows as $row): ?>
		<tr>
			<td><?php if (!empty($row['thumb']['url'])): ?><img class="table__thumb" src="<?= asset($row['thumb']['url']) ?>" alt=""><?php endif; ?></td>
			<td>
				<a class="table__title" href="<?= e(admin_url(['section' => 'posts', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['title']) ?></a>
				<div class="table__meta">/<?= e($row['slug']) ?>/</div>
			</td>
			<td class="table__meta"><?php
                $names = [];
                foreach ($row['categories'] ?? [] as $cid) {
                    if (isset($catOptions[$cid])) { $names[] = $catOptions[$cid]; }
                }
                echo e(implode(', ', $names));
            ?></td>
			<td class="table__meta"><?= e(az_date($row['date'])) ?></td>
			<td class="is-right">
				<div class="table__actions">
					<a class="btn btn--sm" href="<?= e(url($row['slug'])) ?>" target="_blank" rel="noopener">Bax</a>
					<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'posts', 'action' => 'edit', 'id' => $row['id']])) ?>">Redaktə</a>
				</div>
			</td>
		</tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
		<tr><td colspan="5" class="empty">Hələ xəbər yoxdur.</td></tr>
<?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
