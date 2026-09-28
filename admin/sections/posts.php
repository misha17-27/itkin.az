<?php
/**
 * Xəbərlər bölməsi / news section
 *
 * Formada iki nişan var: «AZ Azərbaycanca» və «EN English». İngiliscə başlıq
 * yazılmış xəbər (data/posts-en.php) saytın /en/ versiyasına düşür və onda dil
 * seçimi görünür; tərcüməsi olmayan xəbər yalnız azərbaycancadır.
 */

if (!function_exists('posts_en_html')) {
    /**
     * Vizual redaktorun “boş” məzmunu (<p><br></p>, &nbsp; və s.) boş sayılır —
     * yoxsa ingiliscə səhifədə azərbaycanca mətnin yerinə boş sahə çıxardı.
     */
    function posts_en_html(string $html): string
    {
        $text = strip_tags($html, '<img><video><audio><source><iframe><table><hr>');
        $text = str_replace("\xC2\xA0", ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return trim($text) === '' ? '' : $html;
    }
}

if (!function_exists('posts_en_post')) {
    /** Formadakı ingiliscə sahələr: en[title], en[excerpt], en[description], en[content] */
    function posts_en_post(): array
    {
        return [
            'title'       => post_en('title'),
            'excerpt'     => post_en('excerpt'),
            'description' => post_en('description'),
            'content'     => posts_en_html(post_en('content', true)),
        ];
    }
}

$rows = data_load('posts');
$cats = data_load('categories');

$catOptions = [];
foreach ($cats as $c) {
    $catOptions[$c['id']] = $c['name'];
}

$id = (int) ($_GET['id'] ?? 0);

// Siyahı kateqoriyaya və əlavə edənə görə süzülə bilər: ?section=posts&cat=19&by=u3
$catFilter = (int) ($_GET['cat'] ?? 0);
if (!isset($catOptions[$catFilter])) {
    $catFilter = 0;
}
$byFilter   = admin_author_filter_key($_GET['by'] ?? '');
$listFilter = ($catFilter ? ['cat' => $catFilter] : []) + ($byFilter !== '' ? ['by' => $byFilter] : []);
$listParams = ['section' => 'posts'] + $listFilter;

/* ---------------------------------------------------------------- silmək */

// Xəbər birdəfəlik silinmir: ingiliscə versiyası ilə birlikdə zibil qutusuna düşür (30 gün)
if ($action === 'delete' && $id > 0) {
    admin_require_delete('posts');
    $item = trash_move_record('posts', $id);
    if (!$item) {
        admin_redirect($listParams, 'Belə yazı tapılmadı — yəqin artıq silinib.', 'error');
    }
    admin_redirect($listParams, trash_flash((string) $item['title']));
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

    /*
     * İngiliscə versiya istəyə bağlıdır, amma yazılıbsa başlığı olmalıdır.
     * Formada ingiliscə kart yoxdursa (məsələn bu dəyişiklikdən əvvəl açılmış
     * köhnə səhifədən gələn sorğu) tərcüməyə toxunulmur.
     */
    $en = is_array($_POST['en'] ?? null) ? posts_en_post() : null;
    if ($en !== null && $en['title'] === '' && implode('', $en) !== '') {
        $errors[] = 'İngiliscə versiya üçün başlıq yazın.';
    }

    if (!$errors) {
        $isNew   = $id === 0;
        $newId   = $isNew ? admin_next_id('posts', $rows) : $id;
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

        // kim əlavə edib, kim son dəyişib (admin/inc/authors.php)
        $saved   = admin_author_touch($saved, $isNew ? null : $prev);
        $touched = admin_author_touched($saved, $isNew ? null : $prev);

        store_save('posts', admin_upsert($rows, $saved), 'Xəbərlər və yazılar / posts');

        $flash = $isNew ? 'Yeni xəbər əlavə olundu.' : 'Dəyişikliklər yadda saxlanıldı.';
        if ($en !== null) {
            // Təsvir boşdursa — azərbaycanca kimi, ingiliscə mətnin əvvəlindən
            if ($en['description'] === '' && $en['content'] !== '') {
                $en['description'] = admin_excerpt($en['content'], 28);
            }
            $enHad = $isNew ? [] : admin_en_get('posts', $newId);
            admin_en_save('posts', $newId, $en);
            $enNow = admin_en_get('posts', $newId);
            if ($enHad && !$enNow) {
                $flash .= ' İngiliscə versiya silindi.';
            } elseif (!$enHad && $enNow && !$isNew) {
                $flash .= ' İngiliscə versiya əlavə olundu.';
            }
            // yalnız ingiliscə mətn dəyişibsə də «son dəyişiklik» yenilənsin
            if (!$touched && $enHad !== $enNow) {
                admin_author_mark('posts', $newId, 'Xəbərlər və yazılar / posts');
            }
        }

        admin_redirect(['section' => 'posts', 'action' => 'edit', 'id' => $newId]
            + ((string) ($_POST['tab'] ?? '') === 'en' ? ['tab' => 'en'] : []), $flash);
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

    // İngiliscə versiya: saxlanılmış tərcümə; xəta olubsa formada yazılan
    $enSaved = $isNew ? [] : admin_en_get('posts', $id);
    $enForm  = isset($en) ? $en : $enSaved + ['title' => '', 'excerpt' => '', 'description' => '', 'content' => ''];
    $enView  = $enSaved !== [] ? url_lang((string) (admin_find($rows, $id)['slug'] ?? ''), 'en') : '';

    admin_shell_start('posts', $isNew ? 'Yeni xəbər' : 'Xəbəri redaktə et', $isNew ? [] : [
        ['href' => url($item['slug']), 'label' => 'Saytda bax ↗'],
    ]);

    f_errors($errors);
    f_open(['section' => 'posts', 'action' => 'edit', 'id' => $id]);

    // Açıq nişan: ingiliscə sahədə xəta varsa, siyahıdakı «+ EN»-dən gəlinibsə və ya
    // ingiliscə nişanda yadda saxlanılıbsa — ingiliscə
    $enTab = (isset($en) && $en !== null && $en['title'] === '' && implode('', $en) !== '')
        || (string) ($_GET['tab'] ?? '') === 'en'
        || (string) ($_POST['tab'] ?? '') === 'en';
    ?>
	<input type="hidden" name="tab" value="<?= $enTab ? 'en' : 'az' ?>" data-lang-tab-field>
	<div class="lang-tabs" data-lang-tabs data-active="<?= $enTab ? 'en' : 'az' ?>" role="tablist">
		<button class="lang-tabs__btn" type="button" role="tab" data-lang-tab="az">
			<span class="lang-flag lang-flag--az">AZ</span> Azərbaycanca
		</button>
		<button class="lang-tabs__btn" type="button" role="tab" data-lang-tab="en">
			<span class="lang-flag">EN</span> English
			<span class="lang-tabs__state<?= $enSaved ? ' is-on' : '' ?>"><?= $enSaved ? 'tərcümə var' : 'tərcümə yoxdur' ?></span>
		</button>
<?php if (!$isNew): ?>
		<span class="lang-tabs__links">
			<a class="btn btn--sm" href="<?= e(url((string) (admin_find($rows, $id)['slug'] ?? $item['slug']))) ?>" target="_blank" rel="noopener">Saytda aç ↗</a>
<?php   if ($enView !== ''): ?>
			<a class="btn btn--sm" href="<?= e($enView) ?>" target="_blank" rel="noopener">EN ↗</a>
<?php   endif; ?>
		</span>
<?php endif; ?>
	</div>

	<div class="grid2">
		<div>
			<div class="card" data-lang-pane="az"><div class="card__body">
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

			<div class="card card--en" data-lang-pane="en" id="en">
				<div class="card__head"><span class="lang-flag">EN</span> İngiliscə versiya</div>
				<div class="card__body">
					<p class="field__hint" style="margin-top:0">
						İngiliscə başlığı yazıb yadda saxlayan kimi xəbər saytın <code>/en/</code> versiyasında görünür
						və xəbərin səhifəsində dil seçimi (AZ / EN) çıxır. Tərcüməsi olmayan xəbər yalnız azərbaycancadır.
						Bütün ingiliscə sahələri boşaltsanız, ingiliscə versiya silinir.
					</p>
					<?php
                    f_text('en[title]', 'Başlıq (ingiliscə)', (string) $enForm['title'], [
                        'hint' => 'İngiliscə versiya üçün məcburidir.',
                    ]);
                    f_textarea('en[content]', 'Mətn (ingiliscə)', (string) $enForm['content'], [
                        'tall' => true,
                        'rich' => true,
                        'hint' => 'Boş buraxsanız ingiliscə səhifədə azərbaycanca mətn göstərilir.',
                    ]);
                    ?>
				</div>
			</div>
		</div>

		<div>
			<div class="card">
				<div class="card__head">Nəşr</div>
				<div class="card__body">
					<?php
                    f_text('date', 'Tarix', admin_datetime_value((string) $item['date']), ['type' => 'datetime-local']);
                    f_checks('categories', 'Kateqoriyalar', $catOptions, array_map('intval', $item['categories'] ?? []));
                    ?>
					<p class="field__hint" style="margin:0">Tarix, kateqoriya və şəkil hər iki dil üçün eynidir.</p>
				</div>
			</div>

<?php if (!$isNew): ?>
			<?= admin_author_card($item, 'posts') ?>
<?php endif; ?>

			<div class="card">
				<div class="card__head">Şəkil</div>
				<div class="card__body">
					<?php f_image('thumb', 'Əsas şəkil', (string) ($item['thumb']['url'] ?? ''), ['clearable' => true]); ?>
				</div>
			</div>

			<div class="card">
				<div class="card__head">Axtarış sistemləri</div>
				<div class="card__body">
					<div data-lang-pane="az">
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
					<div data-lang-pane="en">
					<?php
                    f_textarea('en[excerpt]', 'Qısa mətn (ingiliscə)', (string) $enForm['excerpt'], ['rows' => 3]);
                    f_textarea('en[description]', 'Təsvir (meta description, ingiliscə)', (string) $enForm['description'], [
                        'rows' => 3,
                        'hint' => 'Boş buraxsanız ingiliscə mətnin əvvəlindən götürüləcək.',
                    ]);
                    ?>
					</div>
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

// ingiliscə versiyası olan xəbərlər (çoxu yoxdur — nişan yalnız onlarda)
$enRows = data_load('posts-en');

// əlavə edənlər — süzgəcdə göstərmək üçün (hamısı üzrə say)
$byOptions = admin_author_options($rows, 'posts');
if ($byFilter !== '' && !isset($byOptions[$byFilter])) {
    // köhnə keçid: bu müəllifin xəbəri qalmayıb — adı yenə göstərilsin
    $byView = admin_author_key_view($byFilter);
    $byOptions[$byFilter] = [admin_author_option_label($byView), 0, admin_author_plain($byView)];
}

if ($catFilter) {
    $rows = array_values(array_filter($rows, static function (array $row) use ($catFilter) {
        return in_array($catFilter, array_map('intval', (array) ($row['categories'] ?? [])), true);
    }));
}
if ($byFilter !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($byFilter) {
        return admin_row_author($row, 'posts')['key'] === $byFilter;
    }));
}

admin_shell_start('posts', 'Xəbərlər', [
    ['href' => admin_url(['section' => 'posts', 'action' => 'edit']), 'label' => '+ Yeni xəbər', 'primary' => true],
]);
?>
<?php if ($listFilter): ?>
<div class="list-bar">
	<span><strong><?= count($rows) ?></strong> xəbər<?php
        if ($catFilter) {
            echo ' «' . e($catOptions[$catFilter]) . '» kateqoriyasında';
        }
        if ($byFilter !== '') {
            echo ' · əlavə edib: <strong>' . e($byOptions[$byFilter][2]) . '</strong>';
        }
    ?></span>
	<a href="<?= e(admin_url(['section' => 'posts'])) ?>">Filtri götür</a>
</div>
<?php endif; ?>
<table class="table">
	<thead>
		<tr>
			<th style="width:70px"></th>
			<th>Başlıq</th>
			<th style="width:230px">
				<form class="th-filter" method="get" action="<?= e(admin_url()) ?>">
					<input type="hidden" name="section" value="posts">
<?php if ($byFilter !== ''): ?>
					<input type="hidden" name="by" value="<?= e($byFilter) ?>">
<?php endif; ?>
					<select name="cat" data-autosubmit aria-label="Kateqoriyaya görə süz"<?= $catFilter ? ' class="is-on"' : '' ?>>
						<option value="">Kateqoriya: hamısı (<?= $total ?>)</option>
<?php foreach ($catOptions as $cid => $name): ?>
						<option value="<?= (int) $cid ?>"<?= (int) $cid === $catFilter ? ' selected' : '' ?>><?= e($name) ?> (<?= $counts[(int) $cid] ?? 0 ?>)</option>
<?php endforeach; ?>
					</select>
					<noscript><button class="btn btn--sm" type="submit">Süz</button></noscript>
				</form>
			</th>
			<th style="width:190px">
				<form class="th-filter" method="get" action="<?= e(admin_url()) ?>">
					<input type="hidden" name="section" value="posts">
<?php if ($catFilter): ?>
					<input type="hidden" name="cat" value="<?= (int) $catFilter ?>">
<?php endif; ?>
					<select name="by" data-autosubmit aria-label="Əlavə edənə görə süz"<?= $byFilter !== '' ? ' class="is-on"' : '' ?>>
						<option value="">Əlavə edib: hamısı</option>
<?php foreach ($byOptions as $key => [$label, $n]): ?>
						<option value="<?= e($key) ?>"<?= $key === $byFilter ? ' selected' : '' ?>><?= e($label) ?> (<?= (int) $n ?>)</option>
<?php endforeach; ?>
					</select>
					<noscript><button class="btn btn--sm" type="submit">Süz</button></noscript>
				</form>
			</th>
			<th style="width:120px">Tarix</th>
			<th style="width:60px" title="İngiliscə versiya">EN</th>
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
			<td class="table__meta"><?= admin_author_cell($row, 'posts') ?></td>
			<td class="table__meta"><?= e(az_date($row['date'])) ?></td>
			<td>
<?php if (isset($enRows[$row['id']])): ?>
				<a class="lang-flag" href="<?= e(admin_url(['section' => 'posts', 'action' => 'edit', 'id' => $row['id'], 'tab' => 'en'])) ?>" title="İngiliscə versiyası var — redaktə et">EN</a>
<?php else: ?>
				<a class="lang-add" href="<?= e(admin_url(['section' => 'posts', 'action' => 'edit', 'id' => $row['id'], 'tab' => 'en'])) ?>" title="İngiliscə tərcümə əlavə et">+ EN</a>
<?php endif; ?>
			</td>
			<td class="is-right">
				<div class="table__actions">
					<a class="btn btn--sm" href="<?= e(url($row['slug'])) ?>" target="_blank" rel="noopener">Bax</a>
					<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'posts', 'action' => 'edit', 'id' => $row['id']])) ?>">Redaktə</a>
					<form method="post" action="<?= e(admin_url(['section' => 'posts', 'action' => 'delete', 'id' => $row['id']] + $listFilter)) ?>">
						<?= admin_token_field() ?>
						<button class="btn btn--sm btn--danger" type="submit"
						        data-confirm="&#8220;<?= e(mb_strimwidth((string) $row['title'], 0, 70, '…')) ?>&#8221; zibil qutusuna köçürülsün? <?= TRASH_DAYS ?> gün ərzində bərpa etmək olar.">Sil</button>
					</form>
				</div>
			</td>
		</tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
		<tr><td colspan="7" class="empty"><?= $listFilter ? 'Bu süzgəcə uyğun xəbər yoxdur.' : 'Hələ xəbər yoxdur.' ?></td></tr>
<?php endif; ?>
	</tbody>
</table>
<?php
admin_shell_end();
