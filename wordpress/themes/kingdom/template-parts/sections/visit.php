<?php
/**
 * Plan your visit — what to expect.
 *
 * @package Kingdom
 */

$kingdom_steps = array(
	array( 'clock', __( 'Arrive early', 'kingdom' ), __( 'Come 15 minutes early, grab a coffee and meet our welcome team at the entrance.', 'kingdom' ) ),
	array( 'car', __( 'Free parking', 'kingdom' ), __( 'Look for the reserved guest parking spots close to the main entrance.', 'kingdom' ) ),
	array( 'child', __( 'Kids church', 'kingdom' ), __( 'Safe, fun, Bible-based programmes for children from birth to 12 years.', 'kingdom' ) ),
	array( 'coffee', __( 'Come as you are', 'kingdom' ), __( 'No dress code — services last about 90 minutes with worship, the Word and prayer.', 'kingdom' ) ),
);
?>
<section class="k-section k-visit">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Plan your visit', 'kingdom' ), __( 'What to expect', 'kingdom' ), __( 'Visiting a church for the first time can feel unfamiliar. Here is everything you need to know.', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--4">
			<?php foreach ( $kingdom_steps as $kingdom_i => $kingdom_s ) : ?>
				<div class="k-feature" data-reveal>
					<span class="k-feature__icon"><?php echo kingdom_icon( $kingdom_s[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><?php echo esc_html( $kingdom_s[1] ); ?></h3>
					<p><?php echo esc_html( $kingdom_s[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
