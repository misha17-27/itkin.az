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
 *
 * İngiliscə versiya:
 *   - hər mətn sahəsinin (başlıq, mətn, mətn bloku) altında EN sahəsi var; ilkin
 *     dəyəri orijinalın lüğətdəki tərcüməsidir (inc/lang/en/*.php). Ondan fərqli
 *     yazılan mətn data/page-texts-en.php faylına düşür, «İngiliscə tərcüməyə qaytar»
 *     qeydi silir. Şəkil, qalereya, video, keçid və rəqəm hər iki dildə eynidir;
 *   - səhifənin ingiliscə adı, SEO başlığı və təsviri data/pages-en.php-dədir.
 *     Ad boş qalsa, səhifənin ingiliscə versiyası söndürülür (inc/i18n.php → lang_has()).
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

/** Dilə görə tərcümə olunan sahələr; qalanları (şəkil, keçid, rəqəm …) hər iki dildə eynidir */
const ADMIN_PAGE_EN_TYPES = ['heading', 'text', 'html'];

$fields  = data_load('page-fields');
$texts   = data_load('page-texts');
$textsEn = data_load('page-texts-en');
$root    = dirname(__DIR__, 2);

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
    // əvvəlcə HTML-varlıqlar açılır: «&#106;avascript:», «java&Tab;script:» kimi
    // gizlədilmiş sxemlər də yoxlansın; sonra yalnız nisbi, http(s), mailto, tel
    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!admin_safe_url($decoded)) {
        return null;
    }
    return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
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
            // orijinalla eyni keçid (yazılışı fərqli olsa da) — orijinal olduğu kimi qalsın
            $orig = trim((string) $field['value']);
            if (html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8') === html_entity_decode($orig, ENT_QUOTES | ENT_HTML5, 'UTF-8')) {
                return $orig;
            }
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
 * İngiliscə sahənin qeydsiz dəyəri — saytın ingiliscə versiyasında göründüyü kimi:
 * orijinalın lüğətdəki tərcüməsi. Azərbaycancada boşaldılmış sahə isə ingiliscədə
 * də boş qalır (inc/helpers.php → page_override_text()).
 */
function admin_page_en_value(array $field, array $texts): string
{
    if (array_key_exists($field['key'], $texts) && $texts[$field['key']] === '') {
        return '';
    }
    // tərcüməsi olmayan (dildən asılı olmayan) sahə saytda azərbaycanca dəyişikliyi göstərir
    $orig = (string) $field['value'];
    if (t_lang($orig, 'en') === $orig && is_string($texts[$field['key']] ?? null)) {
        return $texts[$field['key']];
    }
    return t_lang($orig, 'en');
}

