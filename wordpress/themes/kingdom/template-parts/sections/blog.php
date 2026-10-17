<?php
/**
 * Latest news.
 *
 * @package Kingdom
 */

$kingdom_q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
if ( ! $kingdom_q->have_posts() ) {
	return;
}
?>
<section class="k-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'News', 'kingdom' ), __( 'From the blog', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--3">
			<?php
			while ( $kingdom_q->have_posts() ) :
				$kingdom_q->the_post();
				get_template_part( 'template-parts/content/card' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
