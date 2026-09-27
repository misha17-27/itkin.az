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
    admin_en_save('itkinlr', $id, null);   // ingiliscə tərcüməsi də silinir
    admin_redirect(['section' => 'itkinlr'], '“' . ($item['title'] ?? '') . '” silindi.');
}

$errors = [];
$item = $id > 0 ? admin_find($rows, $id) : null;
if ($id > 0 && !$item) {
    admin_redirect(['section' => 'itkinlr'], 'Belə qeyd tapılmadı.', 'error');
}

/* İngiliscə versiya (data/itkinlr-en.php): saxlanılmış tərcümə və formada göstəriləcək dəyərlər */
$enSaved = $item ? admin_en_get('itkinlr', $id) : [];
$en      = $enSaved;
$enForm  = false;

if ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    }

    $title = post_str('title');
    if ($title === '') {
        $errors[] = 'Ad, soyad boş ola bilməz.';
    }

    /*
     * İngiliscə sahələr. Kart formada yoxdursa (məsələn bu dəyişiklikdən əvvəl
     * açılmış köhnə səhifədən gələn sorğu) tərcüməyə toxunulmur.
     * Sıra fayldakı kimidir — dəyişiklik olmayanda fayl yenidən yazılmasın.
     */
    $enForm = is_array($_POST['en'] ?? null);
    if ($enForm) {
        $en = [
            'title'       => post_en('title'),
            'description' => post_en('description'),
            'content'     => post_en('content', true),
            'doc_title'   => post_en('doc_title'),
        ];
        if ($en['title'] === '' && implode('', $en) !== '') {
            $errors[] = 'İngiliscə versiya üçün ad, soyad yazın.';
        }
    }

    $slug = store_slug(post_str('slug') ?: $title);
    $slug = store_unique_slug($rows, $slug, $id > 0 ? $id : null);

    if (!$errors) {
        $isNew = $id === 0;
        $newId = $isNew ? store_next_id($rows) : $id;
        $prev  = $item ?? [];

        $thumb = admin_thumb_from_path(post_str('thumb'), $prev['thumb'] ?? []);
        // Arxivdə və ana səhifədə fərqli siniflər işlədilir. Şəkil dəyişməyibsə
        // orijinal siniflər (wp-image-731 kimi) saxlanılır — səhifə eyni qalsın.
        $sameImage = ($prev['thumb']['url'] ?? null) === $thumb['url'];
        if (!$sameImage || empty($prev['thumb']['class'])) {
            $thumb['class'] = 'attachment-large size-large wp-post-image';
        }
        if (!$sameImage || empty($prev['thumb']['home_class'])) {
            $thumb['home_class'] = 'attachment-full size-full';
        }

        $saved = [
            'id'          => $newId,
            'slug'        => $slug,
            'title'       => $title,
            'date'        => $prev['date'] ?? date('Y-m-d\TH:i:s'),
            'modified'    => date('Y-m-d\TH:i:s'),
            'categories'  => [],
            'excerpt'     => '',
            'description' => post_str('description'),
            'doc_title'   => admin_seo_title($title . ' - ' . cfg('site_name')),
            'head_meta'   => $prev['head_meta'] ?? [],
            'schema'      => $prev['schema'] ?? '',
            'thumb'       => $thumb,
            'content'     => post_html('content'),
        ];

        $saved['head_meta'] = admin_sync_meta($saved['head_meta'], [
            'og:title'       => $title,
            'og:description' => $saved['description'],
            'og:image'       => $thumb['url'],
        ]);

        store_save('itkinlr', admin_upsert($rows, $saved), 'İtkin düşmüş şəxslər / missing persons');

        $flash = $isNew ? 'Qeyd əlavə olundu.' : 'Dəyişikliklər yadda saxlanıldı.';
        if ($enForm) {
            // avtomatik başlığın özü saxlanılmır — ad dəyişəndə o da yenilənsin
            if ($en['doc_title'] === $en['title'] . ' - ' . cfg('site_name')) {
                $en['doc_title'] = '';
            }
            admin_en_save('itkinlr', $newId, $en);
            $enNow = admin_en_get('itkinlr', $newId);
            if ($enSaved && !$enNow) {
                $flash .= ' İngiliscə versiya silindi.';
            } elseif (!$enSaved && $enNow && !$isNew) {
                $flash .= ' İngiliscə versiya əlavə olundu.';
            }
        }

        admin_redirect(['section' => 'itkinlr', 'action' => 'edit', 'id' => $newId], $flash);
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
                    'rich' => true,
                    'hint' => 'Orijinal saytda bu səhifələr yalnız ad və şəkildən ibarətdir — boş buraxa bilərsiniz.',
                ]);
                f_text('doc_title', 'SEO başlıq', (string) ($item['doc_title'] ?? ''), [
                    'hint' => 'Brauzerin başlığında və Google nəticələrində görünür. Boş buraxsanız addan avtomatik qurulur.',
                ]);
                f_textarea('description', 'Təsvir (meta description)', (string) $item['description'], ['rows' => 3]);
                ?>
			</div></div>

			<?php
            f_en_open($isNew || !$enSaved ? '' : url_lang('itkinlr/' . $item['slug'], 'en'),
                'Şəxsin ingiliscə səhifəsi (<code>/en/itkinlr/…/</code>) ad yazılanda yaranır. '
                . 'Boş buraxılan sahənin yerində azərbaycanca mətn çıxır. Bütün sahələri boşaltsanız ingiliscə versiya silinir — '
                . 'ingiliscə ünvan azərbaycanca səhifəyə yönləndirilir.');
            f_text('en[title]', 'Ad, soyad, ata adı', (string) ($en['title'] ?? ''), [
                'hint' => 'Latın hərfləri ilə, ingiliscə oxunuşda. Nümunə: <code>Zeynalov Chingiz Atash oglu</code>',
            ]);
            f_textarea('en[content]', 'Əlavə məlumat', (string) ($en['content'] ?? ''), ['rows' => 8, 'rich' => true]);
            f_text('en[doc_title]', 'SEO başlıq', (string) ($en['doc_title'] ?? ''), [
                'hint' => 'Boş buraxsanız ingiliscə addan avtomatik qurulur.',
            ]);
            f_textarea('en[description]', 'Təsvir (meta description)', (string) ($en['description'] ?? ''), [
                'rows' => 3,
                'hint' => 'Nümunə: <code>… — person who went missing in the First Karabakh War.</code>',
            ]);
            f_en_close();
            ?>
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
$enRows = data_load('itkinlr-en');
?>
<table class="table">
	<thead><tr><th style="width:70px"></th><th>Ad, soyad</th><th style="width:60px">EN</th><th></th></tr></thead>
	<tbody>
<?php foreach ($rows as $row): ?>
<?php $enRow = (array) ($enRows[$row['id']] ?? []); ?>
		<tr>
			<td><?php if (!empty($row['thumb']['url'])): ?><img class="table__thumb" src="<?= asset($row['thumb']['url']) ?>" alt=""><?php endif; ?></td>
			<td>
				<a class="table__title" href="<?= e(admin_url(['section' => 'itkinlr', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['title']) ?></a>
				<div class="table__meta">/itkinlr/<?= e($row['slug']) ?>/</div>
			</td>
			<td><?php if ($enRow): ?><span class="lang-flag" title="<?= e('İngiliscə: ' . ($enRow['title'] ?? '')) ?>">EN</span><?php else: ?><span class="lang-flag lang-flag--off" title="İngiliscə versiya yoxdur">EN</span><?php endif; ?></td>
			<td class="is-right"><div class="table__actions">
				<a class="btn btn--sm" href="<?= e(url('itkinlr/' . $row['slug'])) ?>" target="_blank" rel="noopener">Bax</a>
				<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'itkinlr', 'action' => 'edit', 'id' => $row['id']])) ?>">Redaktə</a>
			</div></td>
		</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="4" class="empty">Hələ qeyd yoxdur.</td></tr><?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
