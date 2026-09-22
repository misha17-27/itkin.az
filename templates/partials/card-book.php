<?php
/**
 * Kitab kartı / book card (Elementor loop-item #906)
 * $post — göstəriləcək yazı; $card_extra — əlavə CSS sinfi (karuseldə "swiper-slide").
 */
$card_extra = $card_extra ?? '';
$card_cats  = '';
?>
<div data-elementor-type="loop-item" data-elementor-id="906" class="elementor elementor-906 e-loop-item<?= $card_extra ?> e-loop-item-<?= $post['id'] ?> post-<?= $post['id'] ?> kitabxana-blog type-kitabxana-blog status-publish has-post-thumbnail hentry" data-elementor-post-type="elementor_library"<?= $card_extra !== '' ? ' role="group" aria-roledescription="slide"' : '' ?> data-custom-edit-handle="1">
					<div class="elementor-section-wrap">
						<a class="elementor-element elementor-element-e7841ca e-flex e-con-boxed e-con e-parent" data-id="e7841ca" data-element_type="container" href="<?= url('kitabxana-blog/' . $post['slug']) ?>">
					<div class="e-con-inner">
				<div class="elementor-element elementor-element-d3e7d92 elementor-widget elementor-widget-theme-post-featured-image elementor-widget-image" data-id="d3e7d92" data-element_type="widget" data-widget_type="theme-post-featured-image.default">
				<div class="elementor-widget-container">
													<?php $t = $post['card_thumb']; ?><img decoding="async" width="<?= e($t['width']) ?>" height="<?= e($t['height']) ?>" src="<?= asset($t['url']) ?>" class="<?= e($t['class']) ?>" alt="<?= e($t['alt']) ?>"<?php if ($t['srcset']): ?> srcset="<?= e(srcset_urls($t['srcset'])) ?>"<?php endif; ?><?php if ($t['sizes']): ?> sizes="<?= e($t['sizes']) ?>"<?php endif; ?> />													</div>
				</div>
				<div class="elementor-element elementor-element-fc52d96 elementor-widget elementor-widget-theme-post-title elementor-page-title elementor-widget-heading" data-id="fc52d96" data-element_type="widget" data-widget_type="theme-post-title.default">
				<div class="elementor-widget-container">
			<style>/*! elementor - v3.21.0 - 26-05-2024 */
.elementor-heading-title{padding:0;margin:0;line-height:1}.elementor-widget-heading .elementor-heading-title[class*=elementor-size-]>a{color:inherit;font-size:inherit;line-height:inherit}.elementor-widget-heading .elementor-heading-title.elementor-size-small{font-size:15px}.elementor-widget-heading .elementor-heading-title.elementor-size-medium{font-size:19px}.elementor-widget-heading .elementor-heading-title.elementor-size-large{font-size:29px}.elementor-widget-heading .elementor-heading-title.elementor-size-xl{font-size:39px}.elementor-widget-heading .elementor-heading-title.elementor-size-xxl{font-size:59px}</style><h1 class="elementor-heading-title elementor-size-default"><?= e($post['title']) ?></h1>		</div>
				</div>
				<div class="elementor-element elementor-element-f9e32a8 elementor-widget elementor-widget-theme-post-excerpt" data-id="f9e32a8" data-element_type="widget" data-widget_type="theme-post-excerpt.default">
				<div class="elementor-widget-container">
			<?= e($post['excerpt']) ?>		</div>
				</div>
					</div>
				</a>
							</div>
				</div>
