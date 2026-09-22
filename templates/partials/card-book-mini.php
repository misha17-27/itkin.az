<?php
/**
 * Kitab kartı (kiçik) / compact book card (Elementor loop-item #942)
 * Tək kitab səhifəsindəki karuseldə istifadə olunur — yalnız üz qabığı.
 */
$card_extra = $card_extra ?? '';
?>
<div data-elementor-type="loop-item" data-elementor-id="942" class="elementor elementor-942 e-loop-item<?= $card_extra ?> e-loop-item-<?= $post['id'] ?> post-<?= $post['id'] ?> kitabxana-blog type-kitabxana-blog status-publish has-post-thumbnail hentry" data-elementor-post-type="elementor_library"<?= $card_extra !== '' ? ' role="group" aria-roledescription="slide"' : '' ?> data-custom-edit-handle="1">
					<div class="elementor-section-wrap">
						<div class="elementor-element elementor-element-91b697d e-flex e-con-boxed e-con e-parent" data-id="91b697d" data-element_type="container">
					<div class="e-con-inner">
				<div class="elementor-element elementor-element-791a4a6 elementor-widget elementor-widget-theme-post-featured-image elementor-widget-image" data-id="791a4a6" data-element_type="widget" data-widget_type="theme-post-featured-image.default">
				<div class="elementor-widget-container">
													<?php $t = $post['card_thumb']; ?><img width="<?= e($t['width']) ?>" height="<?= e($t['height']) ?>" src="<?= asset($t['url']) ?>" class="<?= e($t['class']) ?>" alt="<?= e($t['alt']) ?>"<?php if ($t['srcset']): ?> srcset="<?= e(srcset_urls($t['srcset'])) ?>"<?php endif; ?><?php if ($t['sizes']): ?> sizes="<?= e($t['sizes']) ?>"<?php endif; ?> />													</div>
				</div>
					</div>
				</div>
							</div>
				</div>
