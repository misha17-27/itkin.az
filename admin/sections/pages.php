<?php
/**
 * Səhifələr bölməsi.
 *
 * Siyahı: hər statik səhifə, ünvanı, məzmun və SEO vəziyyəti.
 * Redaktə: səhifənin bütün mətnləri, mətn blokları, şəkilləri, qalereyası,
 * fon şəkli və videosu, həmçinin SEO — hamısı səhifədə göründüyü ardıcıllıqla.
 *
 * Dəyişikliklər data/page-texts.php faylına yazılır, şablonlardakı orijinal
 * isə toxunulmaz qalır.
 *   - boşaldılmış sahə saytda da boş qalır (abzası, şəkli, qalereyanı götürmək olur);
 *   - «Orijinala qaytar» qeydi silir və o hissə yenidən bayt-bayt orijinal kimi çıxır;
 *   - dəyəri orijinalla eyni olan sahə üçün qeyd saxlanılmır.
 */

/** açar (data/page-fields.php-dəki prefiks) => [ad, data/pages.php slug-ı, saytdakı ünvan] */
const ADMIN_PAGES = [
    'home'       => ['Ana səhifə',           'ana-sehife',           ''],
    'haqqimizda' => ['Haqqımızda',           'haqqimizda',           'haqqimizda'],
    'elaqe'      => ['Əlaqə',                'elaqe',                'elaqe'],
    'senedler'   => ['Beynəlxalq sənədlər',  'beynelxalq-senedler',  'beynelxalq-senedler'],
    'qanun'      => ['Milli qanunvericilik', 'milli-qanunvericilik', 'milli-qanunvericilik'],
    'sekiller'   => ['Şəkillər',             'sekiller',             'sekiller'],
    'kitabxana'  => ['Kitabxana',            'kitabxana',            'kitabxana'],
    'xeberler'   => ['Xəbərlər',             'xeberler',             'xeberler'],
];

/** Məzmunu avtomatik yığılan səhifələr: harada redaktə olunur */
const ADMIN_PAGE_SOURCES = [
    'kitabxana' => ['kitabxana', 'Kitabxana'],
    'xeberler'  => ['posts', 'Xəbərlər'],
];

$fields = data_load('page-fields');
$texts  = data_load('page-texts');
$root   = dirname(__DIR__, 2);

$byPage = [];
foreach ($fields as $field) {
    $byPage[$field['page']][] = $field;
}

$which = (string) ($_GET['page'] ?? '');
if ($which !== '' && !isset(ADMIN_PAGES[$which])) {
    $which = '';
}

/* ---------------------------------------------------------------- köməkçilər */

/** data/pages.php-dəki qeyd (SEO buradadır) */
function admin_page_record(string $slug): array
{
    foreach (data_load('pages') as $page) {
        if ($page['slug'] === $slug) {
            return $page;
        }
    }
    return ['slug' => $slug, 'title' => '', 'doc_title' => '', 'description' => '', 'head_meta' => []];
}

/** Sahənin hazırkı dəyəri: dəyişdirilibsə yenisi, yoxsa orijinal */
function admin_page_value(array $field, array $texts)
{
    if (!array_key_exists($field['key'], $texts)) {
        return $field['type'] === 'gallery' ? (array) $field['value'] : (string) $field['value'];
    }
    $value = $texts[$field['key']];
    return $field['type'] === 'gallery' ? array_values((array) $value) : (string) $value;
}

function admin_page_changed(array $field, array $texts): bool
{
    return array_key_exists($field['key'], $texts);
}

/** Keçid sahəsi üçün: təhlükəli sxemdirsə null, yoxsa atribut üçün qaçırılmış dəyər */
function admin_page_url(string $raw): ?string
{
    if (preg_match('#^\s*(javascript|data|vbscript):#i', $raw)) {
        return null;
    }
    return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8', false);
}

/**
 * Formadan gələni saxlanılacaq dəyərə çevirir.
 * null qaytarırsa — sahə formada yox idi və ya dəyər qəbul edilmədi, toxunmuruq.
 * Qəbul edilməyən dəyərin səbəbi $warnings-ə yazılır.
 */
