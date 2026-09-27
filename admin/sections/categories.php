<?php
/**
 * Kateqoriyalar bölməsi
 *
 * İngiliscə ad və təsvir data/categories-en.php-də saxlanılır. Kateqoriyanın
 * ingiliscə səhifəsi yalnız içində ingiliscə versiyası olan xəbər olduqda açılır.
 */

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
    // zibil qutusundakı xəbərlər də bu kateqoriyaya bağlıdır — bərpa olunanda yerləri qalmalıdır
    $trashed = trash_posts_in_category($id);
    if ($trashed > 0) {
        admin_redirect(['section' => 'categories'],
            'Silinmədi: zibil qutusunda bu kateqoriyanın ' . $trashed . ' xəbəri var. '
            . 'Onları bərpa edib başqa kateqoriyaya keçirin və ya zibil qutusundan birdəfəlik silin.', 'error');
    }
    // birdəfəlik silinmir: ingiliscə tərcüməsi ilə birlikdə zibil qutusuna düşür (30 gün)
    $item = trash_move_record('categories', $id);
    if (!$item) {
        admin_redirect(['section' => 'categories'], 'Belə kateqoriya tapılmadı — yəqin artıq silinib.', 'error');
    }
    admin_redirect(['section' => 'categories'], trash_flash((string) $item['title']));
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

    // Formada ingiliscə kart yoxdursa (köhnə səhifədən gələn sorğu) tərcüməyə toxunulmur
    $en = is_array($_POST['en'] ?? null) ? ['name' => post_en('name'), 'description' => post_en('description')] : null;

    if (!$errors) {
        $isNew = $id === 0;
        $newId = $isNew ? admin_next_id('categories', $rows) : $id;
        $prev  = $item ?? [];
        $saved = [
            'id'          => $newId,
            'slug'        => $slug,
            'name'        => $name,
            'description' => post_str('description'),
            'count'       => (int) ($prev['count'] ?? 0),
            'doc_title'   => admin_seo_title($name . ' Archives - ' . cfg('site_name')),
            'head_meta'   => admin_sync_meta($prev['head_meta'] ?? [], [
                'og:title'       => $name . ' Archives',
                'og:description' => post_str('description'),
            ]),
            'schema'      => $prev['schema'] ?? '',
        ];
        store_save('categories', admin_upsert($rows, $saved), 'Kateqoriyalar / categories');

        $flash = $isNew ? 'Kateqoriya əlavə olundu.' : 'Yadda saxlanıldı.';
        if ($en !== null) {
            // Brauzer başlığı ingiliscə addan qurulur — azərbaycancadakı kimi “… Archives - sayt”
            if ($en['name'] !== '') {
                $en['doc_title'] = $en['name'] . ' Archives - ' . cfg('site_name');
            }
            $enHad = $isNew ? [] : admin_en_get('categories', $newId);
            admin_en_save('categories', $newId, $en);
            $enNow = admin_en_get('categories', $newId);
            if ($enHad && !$enNow) {
                $flash .= ' İngiliscə versiya silindi.';
            } elseif (!$enHad && $enNow && !$isNew) {
                $flash .= ' İngiliscə versiya əlavə olundu.';
            }
        }

        admin_redirect(['section' => 'categories'], $flash);
    }
}

if ($action === 'edit') {
    $isNew = $id === 0;
    $item  = $item ?? ['id' => 0, 'name' => '', 'slug' => '', 'description' => ''];

    // İngiliscə versiya: saxlanılmış tərcümə; xəta olubsa formada yazılan
    $enSaved = $isNew ? [] : admin_en_get('categories', $id);
    $enForm  = isset($en) ? $en : $enSaved + ['name' => '', 'description' => ''];
    $enPath  = 'category/' . (string) (admin_find($rows, $id)['slug'] ?? '');
    $enLive  = $enSaved !== [] && lang_has($enPath, 'en');

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
        f_text('doc_title', 'SEO başlıq', (string) ($item['doc_title'] ?? ''), [
            'hint' => 'Brauzerin başlığında və Google nəticələrində görünür. Boş buraxsanız addan avtomatik qurulur.',
        ]);
        f_textarea('description', 'Təsvir (meta description)', (string) $item['description'], [
            'rows' => 3,
            'hint' => 'Kateqoriya səhifəsinin axtarış nəticələrindəki izahı.',
        ]);
        ?>
	</div></div>
	<?php
    f_en_open($enLive ? url_lang($enPath, 'en') : '',
        'Saytın <code>/en/</code> versiyasında göstərilir. Boş buraxılan sahənin yerində azərbaycanca mətn çıxır; '
        . 'hər iki sahəni boşaltsanız, tərcümə silinir. Brauzer başlığı ingiliscə addan avtomatik qurulur '
        . '(<code>… Archives - ' . e((string) cfg('site_name')) . '</code>).<br>'
        . 'Kateqoriyanın ingiliscə səhifəsi yalnız içində ingiliscə versiyası olan ən azı bir xəbər olduqda açılır; '
        . 'əks halda ziyarətçi azərbaycanca səhifəyə yönləndirilir.');
    ?>
		<div class="narrow">
<?php if ($enSaved !== [] && !$enLive): ?>
			<p class="field__hint" style="margin-top:0"><span class="lang-flag lang-flag--off">EN</span>
				Hazırda bu kateqoriyada ingiliscə versiyası olan xəbər yoxdur — ingiliscə səhifə açılmır.</p>
<?php endif; ?>
			<?php
            f_text('en[name]', 'Ad (ingiliscə)', (string) $enForm['name']);
            f_textarea('en[description]', 'Təsvir (meta description, ingiliscə)', (string) $enForm['description'], ['rows' => 3]);
            ?>
		</div>
	<?php f_en_close(); ?>
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

$enRows = data_load('categories-en');

admin_shell_start('categories', 'Kateqoriyalar', [
    ['href' => admin_url(['section' => 'categories', 'action' => 'edit']), 'label' => '+ Yeni kateqoriya', 'primary' => true],
]);
?>
<table class="table">
	<thead><tr><th>Ad</th><th style="width:220px">Ünvan</th><th style="width:100px">Yazı</th><th style="width:44px" title="İngiliscə versiya">EN</th><th></th></tr></thead>
	<tbody>
<?php foreach ($rows as $row): ?>
		<tr>
			<td><a class="table__title" href="<?= e(admin_url(['section' => 'categories', 'action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row['name']) ?></a></td>
			<td class="table__meta">/category/<?= e($row['slug']) ?>/</td>
			<td class="table__meta"><?= (int) ($counts[$row['id']] ?? 0) ?></td>
			<td><?php
            // tərcümə var: səhifə açılırsa göy nişan, içində ingiliscə xəbər yoxdursa boz
            if (isset($enRows[$row['id']])) {
                echo lang_has('category/' . $row['slug'], 'en')
                    ? '<span class="lang-flag" title="İngiliscə versiyası var">EN</span>'
                    : '<span class="lang-flag lang-flag--off" title="Tərcüməsi var, amma içində ingiliscə versiyası olan xəbər yoxdur — ingiliscə səhifə açılmır">EN</span>';
            }
            ?></td>
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
