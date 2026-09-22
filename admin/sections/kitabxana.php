<?php
/** Kitabxana bölməsi / library section */

$rows = data_load('kitabxana');
$id   = (int) ($_GET['id'] ?? 0);

/* düymələrin id-ləri Elementor şablonundan gəlir: AZ / EN / RU */
const BOOK_LANGS = ['7f9aa47' => 'AZ', '43181c2' => 'EN', '044b0d1' => 'RU'];

if ($action === 'delete' && $id > 0) {
    admin_require_delete('kitabxana');
    $item = admin_find($rows, $id);
    if (!$item) {
        admin_redirect(['section' => 'kitabxana'], 'Belə kitab tapılmadı — yəqin artıq silinib.', 'error');
    }
    store_save('kitabxana', admin_delete($rows, $id), 'Kitabxana / library books');
    admin_redirect(['section' => 'kitabxana'], '“' . ($item['title'] ?? '') . '” silindi.');
}

$errors = [];
$item = $id > 0 ? admin_find($rows, $id) : null;
if ($id > 0 && !$item) {
    admin_redirect(['section' => 'kitabxana'], 'Belə kitab tapılmadı.', 'error');
}

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }

    $title = post_str('title');
    if ($title === '') {
        $errors[] = 'Kitabın adı boş ola bilməz.';
    }

    $slug = store_slug(post_str('slug') ?: $title);
    $slug = store_unique_slug($rows, $slug, $id > 0 ? $id : null);

    if (!$errors) {
        $isNew = $id === 0;
        $newId = $isNew ? store_next_id($rows) : $id;
        $prev  = $item ?? [];

        $downloads = [];
        foreach (BOOK_LANGS as $key => $lang) {
            $downloads[$key] = trim(post_str('dl_' . $key));
        }

        $content = post_html('content');
        $cover   = admin_thumb_from_path(post_str('thumb'), $prev['thumb'] ?? []);

        $saved = [
            'id'          => $newId,
            'slug'        => $slug,
            'title'       => $title,
            'date'        => $prev['date'] ?? date('Y-m-d\TH:i:s'),
            'modified'    => date('Y-m-d\TH:i:s'),
            'categories'  => [],
            'excerpt'     => post_str('excerpt'),
            'description' => post_str('description') ?: admin_excerpt($content, 28),
            'doc_title'   => $title . ' - ' . cfg('site_name'),
            'head_meta'   => $prev['head_meta'] ?? [],
            'schema'      => $prev['schema'] ?? '',
            'thumb'       => $cover,
            'card_thumb'  => admin_thumb_from_path(post_str('card_thumb') ?: $cover['url'], $prev['card_thumb'] ?? []),
            'downloads'   => $downloads,
            'content'     => $content,
        ];

        $saved['head_meta'] = admin_sync_meta($saved['head_meta'], [
            'og:title'       => $title,
            'og:description' => $saved['description'],
            'og:image'       => $cover['url'],
        ]);

        store_save('kitabxana', admin_upsert($rows, $saved), 'Kitabxana / library books');
        admin_redirect(['section' => 'kitabxana', 'action' => 'edit', 'id' => $newId],
            $isNew ? 'Kitab əlavə olundu.' : 'Dəyişikliklər yadda saxlanıldı.');
    }
}