/** Həmin dəyər — müqayisə üçün azərbaycanca sahələrlə eyni qaydadan keçirilmiş */
function admin_page_en_default(array $field, array $texts): string
{
    return trim(admin_clean_html(trim(admin_page_en_value($field, $texts))));
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

        // İngiliscə mətnlər: lüğətdəki tərcümədən fərqlidirsə data/page-texts-en.php-yə yazılır
        $givenEn  = (array) ($_POST['en'] ?? []);
        $resetsEn = (array) ($_POST['reset_en'] ?? []);
        $nextEn   = $textsEn;

        foreach ($byPage[$which] ?? [] as $field) {
            $key = $field['key'];
            if (!in_array($field['type'], ADMIN_PAGE_EN_TYPES, true)) {
                continue;
            }
            // «İngiliscə tərcüməyə qaytar» — qeyd silinir, saytda yenə lüğətdəki tərcümə çıxır
            if (!empty($resetsEn[$key])) {
                unset($nextEn[$key]);
                continue;
            }
            if (!isset($givenEn[$key]) || !is_string($givenEn[$key])) {
                continue;   // sahə formada yox idi
            }
            $value = post_en($key, true);
            // Formada göstərilən ilkin dəyərlə eynidirsə qeyd lazım deyil; boş dəyər isə
            // saxlanılır — “ingiliscə saytda boş qalsın”
            if ($value === admin_page_en_default($field, $texts)) {
                unset($nextEn[$key]);
            } else {
                $nextEn[$key] = $value;
            }
        }

        if ($nextEn !== $textsEn) {
            store_save('page-texts-en', $nextEn, 'Səhifə mətnləri, ingiliscə / English page text overrides');
        }

        // SEO başlığı və təsviri data/pages.php-də saxlanılır (yazılması — aşağıda, «son dəyişiklik» ilə birlikdə)
        $pages   = data_load('pages');
        $pageIdx = null;
        foreach ($pages as $i => $page) {
            if ($page['slug'] === ADMIN_PAGES[$which][1]) {
                $pageIdx = $i;
                break;
            }
        }
        $pageBefore = $pageIdx !== null ? $pages[$pageIdx] : [];
        if ($pageIdx !== null) {
            $desc = post_str('description');
            $pages[$pageIdx]['doc_title']   = admin_seo_title($pageBefore['title'] . ' - ' . cfg('site_name'));
            $pages[$pageIdx]['description'] = $desc;
            $pages[$pageIdx]['head_meta']   = admin_sync_meta($pageBefore['head_meta'] ?? [], [
                'og:description' => $desc,
            ]);
        }

        // İngiliscə ad, SEO başlığı və təsvir — data/pages-en.php.
        // Forma bu sahələrsiz gəlibsə (köhnə səhifədən), ingiliscə versiyaya toxunmuruq.
        $enNote   = '';
        $record   = admin_page_record(ADMIN_PAGES[$which][1]);
        $enBefore = isset($record['id']) ? admin_en_get('pages', (int) $record['id']) : [];
        if (isset($record['id']) && array_key_exists('title', $givenEn)) {
            $pageId = (int) $record['id'];
            $hadEn  = $enBefore !== [];
            $enSeo  = [
                'title'       => post_en('title'),
                'doc_title'   => post_en('doc_title'),
                'description' => post_en('description'),
            ];
            // «Xəbərlər»in ingiliscə versiyası addan yox, xəbərlərin tərcüməsindən asılıdır
            if ($enSeo['title'] === '' && $record['slug'] !== 'xeberler') {
                admin_en_save('pages', $pageId, null);
                if ($hadEn || $enSeo['doc_title'] !== '' || $enSeo['description'] !== '') {
                    $enNote = 'İngiliscə ad boş olduğu üçün səhifənin ingiliscə versiyası söndürüldü.';
                }
            } else {
                admin_en_save('pages', $pageId, $enSeo);
            }
        }

        // «Son dəyişiklik» (admin/inc/authors.php) — yalnız səhifədə nəsə həqiqətən dəyişibsə:
        // mətnlər, ingiliscə mətnlər, SEO və ya ingiliscə ad
        if ($pageIdx !== null) {
            $enAfter = isset($record['id']) ? admin_en_get('pages', (int) $record['id']) : [];
            $changed = $next !== $texts || $nextEn !== $textsEn || $enAfter !== $enBefore
                || admin_author_changed($pages[$pageIdx], $pageBefore);
            $stamp = $changed ? admin_author_stamp() : [];
            if ($stamp) {
                $pages[$pageIdx]['modified_by'] = $stamp;
            }
            if ($pages[$pageIdx] !== $pageBefore) {
                store_save('pages', $pages, 'Statik səhifələr / static pages');
            }
        }

        admin_redirect(['section' => 'pages', 'page' => $which],
            ($warnings ? 'Yadda saxlanıldı, amma: ' . implode('; ', $warnings) . '.' : 'Səhifə yadda saxlanıldı.')
            . ($enNote !== '' ? ' ' . $enNote : ''),
            $warnings ? 'error' : 'ok');
    }
}

/* ---------------------------------------------------------------- siyahı */

