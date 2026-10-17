<?php
/**
 * Countdown banner.
 *
 * @package Kingdom
 */

if ( ! kingdom_plugin_active() ) {
	return;
}
?>
<section class="k-countdown-band">
	<div class="k-container k-countdown-band__inner">
		<div>
			<span class="k-eyebrow k-eyebrow--light"><?php esc_html_e( 'Next service starts in', 'kingdom' ); ?></span>
		</div>
		<?php echo do_shortcode( '[kcs_service_countdown]' ); ?>
		<a class="k-btn k-btn--light" href="<?php echo esc_url( kcs_page_url( 'watch_live' ) ); ?>"><?php esc_html_e( 'Watch online', 'kingdom' ); ?></a>
	</div>
</section>