function admin_page_take(array $field, array $given, array $galleries, string $root, array &$warnings)
{
    $key  = $field['key'];
    $type = $field['type'];

    if ($type === 'gallery') {
        if (!isset($galleries[$key])) {
            return null;
        }
        $list = [];
        foreach ((array) ($given[$key] ?? []) as $path) {
            $path = trim((string) $path);
            if (page_path_ok($path) && is_file($root . '/' . $path)) {
                $list[] = $path;
            }
        }
        return $list;
    }

    if (!isset($given[$key]) || !is_string($given[$key])) {
        return null;
    }
    $raw = trim(admin_eol($given[$key]));

    switch ($type) {
        case 'image':
        case 'bg':
        case 'video':
            if ($raw === '') {
                return '';   // şəkil/video götürülüb
            }
            if (page_path_ok($raw) && is_file($root . '/' . $raw)) {
                return $raw;
            }
            $warnings[] = $field['label'] . ': fayl tapılmadı, əvvəlki qaldı';
            return null;
        case 'html':
        case 'heading':
        case 'text':
            return trim(admin_clean_html($raw));
        case 'number':
            return (string) preg_replace('/[^0-9]/', '', $raw);
        case 'url':
            $url = admin_page_url($raw);
            if ($url === null) {
                $warnings[] = $field['label'] . ': belə keçid qəbul edilmir, əvvəlki qaldı';
            }
            return $url;
    }
    return $raw;
}

/** Orijinal dəyər — müqayisə üçün eyni qaydadan keçirilmiş */
function admin_page_default(array $field)
{
    switch ($field['type']) {
        case 'gallery':
            return array_values((array) $field['value']);
        case 'html':
        case 'heading':
        case 'text':
            return trim(admin_clean_html(trim((string) $field['value'])));
    }
    return trim((string) $field['value']);
}

/**
 * Ekranda göstərmək üçün sıra: keçid sahəsi (düymənin ünvanı, bəndin keçidi)
 * markupda mətnindən əvvəl gəlir, amma oxumaq üçün mətn birinci olmalıdır.
 */
function admin_page_order(array $list): array
{
    for ($i = 0; $i < count($list) - 1; $i++) {
        if ($list[$i]['type'] === 'url' && $list[$i + 1]['type'] === 'text') {
            [$list[$i], $list[$i + 1]] = [$list[$i + 1], $list[$i]];
            $i++;
        }
    }
    return $list;
}

/* ---------------------------------------------------------------- yazmaq */

$errors = [];

if ($which !== '' && $action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_token_ok()) {
        $errors[] = 'Forma köhnəlib. Səhifəni yeniləyin.';
    } else {
        $given     = (array) ($_POST['field'] ?? []);
        $galleries = (array) ($_POST['gallery'] ?? []);
        $resets    = (array) ($_POST['reset'] ?? []);
        $next      = $texts;
        $warnings  = [];

        foreach ($byPage[$which] ?? [] as $field) {
            // «Orijinala qaytar» işarələnibsə — qeydi silirik, formadakı dəyərə baxmırıq
            if (!empty($resets[$field['key']])) {
                unset($next[$field['key']]);
                continue;
            }
            $value = admin_page_take($field, $given, $galleries, $root, $warnings);
            if ($value === null) {
                continue;
            }
            // Orijinalla eynidirsə qeyd lazım deyil; boş dəyər isə saxlanılır — “boş qalsın”
            if ($value === admin_page_default($field)) {
                unset($next[$field['key']]);
            } else {
                $next[$field['key']] = $value;
            }
        }

        store_save('page-texts', $next, 'Səhifə mətnləri / page text overrides');

        // SEO başlığı və təsviri data/pages.php-də saxlanılır
        $pages = data_load('pages');
        foreach ($pages as $i => $page) {
            if ($page['slug'] !== ADMIN_PAGES[$which][1]) {
                continue;
            }
            $desc = post_str('description');
            $pages[$i]['doc_title']   = admin_seo_title($page['title'] . ' - ' . cfg('site_name'));
            $pages[$i]['description'] = $desc;
            $pages[$i]['head_meta']   = admin_sync_meta($page['head_meta'] ?? [], [
                'og:description' => $desc,
            ]);
            store_save('pages', $pages, 'Statik səhifələr / static pages');
            break;
        }

        admin_redirect(['section' => 'pages', 'page' => $which],
            $warnings ? 'Yadda saxlanıldı, amma: ' . implode('; ', $warnings) . '.' : 'Səhifə yadda saxlanıldı.',
            $warnings ? 'error' : 'ok');
    }
}

