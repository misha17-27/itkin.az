<?php
/**
 * İtkin şəxs kartı / missing person card (Elementor loop-item #150)
 * $post — data/itkinlr.php-dən bir sətir.
 */
?>
<div data-elementor-type="loop-item" data-elementor-id="150" class="elementor elementor-150 swiper-slide e-loop-item e-loop-item-<?= $post['id'] ?> post-<?= $post['id'] ?> itkinlr type-itkinlr status-publish has-post-thumbnail hentry" data-elementor-post-type="elementor_library" role="group" aria-roledescription="slide" data-custom-edit-handle="1">
					<div class="elementor-section-wrap">
						<div class="elementor-element elementor-element-5327ab8 e-flex e-con-boxed e-con e-parent" data-id="5327ab8" data-element_type="container" data-settings="{&quot;background_background&quot;:&quot;classic&quot;}">
					<div class="e-con-inner">
				<div class="elementor-element elementor-element-dcecb90 elementor-widget elementor-widget-theme-post-featured-image elementor-widget-image" data-id="dcecb90" data-element_type="widget" data-widget_type="theme-post-featured-image.default">
				<div class="elementor-widget-container">
													<?php $t = $post['thumb']; ?><img decoding="async" width="<?= e($t['width']) ?>" height="<?= e($t['height']) ?>" src="<?= asset($t['url']) ?>" class="<?= e($t['home_class'] ?? $t['class']) ?>" alt="<?= e($t['alt']) ?>"<?php if ($t['srcset']): ?> srcset="<?= e(srcset_urls($t['srcset'])) ?>"<?php endif; ?><?php if ($t['sizes']): ?> sizes="<?= e($t['sizes']) ?>"<?php endif; ?> />													</div>
				</div>
				<div class="elementor-element elementor-element-cb02d9a elementor-widget elementor-widget-theme-post-title elementor-page-title elementor-widget-heading" data-id="cb02d9a" data-element_type="widget" data-widget_type="theme-post-title.default">
				<div class="elementor-widget-container">
			<h1 class="elementor-heading-title elementor-size-default"><?= e($post['title']) ?></h1>		</div>
				</div>
					</div>
				</div>
							</div>
				</div>
