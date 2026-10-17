<?php
/**
 * Giving section.
 *
 * Args: purpose.
 *
 * @package Kingdom
 */

$kingdom_args = wp_parse_args( $args ?? array(), array( 'purpose' => '' ) );
?>
<section class="k-section k-give">
	<div class="k-container k-split k-split--center">
		<div class="k-split__content" data-reveal>
			<span class="k-eyebrow"><?php esc_html_e( 'Giving', 'kingdom' ); ?></span>
			<h2 class="k-section__title"><?php echo esc_html( get_theme_mod( 'kingdom_give_title', __( 'Give generously', 'kingdom' ) ) ); ?></h2>
			<p class="k-lead"><?php echo esc_html( get_theme_mod( 'kingdom_give_text', __( 'Give your tithes and offerings securely online with card, bank transfer or mobile money.', 'kingdom' ) ) ); ?></p>
			<ul class="k-checklist">
				<li><?php esc_html_e( 'Secure, encrypted payments', 'kingdom' ); ?></li>
				<li><?php esc_html_e( 'Instant email receipt', 'kingdom' ); ?></li>
				<li><?php esc_html_e( 'Cards, bank transfer, USSD, mobile money & PayPal', 'kingdom' ); ?></li>
			</ul>
			<blockquote class="kcs-verse"><p>“<?php esc_html_e( 'Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver.', 'kingdom' ); ?>”</p><cite>2 Corinthians 9:7</cite></blockquote>
		</div>
		<div data-reveal><?php echo kingdom_shortcode( '[kcs_give purpose="' . esc_attr( $kingdom_args['purpose'] ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</section>
