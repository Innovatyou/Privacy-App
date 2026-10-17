<?php
/**
 * Stats counter.
 *
 * @package Kingdom
 */

$kingdom_lines = array_filter( array_map( 'trim', explode( "\n", get_theme_mod( 'kingdom_stats', "25|Years of ministry\n3000|Members\n40|Ministries\n12|Nations reached" ) ) ) );
if ( ! $kingdom_lines ) {
	return;
}
$kingdom_bg = get_theme_mod( 'kingdom_stats_image' );
?>
<section class="k-stats<?php echo $kingdom_bg ? ' has-image' : ''; ?>"<?php echo $kingdom_bg ? ' style="--k-img:url(' . esc_url( $kingdom_bg ) . ')"' : ''; ?>>
	<div class="k-container k-stats__grid">
		<?php foreach ( $kingdom_lines as $kingdom_line ) : ?>
			<?php $kingdom_p = array_map( 'trim', explode( '|', $kingdom_line ) ); ?>
			<div class="k-stat" data-reveal>
				<strong data-count="<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $kingdom_p[0] ) ); ?>"><?php echo esc_html( $kingdom_p[0] ); ?></strong><span class="k-stat__plus">+</span>
				<small><?php echo esc_html( $kingdom_p[1] ?? '' ); ?></small>
			</div>
		<?php endforeach; ?>
	</div>
</section>
