<?php
/**
 * Call to action band.
 *
 * Args: title, text, button, url.
 *
 * @package Kingdom
 */

$kingdom_args = wp_parse_args(
	$args ?? array(),
	array(
		'title'  => get_theme_mod( 'kingdom_cta_title', __( 'New here? We saved you a seat.', 'kingdom' ) ),
		'text'   => get_theme_mod( 'kingdom_cta_text', __( 'Let us know you are coming and we will have someone ready to welcome you.', 'kingdom' ) ),
		'button' => get_theme_mod( 'kingdom_cta_btn_text', __( 'Plan your visit', 'kingdom' ) ),
		'url'    => kingdom_url( 'kingdom_cta_btn_url', 'template-plan-visit.php' ),
	)
);
?>
<section class="k-cta">
	<div class="k-container k-cta__inner" data-reveal>
		<div>
			<h2><?php echo esc_html( $kingdom_args['title'] ); ?></h2>
			<p><?php echo esc_html( $kingdom_args['text'] ); ?></p>
		</div>
		<?php if ( $kingdom_args['button'] ) : ?>
			<a class="k-btn k-btn--light k-btn--lg" href="<?php echo esc_url( $kingdom_args['url'] ); ?>"><?php echo esc_html( $kingdom_args['button'] ); ?></a>
		<?php endif; ?>
	</div>
</section>
