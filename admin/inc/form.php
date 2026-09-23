<?php
/**
 * Forma elementləri / form field helpers.
 */

function f_open(array $params, array $attrs = []): void
{
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e((string) $v) . '"';
    }
    echo '<form class="form" method="post" action="' . e(admin_url($params)) . '"' . $extra . '>';
    echo admin_token_field();
}

function f_close(): void
{
    echo '</form>';
}

function f_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="errors"><strong>Yadda saxlamaq alınmadı:</strong><ul>';
    foreach ($errors as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul></div>';
}

function f_text(string $name, string $label, string $value, array $opt = []): void
{
    $type = $opt['type'] ?? 'text';
    ?>
	<div class="field">
		<label for="f-<?= e($name) ?>"><?= e($label) ?></label>
		<input class="input" type="<?= e($type) ?>" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
		       value="<?= e($value) ?>"<?= !empty($opt['required']) ? ' required' : '' ?>
		       <?= isset($opt['placeholder']) ? 'placeholder="' . e($opt['placeholder']) . '"' : '' ?>
		       <?= isset($opt['data']) ? 'data-' . e($opt['data']) . '="1"' : '' ?>>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

/**
 * Mətn sahəsi.
 *
 * 'rich' => true verilsə, editor.js bu sahəni vizual redaktora çevirir.
 * <textarea> yerində qalır və məzmunun əsl saxlandığı yer elə odur — JS
 * işləməsə də sahə adi kod sahəsi kimi işləyir.
 */
function f_textarea(string $name, string $label, string $value, array $opt = []): void
{
    $class = 'textarea' . (!empty($opt['tall']) ? ' textarea--tall' : '');
    $rich  = !empty($opt['rich']);
    ?>
	<div class="field">
		<label for="f-<?= e($name) ?>"><?= e($label) ?><?= f_badge($opt) ?></label>
		<textarea class="<?= $class ?>" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
		          rows="<?= (int) ($opt['rows'] ?? 5) ?>"<?= $rich ? ' data-rich="' . e($label) . '"' : '' ?>><?= e($value) ?></textarea>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

function f_select(string $name, string $label, array $options, string $value, array $opt = []): void
{
    ?>
	<div class="field">
		<label for="f-<?= e($name) ?>"><?= e($label) ?></label>
		<select class="select" id="f-<?= e($name) ?>" name="<?= e($name) ?>">
<?php foreach ($options as $key => $text): ?>
			<option value="<?= e((string) $key) ?>"<?= (string) $key === $value ? ' selected' : '' ?>><?= e($text) ?></option>
<?php endforeach; ?>
		</select>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

function f_checks(string $name, string $label, array $options, array $selected): void
{
    ?>
	<div class="field">
		<span><strong><?= e($label) ?></strong></span>
		<div class="checks">
<?php foreach ($options as $key => $text): ?>
			<label class="check">
				<input type="checkbox" name="<?= e($name) ?>[]" value="<?= e((string) $key) ?>"
				       <?= in_array((int) $key, $selected, true) ? 'checked' : '' ?>>
				<?= e($text) ?>
			</label>
<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/** Sahə adının yanında kiçik nişan (məsələn “dəyişdirilib”) */
function f_badge(array $opt): string
{
    return !empty($opt['badge']) ? ' <span class="badge">' . e($opt['badge']) . '</span>' : '';
}

/**
 * Şəkil sahəsi.
 *
 * Yol istifadəçiyə göstərilmir — dəyər gizli sahədə saxlanılır, ekranda isə
 * yalnız şəklin özü olur. Şəklə və ya düyməyə basanda kitabxana açılır,
 * orada seçim edilən kimi pəncərə bağlanır və şəkil buraya düşür.
 *
 * 'clearable' => true   “Təmizlə” düyməsi (şəkli götürür)
 * 'reset' => 'uploads/…' “Orijinala qaytar” düyməsi (səhifə şəkilləri üçün)
 */
function f_image(string $name, string $label, string $value, array $opt = []): void
{
    $preview = $value !== '' ? asset($value) : '';
    ?>
	<div class="field">
		<label><?= e($label) ?><?= f_badge($opt) ?></label>
		<div class="pick">
			<button type="button" class="pick__box<?= $preview === '' ? ' is-empty' : '' ?>"
			        data-pick="<?= e($name) ?>" title="Kitabxanadan şəkil seç">
				<img class="pick__preview" id="p-<?= e($name) ?>" src="<?= e($preview) ?>" alt=""<?= $preview === '' ? ' hidden' : '' ?>>
				<span class="pick__none">Şəkil seçilməyib</span>
			</button>
			<input type="hidden" id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>">
			<div class="pick__side">
				<div class="actions">
					<button type="button" class="btn btn--sm" data-pick="<?= e($name) ?>">Kitabxanadan seç</button>
					<?php if (!empty($opt['clearable'])): ?>
					<button type="button" class="btn btn--sm" data-clear="<?= e($name) ?>">Təmizlə</button>
					<?php endif; ?>
					<?php if (!empty($opt['reset'])): ?>
					<button type="button" class="btn btn--sm" data-reset="<?= e($name) ?>" data-reset-to="<?= e($opt['reset']) ?>">Orijinala qaytar</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

/** Video sahəsi — şəkil sahəsi kimi, sadəcə kitabxanada yalnız videolar görünür */
function f_video(string $name, string $label, string $value, array $opt = []): void
{
    $src = $value !== '' ? asset($value) : '';
    ?>
	<div class="field">
		<label><?= e($label) ?><?= f_badge($opt) ?></label>
		<div class="pick">
			<button type="button" class="pick__box pick__box--wide<?= $src === '' ? ' is-empty' : '' ?>"
			        data-pick="<?= e($name) ?>" data-kind="video" title="Kitabxanadan video seç">
				<video class="pick__preview" id="p-<?= e($name) ?>" src="<?= e($src) ?>" muted playsinline preload="metadata"<?= $src === '' ? ' hidden' : '' ?>></video>
				<span class="pick__none">Video seçilməyib</span>
			</button>
			<input type="hidden" id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>">
			<div class="pick__side">
				<div class="actions">
					<button type="button" class="btn btn--sm" data-pick="<?= e($name) ?>" data-kind="video">Kitabxanadan seç</button>
					<?php if (!empty($opt['clearable'])): ?>
					<button type="button" class="btn btn--sm" data-clear="<?= e($name) ?>">Təmizlə</button>
					<?php endif; ?>
					<?php if (!empty($opt['reset'])): ?>
					<button type="button" class="btn btn--sm" data-reset="<?= e($name) ?>" data-reset-to="<?= e($opt['reset']) ?>">Orijinala qaytar</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

/**
 * Fayl sahəsi (PDF): yol göstərilmir — faylın adı, «PDF yüklə» düyməsi
 * (kompüterdən birbaşa yükləyir), «Kitabxanadan seç» və «Təmizlə».
 * Kənar saytdakı köhnə keçid (məsələn human.gov.az) olduğu kimi qalır və
 * ayrıca göstərilir — yalnız yeni fayl yükləndikdə əvəzlənir.
 */
function f_file(string $name, string $label, string $value, array $opt = []): void
{
    $external = $value !== '' && preg_match('#^https?://#i', $value);
    $href     = $value === '' ? '' : ($external ? $value : asset($value));
    $shown    = $value === '' ? '' : rawurldecode(basename((string) parse_url($value, PHP_URL_PATH)));
    $size     = '';
    if ($value !== '' && !$external) {
        $file = dirname(__DIR__, 2) . '/' . $value;
        if (is_file($file)) {
            $bytes = (int) filesize($file);
            $size  = $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
        }
    }
    ?>
	<div class="field">
		<label><?= e($label) ?><?= f_badge($opt) ?></label>
		<div class="filepick<?= $value === '' ? ' is-empty' : '' ?>" data-file-field="<?= e($name) ?>">
			<input type="hidden" id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>">
			<div class="filepick__current">
				<span class="filepick__icon" aria-hidden="true">PDF</span>
				<a class="filepick__name" id="p-<?= e($name) ?>" href="<?= e($href) ?>" target="_blank" rel="noopener"<?= $value === '' ? ' hidden' : '' ?>><?= e($shown) ?></a>
				<span class="filepick__meta"><?= $external ? 'kənar saytda' : e($size) ?></span>
				<span class="filepick__none">Fayl yoxdur</span>
			</div>
			<div class="actions">
				<label class="btn btn--sm btn--primary filepick__upload">
					PDF yüklə
					<input type="file" accept="application/pdf,.pdf" data-file-upload="<?= e($name) ?>" data-kind="pdf" hidden>
				</label>
				<button type="button" class="btn btn--sm" data-pick="<?= e($name) ?>" data-kind="pdf">Kitabxanadan seç</button>
				<button type="button" class="btn btn--sm" data-clear="<?= e($name) ?>">Təmizlə</button>
			</div>
			<span class="filepick__status" aria-live="polite"></span>
		</div>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

/**
 * Şəkil üçün yüngül önizləmə: WordPress-in kəsdiyi kiçik nüsxə varsa onu
 * götürür — qalereyada 60 tam ölçülü şəkil yükləməmək üçün.
 */
function f_thumb(string $path): string
{
    static $root = null;
    $root = $root ?? dirname(__DIR__, 2);
    $dot = strrpos($path, '.');
    if ($dot !== false) {
        foreach (['-150x150', '-300x300', '-300x225', '-225x300', '-300x200', '-200x300'] as $size) {
            $small = substr($path, 0, $dot) . $size . substr($path, $dot);
            if (is_file($root . '/' . $small)) {
                return $small;
            }
        }
    }
    return $path;
}

/**
 * Qalereya: şəkillərin siyahısı. Sıranı dəyişmək (sürüşdürmək və ya oxlarla),
 * silmək və kitabxanadan bir neçəsini birdən əlavə etmək olur.
 *
 * $name     forma adı; hər şəkil "$name[]" kimi göndərilir
 * $marker   formada bu qalereyanın olduğunu bildirən gizli sahənin adı —
 *           bütün şəkillər silinəndə "$name[]" heç göndərilmir
 * 'reset'   orijinal siyahı (“Orijinala qaytar” üçün)
 */
function f_gallery(string $name, string $marker, string $label, array $paths, array $opt = []): void
{
    $item = static function (string $path) use ($name): string {
        return '<figure class="gal__item" draggable="true">'
            . '<img src="' . e(asset(f_thumb($path))) . '" alt="" loading="lazy">'
            . '<input type="hidden" name="' . e($name) . '[]" value="' . e($path) . '">'
            . '<div class="gal__tools">'
            . '<button type="button" data-gal-move="-1" title="Sola">‹</button>'
            . '<button type="button" data-gal-move="1" title="Sağa">›</button>'
            . '<button type="button" data-gal-remove title="Qalereyadan çıxar">×</button>'
            . '</div></figure>';
    };
    $reset = [];
    foreach ((array) ($opt['reset'] ?? []) as $path) {
        $reset[] = ['p' => $path, 't' => asset(f_thumb($path))];
    }
    ?>
	<div class="field">
		<label><?= e($label) ?> <span class="field__hint" data-gal-count><?= count($paths) ?> şəkil</span><?= f_badge($opt) ?></label>
		<div class="gal" data-gallery="<?= e($name) ?>" data-reset='<?= e((string) json_encode($reset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'>
			<input type="hidden" name="<?= e($marker) ?>" value="1">
			<div class="gal__grid">
<?php foreach ($paths as $path): ?>
				<?= $item((string) $path) ?>

<?php endforeach; ?>
			</div>
			<div class="actions">
				<button type="button" class="btn btn--sm" data-gal-add>+ Şəkil əlavə et</button>
				<?php if ($reset): ?>
				<button type="button" class="btn btn--sm" data-gal-reset>Orijinala qaytar</button>
				<?php endif; ?>
			</div>
		</div>
		<span class="field__hint">Şəkilləri sürüşdürərək və ya ‹ › ilə sıralayın. Hamısını silsəniz qalereya saytda boş qalır.</span>
	</div>
	<?php
}

/**
 * Yadda saxla / Sil düymələri.
 *
 * “Sil” adi keçid deyil, elə bu formanın özünü silmə ünvanına göndərən
 * düymədir — beləliklə sorğu POST olur və gizli token da onunla gedir.
 * formnovalidate lazımdır ki, boş məcburi sahə silməyə mane olmasın.
 */
function f_actions(string $backHref, ?string $deleteHref = null): void
{
    ?>
	<div class="actions actions--split">
		<div class="actions">
			<button class="btn btn--primary" type="submit">Yadda saxla</button>
			<a class="btn" href="<?= e($backHref) ?>">Ləğv et</a>
		</div>
<?php if ($deleteHref !== null): ?>
		<button class="btn btn--danger" type="submit" formmethod="post" formnovalidate
		        formaction="<?= e($deleteHref) ?>"
		        data-confirm="Silmək istədiyinizə əminsiniz?">Sil</button>
<?php endif; ?>
	</div>
	<?php
}
