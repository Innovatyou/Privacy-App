<?php
/**
 * Newsletter band.
 *
 * @package Kingdom
 */

if ( ! kingdom_plugin_active() ) {
	return;
}
?>
<section class="k-section k-newsletter-band">
	<div class="k-container k-container--narrow k-center" data-reveal>
		<?php echo do_shortcode( '[kcs_newsletter style="inline" title="' . esc_attr__( 'Never miss a moment', 'kingdom' ) . '"]' ); ?>
	</div>
</section>
