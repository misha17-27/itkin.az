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

function f_textarea(string $name, string $label, string $value, array $opt = []): void
{
    $class = 'textarea' . (!empty($opt['tall']) ? ' textarea--tall' : '');
    ?>
	<div class="field">
		<label for="f-<?= e($name) ?>"><?= e($label) ?></label>
		<textarea class="<?= $class ?>" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
		          rows="<?= (int) ($opt['rows'] ?? 5) ?>"><?= e($value) ?></textarea>
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

/** Şəkil seçimi: yol + önizləmə + kitabxanadan seçmə düyməsi */
function f_image(string $name, string $label, string $value, array $opt = []): void
{
    $preview = $value !== '' ? asset($value) : '';
    ?>
	<div class="field">
		<label for="f-<?= e($name) ?>"><?= e($label) ?></label>
		<div class="pick">
			<img class="pick__preview" id="p-<?= e($name) ?>" src="<?= e($preview) ?>" alt=""
			     <?= $preview === '' ? 'style="visibility:hidden"' : '' ?>>
			<div class="pick__side">
				<input class="input" type="text" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
				       value="<?= e($value) ?>" placeholder="uploads/2026/10/sekil.jpg" data-image-input>
				<div class="actions">
					<button type="button" class="btn btn--sm" data-pick="<?= e($name) ?>">Kitabxanadan seç</button>
					<?php if (!empty($opt['clearable'])): ?>
					<button type="button" class="btn btn--sm" data-clear="<?= e($name) ?>">Təmizlə</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php if (!empty($opt['hint'])): ?><span class="field__hint"><?= $opt['hint'] ?></span><?php endif; ?>
	</div>
	<?php
}

/** Yadda saxla / Sil düymələri */
function f_actions(string $backHref, ?string $deleteHref = null): void
{
    ?>
	<div class="actions actions--split">
		<div class="actions">
			<button class="btn btn--primary" type="submit">Yadda saxla</button>
			<a class="btn" href="<?= e($backHref) ?>">Ləğv et</a>
		</div>
<?php if ($deleteHref !== null): ?>
		<a class="btn btn--danger" href="<?= e($deleteHref) ?>" data-confirm="Silmək istədiyinizə əminsiniz?">Sil</a>
<?php endif; ?>
	</div>
	<?php
}
