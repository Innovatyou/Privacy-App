<?php
/**
 * Live stream section.
 *
 * @package Kingdom
 */

?>
<section class="k-section k-section--dark k-live-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Church online', 'kingdom' ), __( 'Worship with us live', 'kingdom' ), __( 'Can’t make it in person? Join our services live from anywhere in the world.', 'kingdom' ) ); ?>
		<div data-reveal><?php echo kingdom_shortcode( '[kcs_livestream chat="no" autoplay="no"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</section>