/* ---------------------------------------------------------------- siyahı */

if ($which === '') {
    admin_shell_start('pages', 'Səhifələr', [
        ['href' => base_path() . '/', 'label' => 'Saytı aç ↗'],
    ]);
    ?>
<div class="card">
	<div class="card__body">
		<p class="pages-note">
			Səhifənin mətnləri, şəkilləri, qalereyası və SEO-su «Redaktə et» ilə açılır.
			Xəbərlər, kitablar və itkinlər öz bölmələrində redaktə olunur.
		</p>
		<table class="table">
			<thead>
				<tr><th>Səhifə</th><th>Ünvan</th><th style="width:90px">Məzmun</th><th style="width:70px">SEO</th><th></th></tr>
			</thead>
			<tbody>
<?php foreach (ADMIN_PAGES as $key => [$label, $slug, $path]): ?>
<?php
    $list    = $byPage[$key] ?? [];
    $changed = count(array_filter($list, static function (array $f) use ($texts) {
        return admin_page_changed($f, $texts);
    }));
    $record  = admin_page_record($slug);
    $seoOk   = trim((string) $record['doc_title']) !== '' && trim((string) $record['description']) !== '';
    $auto    = ADMIN_PAGE_SOURCES[$key] ?? null;
?>
				<tr>
					<td>
						<a class="table__title" href="<?= e(admin_url(['section' => 'pages', 'page' => $key])) ?>"><?= e($label) ?></a>
<?php if ($changed): ?>
						<span class="table__sub"><?= $changed ?> dəyişiklik</span>
<?php endif; ?>
					</td>
					<td><span class="url-pill">/<?= e($path) ?></span></td>
					<td>
<?php if ($list): ?>
						<span class="dot" title="<?= count($list) ?> sahə redaktə olunur"></span>
<?php else: ?>
						<span class="dot dot--off" title="Məzmun avtomatik yığılır<?= $auto ? ' — «' . e($auto[1]) . '» bölməsindən' : '' ?>"></span>
<?php endif; ?>
					</td>
					<td>
						<span class="dot<?= $seoOk ? '' : ' dot--warn' ?>" title="<?= $seoOk ? 'SEO başlıq və təsvir var' : 'SEO təsviri yoxdur' ?>"></span>
					</td>
					<td class="is-right">
						<div class="table__actions">
							<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'pages', 'page' => $key])) ?>">Redaktə et</a>
							<a class="btn btn--sm" href="<?= e(url($path)) ?>" target="_blank" rel="noopener">Aç</a>
						</div>
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php
    admin_shell_end();
    return;
}

/* ---------------------------------------------------------------- forma */

[$pageLabel, $pageSlug, $pagePath] = ADMIN_PAGES[$which];
$record = admin_page_record($pageSlug);
$list   = admin_page_order($byPage[$which] ?? []);

admin_shell_start('pages', $pageLabel, [
    ['href' => admin_url(['section' => 'pages']), 'label' => '← Bütün səhifələr'],
    ['href' => url($pagePath), 'label' => 'Saytda bax ↗'],
]);
f_errors($errors);
f_open(['section' => 'pages', 'action' => 'edit', 'page' => $which]);
?>
<div class="card">
	<div class="card__head">Məzmun</div>
	<div class="card__body">
<?php if (!$list): ?>
<?php $src = ADMIN_PAGE_SOURCES[$which] ?? null; ?>
		<p style="margin:0">
			Bu səhifənin məzmunu avtomatik yığılır<?php if ($src): ?> —
			onu <a href="<?= e(admin_url(['section' => $src[0]])) ?>">«<?= e($src[1]) ?>»</a> bölməsində dəyişin<?php endif; ?>.
			Aşağıda yalnız SEO sahələri var.
		</p>