if ($action === 'edit') {
    $isNew = $id === 0;
    $item  = $item ?? [
        'id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'description' => '',
        'thumb' => admin_empty_thumb(), 'card_thumb' => admin_empty_thumb(),
        'downloads' => array_fill_keys(array_keys(BOOK_LANGS), ''), 'content' => '',
    ];

    admin_shell_start('kitabxana', $isNew ? 'Yeni kitab' : 'Kitabı redaktə et', $isNew ? [] : [
        ['href' => url('kitabxana-blog/' . $item['slug']), 'label' => 'Saytda bax ↗'],
    ]);

    f_errors($errors);
    f_open(['section' => 'kitabxana', 'action' => 'edit', 'id' => $id]);
    ?>
	<div class="grid2">
		<div>
			<div class="card"><div class="card__body">
				<?php
                f_text('title', 'Kitabın adı', (string) $item['title'], ['required' => true, 'data' => 'slug-source']);
                f_text('slug', 'Ünvan (slug)', (string) $item['slug'], ['data' => 'slug-target']);
                f_text('excerpt', 'Müəllif(lər)', (string) $item['excerpt'], [
                    'hint' => 'Kitab kartının altında görünür. Nümunə: <code>Eldar Səmədov, Emin Vəliyev</code>',
                ]);
                f_textarea('content', 'Təsvir', (string) $item['content'], ['tall' => true]);
                ?>
			</div></div>

			<div class="card">
				<div class="card__head">Yükləmə keçidləri</div>
				<div class="card__body">
					<p class="field__hint" style="margin-top:0">Hər dil üçün ayrıca düymə. Boş buraxsanız düymə keçidsiz görünür — orijinalda da belədir.</p>
					<?php foreach (BOOK_LANGS as $key => $lang): ?>
					<?php f_text('dl_' . $key, $lang . ' — PDF ünvanı', (string) ($item['downloads'][$key] ?? ''), [
                        'placeholder' => 'uploads/2024/08/kitab.pdf  və ya  https://...',
                    ]); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div>
			<div class="card">
				<div class="card__head">Üz qabığı</div>
				<div class="card__body">
					<?php
                    f_image('thumb', 'Tam ölçü', (string) ($item['thumb']['url'] ?? ''), ['clearable' => true]);
                    f_image('card_thumb', 'Siyahı üçün', (string) ($item['card_thumb']['url'] ?? ''), [
                        'clearable' => true,
                        'hint' => 'Boş buraxsanız tam ölçülü şəkil işlədiləcək.',
                    ]);
                    ?>
				</div>
			</div>

			<div class="card">
				<div class="card__head">Axtarış sistemləri</div>
				<div class="card__body">
					<?php f_textarea('description', 'Təsvir (meta description)', (string) $item['description'], ['rows' => 4]); ?>
				</div>
			</div>
		</div>
	</div>

	<?php f_actions(admin_url(['section' => 'kitabxana']),
        $isNew ? null : admin_url(['section' => 'kitabxana', 'action' => 'delete', 'id' => $id])); ?>
    <?php
    f_close();
    admin_shell_end();
    return;
}

admin_shell_start('kitabxana', 'Kitabxana', [
    ['href' => admin_url(['section' => 'kitabxana', 'action' => 'edit']), 'label' => '+ Yeni kitab', 'primary' => true],
]);
?>
<table class="table">
	<thead><tr><th style="width:70px"></th><th>Ad</th><th style="width:230px">Müəllif</th><th style="width:110px">PDF</th><th></th></tr></thead>
	<tbody>
<?php foreach ($rows as $row): ?>
		<tr>
			<td><?php if (!empty($row['card_thumb']['url'])): ?><img class="table__thumb" src="<?= asset($row['card_thumb']['url']) ?>" alt=""><?php endif; ?></td>
			<td>
				<a class="table__title" href="<?= e(admin_url(['section' => 'kitabxana', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['title']) ?></a>
				<div class="table__meta">/kitabxana-blog/<?= e($row['slug']) ?>/</div>
			</td>
			<td class="table__meta"><?= e($row['excerpt']) ?></td>
			<td class="table__meta"><?= count(array_filter($row['downloads'] ?? [])) ?> fayl</td>
			<td class="is-right"><div class="table__actions">
				<a class="btn btn--sm" href="<?= e(url('kitabxana-blog/' . $row['slug'])) ?>" target="_blank" rel="noopener">Bax</a>
				<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'kitabxana', 'action' => 'edit', 'id' => $row['id']])) ?>">Redaktə</a>
			</div></td>
		</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty">Hələ kitab yoxdur.</td></tr><?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
