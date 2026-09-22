<?php
/**
 * Yol göstəricisi / breadcrumbs (Jet Blocks).
 * $crumbs — [['label' => ..., 'href' => null|yol, 'rel' => 'tag'], ...] sonuncu bənd cari səhifədir.
 */
?>
<div class="elementor-jet-breadcrumbs jet-blocks"><div class="jet-breadcrumbs"><div class="jet-breadcrumbs__content"><div class="jet-breadcrumbs__wrap"><div class="jet-breadcrumbs__item"><a href="<?= url() ?>" class="jet-breadcrumbs__item-link is-home" rel="home" title="Ana səhifə">Ana səhifə</a></div>
<?php foreach ($crumbs as $crumb): ?>
<div class="jet-breadcrumbs__item"><div class="jet-breadcrumbs__item-sep"><span class="jet-blocks-icon"><svg aria-hidden="true" class="e-font-icon-svg e-fas-angle-right" viewBox="0 0 256 512" xmlns="http://www.w3.org/2000/svg"><path d="M224.3 273l-136 136c-9.4 9.4-24.6 9.4-33.9 0l-22.6-22.6c-9.4-9.4-9.4-24.6 0-33.9l96.4-96.4-96.4-96.4c-9.4-9.4-9.4-24.6 0-33.9L54.3 103c9.4-9.4 24.6-9.4 33.9 0l136 136c9.5 9.4 9.5 24.6.1 34z"></path></svg></span></div></div>
<div class="jet-breadcrumbs__item"><?php if (!empty($crumb['href'])): ?><a href="<?= e($crumb['href']) ?>" class="jet-breadcrumbs__item-link" rel="<?= e($crumb['rel'] ?? 'tag') ?>" title="<?= e($crumb['label']) ?>"><?= e($crumb['label']) ?></a><?php else: ?><span class="jet-breadcrumbs__item-target"><?= e($crumb['label']) ?></span><?php endif; ?></div>
<?php endforeach; ?>
</div></div></div></div>
