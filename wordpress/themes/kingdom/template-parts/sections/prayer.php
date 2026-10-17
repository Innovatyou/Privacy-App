<?php
/**
 * Prayer request.
 *
 * @package Kingdom
 */

?>
<section class="k-section k-prayer">
	<div class="k-container k-split k-split--center">
		<div class="k-split__content" data-reveal>
			<span class="k-eyebrow"><?php esc_html_e( 'Prayer', 'kingdom' ); ?></span>
			<h2 class="k-section__title"><?php esc_html_e( 'How can we pray for you?', 'kingdom' ); ?></h2>
			<p class="k-lead"><?php esc_html_e( 'Our prayer team meets every week to pray over every request. You are not alone.', 'kingdom' ); ?></p>
			<blockquote class="kcs-verse"><p>“<?php esc_html_e( 'Cast all your anxiety on him because he cares for you.', 'kingdom' ); ?>”</p><cite>1 Peter 5:7</cite></blockquote>
		</div>
		<div data-reveal><?php echo kingdom_shortcode( '[kcs_prayer_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</section>