<?php else: ?>
		<p class="field__hint" style="margin-top:0">
			Sahələr səhifədə göründüyü ardıcıllıqladır. Boşaltdığınız sahə saytda da boş qalır.
			Dəyişdirilmiş sahənin altında «Orijinala qaytar» var — əvvəlki mətn və ya şəkil geri gəlir.
		</p>
<?php foreach ($list as $field): ?>
<?php
    $key     = $field['key'];
    $type    = $field['type'];
    $name    = 'field[' . $key . ']';
    $value   = admin_page_value($field, $texts);
    $changed = admin_page_changed($field, $texts);
    $badge   = $changed ? ['badge' => 'dəyişdirilib'] : [];
    $section = $type === 'heading';
?>
		<div class="pf<?= $section ? ' pf--section' : '' ?>">
<?php
    switch ($type) {
        case 'image':
        case 'bg':
            f_image($name, $field['label'], (string) $value, $badge + [
                'clearable' => true,
                'reset' => (string) $field['value'],
                'hint'  => $type === 'bg' ? 'Blokun arxa fonundakı şəkil.' : '',
            ]);
            break;

        case 'video':
            f_video($name, $field['label'], (string) $value, $badge + [
                'clearable' => true,
                'reset' => (string) $field['value'],
                'hint'  => 'Ana səhifənin yuxarısında səssiz fırlanan video. MP4, mümkün qədər yüngül.',
            ]);
            break;

        case 'gallery':
            f_gallery($name, 'gallery[' . $key . ']', $field['label'], (array) $value, $badge + [
                'reset' => (array) $field['value'],
            ]);
            break;

        case 'html':
            f_textarea($name, $field['label'], (string) $value, $badge + ['rich' => true, 'rows' => 8]);
            break;

        default:
            $multi = $type === 'heading' && (mb_strlen((string) $value) > 60 || strpos((string) $value, "\n") !== false);
            ?>
			<div class="field">
				<label for="f-<?= e($key) ?>"><?= e($field['label']) ?><?= f_badge($badge) ?></label>
<?php if ($multi): ?>
				<textarea class="textarea" id="f-<?= e($key) ?>" name="<?= e($name) ?>" rows="2" style="min-height:64px"><?= e((string) $value) ?></textarea>
<?php else: ?>
				<input class="input" type="text" id="f-<?= e($key) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
				       <?= $type === 'number' ? 'inputmode="numeric"' : '' ?><?= $type === 'url' ? ' placeholder="https://… və ya uploads/…"' : '' ?>>
<?php endif; ?>
<?php if ($changed): ?>
				<span class="field__hint">Orijinal: <?= e(mb_strimwidth((string) $field['value'], 0, 120, '…')) ?></span>
<?php endif; ?>
			</div>
<?php
    }
?>
<?php if ($changed): ?>
			<label class="check check--reset">
				<input type="checkbox" name="reset[<?= e($key) ?>]" value="1"> Orijinala qaytar
			</label>
<?php endif; ?>
		</div>
<?php endforeach; ?>
<?php endif; ?>
	</div>
</div>

<div class="card">
	<div class="card__head">Axtarış sistemləri</div>
	<div class="card__body">
		<?php
        f_text('doc_title', 'SEO başlıq', (string) ($record['doc_title'] ?? ''), [
            'hint' => 'Brauzerin başlığında və Google nəticələrində görünür. Boş buraxsanız addan avtomatik qurulur.',
        ]);
        f_textarea('description', 'Təsvir (meta description)', (string) ($record['description'] ?? ''), [
            'rows' => 3,
            'hint' => 'Axtarış nəticələrində başlığın altındakı izah. 150–160 simvol yaxşı ölçüdür.',
        ]);
        ?>
	</div>
</div>

<div class="actions">
	<button class="btn btn--primary" type="submit">Yadda saxla</button>
	<a class="btn" href="<?= e(admin_url(['section' => 'pages'])) ?>">Ləğv et</a>
</div>
<?php
f_close();
admin_shell_end();
