<?php
/**
 * Service times bar.
 *
 * @package Kingdom
 */

$kingdom_times = kingdom_service_times();
if ( ! $kingdom_times ) {
	return;
}
?>
<section class="k-times">
	<div class="k-container k-times__inner">
		<div class="k-times__label"><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <strong><?php esc_html_e( 'Service times', 'kingdom' ); ?></strong></div>
		<?php foreach ( $kingdom_times as $kingdom_s ) : ?>
			<div class="k-times__item"><small><?php echo esc_html( $kingdom_s['name'] ); ?></small><span><?php echo esc_html( $kingdom_s['day'] . ' · ' . $kingdom_s['time'] ); ?></span></div>
		<?php endforeach; ?>
		<?php if ( get_theme_mod( 'kingdom_address' ) ) : ?>
			<div class="k-times__item k-times__item--addr"><small><?php esc_html_e( 'Location', 'kingdom' ); ?></small><span><?php echo esc_html( strtok( get_theme_mod( 'kingdom_address' ), "\n" ) ); ?></span></div>
		<?php endif; ?>
	</div>
</section>
