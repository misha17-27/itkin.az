<?php
/**
 * Xəbər kartı / post card (Elementor loop-item #214)
 * $post — göstəriləcək yazı; $card_extra — əlavə CSS sinfi (karuseldə "swiper-slide").
 */
$card_extra = $card_extra ?? '';
$card_cats  = cat_classes($post);
?>
<style id="loop-dynamic-214">.e-loop-item-<?= $post['id'] ?> .elementor-element.elementor-element-5c2c833:not(.elementor-motion-effects-element-type-background), .e-loop-item-<?= $post['id'] ?> .elementor-element.elementor-element-5c2c833 > .elementor-motion-effects-container > .elementor-motion-effects-layer{background-image:url("<?= asset($post['thumb']['url']) ?>");}</style>
<div data-elementor-type="loop-item" data-elementor-id="214" class="elementor elementor-214 e-loop-item<?= $card_extra ?> e-loop-item-<?= $post['id'] ?> post-<?= $post['id'] ?> post type-post status-publish format-standard has-post-thumbnail hentry <?= e($card_cats) ?>" data-elementor-post-type="elementor_library"<?= $card_extra !== '' ? ' role="group" aria-roledescription="slide"' : '' ?> data-custom-edit-handle="1">
					<div class="elementor-section-wrap">
						<a class="elementor-element elementor-element-5c2c833 e-flex e-con-boxed e-con e-parent" data-id="5c2c833" data-element_type="container" data-settings="{&quot;background_background&quot;:&quot;classic&quot;}" href="<?= url($post['slug']) ?>">
					<div class="e-con-inner">
				<div class="elementor-element elementor-element-aef03d3 elementor-widget elementor-widget-theme-post-title elementor-page-title elementor-widget-heading" data-id="aef03d3" data-element_type="widget" data-widget_type="theme-post-title.default">
				<div class="elementor-widget-container">
			<style>/*! elementor - v3.21.0 - 26-05-2024 */
.elementor-heading-title{padding:0;margin:0;line-height:1}.elementor-widget-heading .elementor-heading-title[class*=elementor-size-]>a{color:inherit;font-size:inherit;line-height:inherit}.elementor-widget-heading .elementor-heading-title.elementor-size-small{font-size:15px}.elementor-widget-heading .elementor-heading-title.elementor-size-medium{font-size:19px}.elementor-widget-heading .elementor-heading-title.elementor-size-large{font-size:29px}.elementor-widget-heading .elementor-heading-title.elementor-size-xl{font-size:39px}.elementor-widget-heading .elementor-heading-title.elementor-size-xxl{font-size:59px}</style><h1 class="elementor-heading-title elementor-size-default"><?= e($post['title']) ?></h1>		</div>
				</div>
					</div>
				</a>
							</div>
				</div>