if ($which === '') {
    admin_shell_start('pages', 'Səhifələr');   // «Saytı aç ↗» üst zolaqda hər səhifədə var
    ?>
<div class="card">
	<div class="card__body">
		<p class="pages-note">
			Səhifənin mətnləri, şəkilləri, qalereyası və SEO-su «Redaktə et» ilə açılır.
			Xəbərlər, kitablar və itkinlər öz bölmələrində redaktə olunur.
			<span class="lang-flag">EN</span> — səhifənin saytda ingiliscə versiyası var (<code>/en/…</code>);
			onun mətnləri də «Redaktə et»dədir.
		</p>
		<table class="table">
			<thead>
				<tr><th>Səhifə</th><th>Ünvan</th><th style="width:130px">Əlavə edib</th><th style="width:190px">Son dəyişiklik</th><th style="width:90px">Məzmun</th><th style="width:70px">SEO</th><th style="width:60px">EN</th><th></th></tr>
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
    $enOn    = lang_has($path, 'en');
?>
				<tr>
					<td>
						<a class="table__title" href="<?= e(admin_url(['section' => 'pages', 'page' => $key])) ?>"><?= e($label) ?></a>
<?php if ($changed): ?>
						<span class="table__sub"><?= $changed ?> dəyişiklik</span>
<?php endif; ?>
					</td>
					<td><span class="url-pill">/<?= e($path) ?></span></td>
					<td class="table__meta"><?= admin_author_cell($record, 'pages') ?></td>
					<td class="table__meta">
<?php if (($editor = admin_row_editor($record)) !== null): ?>
						<span class="author<?= $editor['origin'] === 'gone' ? ' author--gone' : '' ?>"><?= e($editor['name']) ?></span>
						<span class="table__sub"><?= e(admin_author_when($editor['at'])) ?><?= $editor['origin'] === 'gone' ? ' · istifadəçi silinib' : '' ?></span>
<?php else: ?>
						<span class="author author--none" title="Panel bunu qeyd etməyə başlayandan bəri dəyişdirilməyib">—</span>
<?php endif; ?>
					</td>
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
					<td>
<?php if ($enOn): ?>
						<span class="lang-flag" title="İngiliscə versiyası var">EN</span>
<?php else: ?>
						<span class="lang-flag lang-flag--off" title="<?= $slug === 'xeberler'
                            ? 'İngiliscə versiyası yoxdur — heç bir xəbərin ingiliscə mətni yoxdur'
                            : 'İngiliscə versiyası yoxdur — səhifənin ingiliscə adı yazılmayıb' ?>">—</span>
<?php endif; ?>
					</td>
					<td class="is-right">
						<div class="table__actions">
							<a class="btn btn--sm" href="<?= e(admin_url(['section' => 'pages', 'page' => $key])) ?>">Redaktə et</a>
							<a class="btn btn--sm" href="<?= e(url($path)) ?>" target="_blank" rel="noopener">Aç</a>
<?php if ($enOn): ?>
							<a class="btn btn--sm" href="<?= e(url_lang($path, 'en')) ?>" target="_blank" rel="noopener">EN ↗</a>
<?php endif; ?>
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
$enOn   = lang_has($pagePath, 'en');
$enSeo  = isset($record['id']) ? admin_en_get('pages', (int) $record['id']) : [];

$top = [
    ['href' => admin_url(['section' => 'pages']), 'label' => '← Bütün səhifələr'],
    ['href' => url($pagePath), 'label' => 'Saytda bax ↗'],
];
if ($enOn) {
    $top[] = ['href' => url_lang($pagePath, 'en'), 'label' => 'Saytda bax (EN) ↗'];
}
admin_shell_start('pages', $pageLabel, $top);
f_errors($errors);
f_open(['section' => 'pages', 'action' => 'edit', 'page' => $which]);
?>
<div class="page-edited"><?= admin_author_box($record, 'pages') ?></div>
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
		<p class="field__hint">
			Mətnlərin altındakı <span class="lang-flag">EN</span> sahəsi saytın ingiliscə versiyası üçündür;
			orada hazır tərcümə yazılıb. Şəkillər, qalereya, video, keçidlər və rəqəmlər hər iki dildə eynidir.
<?php if (!$enOn): ?>
			<strong>Bu səhifənin ingiliscə versiyası hazırda söndürülüb</strong> — EN mətnləri saytda görünmür,
			ta ki aşağıda «İngiliscə versiya» kartında səhifənin ingiliscə adı yazılana qədər.
<?php endif; ?>
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
<?php if (in_array($type, ADMIN_PAGE_EN_TYPES, true)): ?>
<?php
    $enChanged = array_key_exists($key, $textsEn);
    $enValue   = $enChanged ? (string) $textsEn[$key] : admin_page_en_value($field, $texts);
    $enId      = 'f-en-' . $key;
    $enName    = 'en[' . $key . ']';
    $enMulti   = $type !== 'html' && (!empty($multi) || strpos($enValue, "\n") !== false
        || ($type === 'heading' && mb_strlen($enValue) > 60));
    $enDict    = t_lang((string) $field['value'], 'en');
?>
			<div class="pf__en">
				<div class="field">
					<label for="<?= e($enId) ?>"><span class="lang-flag">EN</span><?= f_badge($enChanged ? ['badge' => 'dəyişdirilib'] : []) ?></label>
<?php if ($type === 'html'): ?>
					<textarea class="textarea" id="<?= e($enId) ?>" name="<?= e($enName) ?>" rows="8" data-rich="<?= e($field['label']) ?> (ingiliscə)" lang="en"><?= e($enValue) ?></textarea>
<?php elseif ($enMulti): ?>
					<textarea class="textarea" id="<?= e($enId) ?>" name="<?= e($enName) ?>" rows="2" style="min-height:64px" lang="en"><?= e($enValue) ?></textarea>
<?php else: ?>
					<input class="input" type="text" id="<?= e($enId) ?>" name="<?= e($enName) ?>" value="<?= e($enValue) ?>" lang="en">
<?php endif; ?>
<?php if ($enChanged): ?>
					<span class="field__hint">Lüğətdəki tərcümə: <?= e(mb_strimwidth(strip_tags($enDict), 0, 120, '…')) ?></span>
<?php elseif ($enValue === '' && $enDict !== ''): ?>
					<span class="field__hint">Azərbaycanca sahə boşaldıldığı üçün ingiliscədə də boşdur. Mətn yazsanız, ingiliscə saytda görünəcək.</span>
<?php endif; ?>
				</div>
<?php if ($enChanged): ?>
				<label class="check check--reset">
					<input type="checkbox" name="reset_en[<?= e($key) ?>]" value="1"> İngiliscə tərcüməyə qaytar
				</label>
<?php endif; ?>
			</div>
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

<?php
$enTitle    = (string) ($enSeo['title'] ?? '');
$enSiteName = trim((string) cfg('site_name_en', '')) !== '' ? trim((string) cfg('site_name_en')) : (string) cfg('site_name', '');
f_en_open($enOn ? url_lang($pagePath, 'en') : '', $pageSlug === 'xeberler'
    ? '«Xəbərlər» səhifəsinin ingiliscə versiyası ən azı bir xəbərə ingiliscə mətn yazılanda avtomatik açılır'
      . ' («<a href="' . e(admin_url(['section' => 'posts'])) . '">Xəbərlər</a>» bölməsində). Burada onun ingiliscə adı'
      . ' və SEO-su yazılır; boş buraxılan sahənin yerində azərbaycanca mətn çıxır.'
    : 'Saytın <code>/en/</code> versiyası üçün. Səhifənin ingiliscə adı brauzerin başlığında, «Home › …» yolunda'
      . ' və paylaşma önizləməsində (og:title) işlənir. <strong>Adı boş buraxsanız, səhifənin ingiliscə versiyası'
      . ' söndürülür</strong>: <code>/en/…</code> ünvanı azərbaycanca səhifəyə yönləndirilir, dil düyməsi görünmür.');
f_text('en[title]', 'Səhifənin ingiliscə adı', $enTitle, [
    'hint' => $pageSlug === 'xeberler' ? 'Boş buraxsanız «' . e((string) $record['title']) . '» göstərilir.' : '',
]);
f_text('en[doc_title]', 'SEO başlıq (ingiliscə)', (string) ($enSeo['doc_title'] ?? ''), ($enTitle !== ''
    ? ['placeholder' => $enTitle . ' - ' . $enSiteName] : []) + [
    'hint' => 'Boş buraxsanız ingiliscə addan avtomatik qurulur («Ad - ' . e($enSiteName) . '»).',
]);
f_textarea('en[description]', 'Təsvir (ingiliscə)', (string) ($enSeo['description'] ?? ''), [
    'rows' => 3,
    'hint' => 'Axtarış nəticələrində və paylaşma önizləməsində. Boş buraxsanız azərbaycanca təsvir göstərilir.',
]);
f_en_close();
?>

<div class="actions">
	<button class="btn btn--primary" type="submit">Yadda saxla</button>
	<a class="btn" href="<?= e(admin_url(['section' => 'pages'])) ?>">Ləğv et</a>
</div>
<?php
f_close();
admin_shell_end();
