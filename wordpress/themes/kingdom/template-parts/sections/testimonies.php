<?php
/**
 * Testimonies.
 *
 * Args: count.
 *
 * @package Kingdom
 */

if ( ! post_type_exists( 'kcs_testimony' ) ) {
	return;
}
$kingdom_args = wp_parse_args( $args ?? array(), array( 'count' => 3 ) );
$kingdom_q    = new WP_Query( array( 'post_type' => 'kcs_testimony', 'posts_per_page' => (int) $kingdom_args['count'], 'no_found_rows' => true ) );
if ( ! $kingdom_q->have_posts() ) {
	return;
}
?>
<section class="k-section k-section--alt">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Testimonies', 'kingdom' ), __( 'What God is doing', 'kingdom' ), __( 'Real stories of faith, healing and transformation from our church family.', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--3">
			<?php
			while ( $kingdom_q->have_posts() ) :
				$kingdom_q->the_post();
				get_template_part( 'template-parts/content/testimony' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<p class="k-center k-mt"><a class="k-btn k-btn--outline" href="<?php echo esc_url( kingdom_url( '', 'template-testimonies.php', get_post_type_archive_link( 'kcs_testimony' ) ) . '#share' ); ?>"><?php esc_html_e( 'Share your testimony', 'kingdom' ); ?></a></p>
	</div>
</section>
