<?php
/**
 * Ministries.
 *
 * Args: count.
 *
 * @package Kingdom
 */

if ( ! post_type_exists( 'kcs_ministry' ) ) {
	return;
}
$kingdom_args = wp_parse_args( $args ?? array(), array( 'count' => 6 ) );
$kingdom_q    = new WP_Query( array( 'post_type' => 'kcs_ministry', 'posts_per_page' => (int) $kingdom_args['count'], 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'no_found_rows' => true ) );
if ( ! $kingdom_q->have_posts() ) {
	return;
}
?>
<section class="k-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Ministries', 'kingdom' ), get_theme_mod( 'kingdom_ministries_title', __( 'Find your place', 'kingdom' ) ), __( 'There is a place for everyone to grow, serve and belong.', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--3">
			<?php
			while ( $kingdom_q->have_posts() ) :
				$kingdom_q->the_post();
				get_template_part( 'template-parts/content/ministry' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<p class="k-center k-mt"><a class="k-btn k-btn--outline" href="<?php echo esc_url( get_post_type_archive_link( 'kcs_ministry' ) ); ?>"><?php esc_html_e( 'Explore all ministries', 'kingdom' ); ?></a></p>
	</div>
</section>
