<?php
/**
 * Template Name: Give Online
 * Template Post Type: page
 *
 * Giving form with all enabled payment gateways.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
if ( kingdom_content_has( 'kcs_give' ) ) {
	kingdom_section( 'page-content' );
} else {
	kingdom_section( 'give' );
	kingdom_section( 'page-content' );
}
?>
<section class="k-section k-section--alt">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Other ways to give', 'kingdom' ), __( 'Give the way that suits you', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--3">
			<div class="k-feature" data-reveal><span class="k-feature__icon"><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Tithe & offering', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Card, bank transfer, USSD, mobile money or PayPal — in US dollars or your local currency.', 'kingdom' ); ?></p><?php if ( kingdom_plugin_active() ) : ?><a class="k-link" href="<?php echo esc_url( kcs_page_url( 'tithe' ) ); ?>"><?php esc_html_e( 'Give your tithe', 'kingdom' ); ?> →</a><?php endif; ?></div>
			<div class="k-feature" data-reveal><span class="k-feature__icon"><?php echo kingdom_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Become a partner', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Commit to a monthly seed and receive partner updates.', 'kingdom' ); ?></p><?php if ( kingdom_plugin_active() ) : ?><a class="k-link" href="<?php echo esc_url( kcs_page_url( 'partnership' ) ); ?>"><?php esc_html_e( 'Learn more', 'kingdom' ); ?> →</a><?php endif; ?></div>
			<div class="k-feature" data-reveal><span class="k-feature__icon"><?php echo kingdom_icon( 'hands' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'In person', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Give during any of our services using the offering envelopes.', 'kingdom' ); ?></p></div>
		</div>
	</div>
</section>
<?php
get_footer();
