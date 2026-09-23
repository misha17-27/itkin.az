<?php
/** Xəbərlər bölməsi / news section */

$rows = data_load('posts');
$cats = data_load('categories');

$catOptions = [];
foreach ($cats as $c) {
    $catOptions[$c['id']] = $c['name'];
}

$id = (int) ($_GET['id'] ?? 0);

// Siyahı kateqoriyaya görə süzülə bilər: ?section=posts&cat=19
$catFilter = (int) ($_GET['cat'] ?? 0);
if (!isset($catOptions[$catFilter])) {
    $catFilter = 0;
}
$listParams = ['section' => 'posts'] + ($catFilter ? ['cat' => $catFilter] : []);

/* ---------------------------------------------------------------- silmək */

if ($action === 'delete' && $id > 0) {
    admin_require_delete('posts');
    $item = admin_find($rows, $id);
    if (!$item) {
        admin_redirect($listParams, 'Belə yazı tapılmadı — yəqin artıq silinib.', 'error');
    }
    store_save('posts', admin_delete($rows, $id), 'Xəbərlər və yazılar / posts');
    admin_redirect($listParams, '“' . ($item['title'] ?? '') . '” silindi.');
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
            'doc_title'   => admin_seo_title($title . ' - ' . cfg('site_name')),
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
                    'rich' => true,
                    'hint' => 'Formatı yuxarıdakı düymələrlə verin. “HTML” düyməsi kodu açır — cədvəl, video və şəkil kimi hissələri oradan dəqiq redaktə etmək olar.',
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
                    f_text('doc_title', 'SEO başlıq', (string) ($item['doc_title'] ?? ''), [
                        'hint' => 'Brauzerin başlığında və Google nəticələrində görünür. Boş buraxsanız addan avtomatik qurulur.',
                    ]);
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

// hər kateqoriyada neçə xəbər var — süzgəcdə göstərmək üçün
$total  = count($rows);
$counts = [];
foreach ($rows as $row) {
    foreach ((array) ($row['categories'] ?? []) as $cid) {
        $counts[(int) $cid] = ($counts[(int) $cid] ?? 0) + 1;
    }
}

if ($catFilter) {
    $rows = array_values(array_filter($rows, static function (array $row) use ($catFilter) {
        return in_array($catFilter, array_map('intval', (array) ($row['categories'] ?? [])), true);
    }));
}

admin_shell_start('posts', 'Xəbərlər', [
    ['href' => admin_url(['section' => 'posts', 'action' => 'edit']), 'label' => '+ Yeni xəbər', 'primary' => true],
]);
?>
<?php if ($catFilter): ?>
<div class="list-bar">
	<span><strong><?= count($rows) ?></strong> xəbər «<?= e($catOptions[$catFilter]) ?>» kateqoriyasında</span>
	<a href="<?= e(admin_url(['section' => 'posts'])) ?>">Filtri götür</a>
</div>
<?php endif; ?>
<table class="table">
	<thead>
		<tr>
			<th style="width:70px"></th>
			<th>Başlıq</th>
			<th style="width:200px">
				<form class="th-filter" method="get" action="<?= e(admin_url()) ?>">
					<input type="hidden" name="section" value="posts">
					<select name="cat" data-autosubmit aria-label="Kateqoriyaya görə süz"<?= $catFilter ? ' class="is-on"' : '' ?>>
						<option value="">Kateqoriya: hamısı (<?= $total ?>)</option>
<?php foreach ($catOptions as $cid => $name): ?>
						<option value="<?= (int) $cid ?>"<?= (int) $cid === $catFilter ? ' selected' : '' ?>><?= e($name) ?> (<?= $counts[(int) $cid] ?? 0 ?>)</option>
<?php endforeach; ?>
					</select>
					<noscript><button class="btn btn--sm" type="submit">Süz</button></noscript>
				</form>
			</th>
			<th style="width:120px">Tarix</th>
			<th></th>
		</tr>
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
					<form method="post" action="<?= e(admin_url(['section' => 'posts', 'action' => 'delete', 'id' => $row['id']] + ($catFilter ? ['cat' => $catFilter] : []))) ?>">
						<?= admin_token_field() ?>
						<button class="btn btn--sm btn--danger" type="submit"
						        data-confirm="&#8220;<?= e(mb_strimwidth((string) $row['title'], 0, 70, '…')) ?>&#8221; silinsin? Bunu geri qaytarmaq olmur.">Sil</button>
					</form>
				</div>
			</td>
		</tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
		<tr><td colspan="5" class="empty"><?= $catFilter ? 'Bu kateqoriyada xəbər yoxdur.' : 'Hələ xəbər yoxdur.' ?></td></tr>
<?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
