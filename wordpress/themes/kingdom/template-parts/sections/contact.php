<?php
/**
 * Contact details, form and map.
 *
 * @package Kingdom
 */

$kingdom_map  = get_theme_mod( 'kingdom_map' );
$kingdom_form = get_theme_mod( 'kingdom_contact_form' );
$kingdom_times = kingdom_service_times();
?>
<section class="k-section k-contact">
	<div class="k-container k-split k-split--top">
		<div class="k-split__content" data-reveal>
			<span class="k-eyebrow"><?php esc_html_e( 'Get in touch', 'kingdom' ); ?></span>
			<h2 class="k-section__title"><?php esc_html_e( 'We would love to hear from you', 'kingdom' ); ?></h2>
			<ul class="k-facts">
				<?php if ( get_theme_mod( 'kingdom_address' ) ) : ?><li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Address', 'kingdom' ); ?></small><?php echo nl2br( esc_html( get_theme_mod( 'kingdom_address' ) ) ); ?></span></li><?php endif; ?>
				<?php if ( get_theme_mod( 'kingdom_phone' ) ) : ?><li><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Phone', 'kingdom' ); ?></small><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', get_theme_mod( 'kingdom_phone' ) ) ); ?>"><?php echo esc_html( get_theme_mod( 'kingdom_phone' ) ); ?></a></span></li><?php endif; ?>
				<?php if ( get_theme_mod( 'kingdom_email' ) ) : ?><li><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Email', 'kingdom' ); ?></small><a href="mailto:<?php echo esc_attr( get_theme_mod( 'kingdom_email' ) ); ?>"><?php echo esc_html( get_theme_mod( 'kingdom_email' ) ); ?></a></span></li><?php endif; ?>
				<?php if ( $kingdom_times ) : ?><li><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Services', 'kingdom' ); ?></small><?php echo esc_html( implode( ' · ', array_map( function ( $s ) { return $s['day'] . ' ' . $s['time']; }, array_slice( $kingdom_times, 0, 3 ) ) ) ); ?></span></li><?php endif; ?>
			</ul>
			<?php if ( $kingdom_form ) : ?>
				<div class="k-contact__form"><?php echo do_shortcode( $kingdom_form ); ?></div>
			<?php endif; ?>
		</div>
		<div class="k-map" data-reveal>
			<?php if ( $kingdom_map ) : ?>
				<iframe src="<?php echo esc_url( $kingdom_map ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php esc_attr_e( 'Map', 'kingdom' ); ?>" allowfullscreen></iframe>
			<?php else : ?>
				<div class="k-placeholder"><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small><?php esc_html_e( 'Add a Google Maps embed URL in the Customizer', 'kingdom' ); ?></small></div>
			<?php endif; ?>
		</div>
	</div>
</section>
