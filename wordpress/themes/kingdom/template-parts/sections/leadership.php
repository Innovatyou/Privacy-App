<?php
/**
 * Leadership.
 *
 * Args: count.
 *
 * @package Kingdom
 */

if ( ! post_type_exists( 'kcs_leader' ) ) {
	return;
}
$kingdom_args = wp_parse_args( $args ?? array(), array( 'count' => 4 ) );
$kingdom_q    = new WP_Query( array( 'post_type' => 'kcs_leader', 'posts_per_page' => (int) $kingdom_args['count'], 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'no_found_rows' => true ) );
if ( ! $kingdom_q->have_posts() ) {
	return;
}
?>
<section class="k-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Leadership', 'kingdom' ), __( 'Meet our pastors', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--4">
			<?php
			while ( $kingdom_q->have_posts() ) :
				$kingdom_q->the_post();
				get_template_part( 'template-parts/content/leader' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
