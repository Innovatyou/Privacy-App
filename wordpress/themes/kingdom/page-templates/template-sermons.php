<?php
/**
 * Template Name: Sermons
 * Template Post Type: page
 *
 * Featured latest sermon, filterable sermon library and podcast links.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
kingdom_section( 'page-content' );
if ( post_type_exists( 'kcs_sermon' ) ) :
	$kingdom_paged = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
	$kingdom_q     = new WP_Query( array( 'post_type' => 'kcs_sermon', 'posts_per_page' => 9, 'paged' => $kingdom_paged ) );
	?>
	<section class="k-section">
		<div class="k-container">
			<?php get_template_part( 'template-parts/sermon-filters' ); ?>
			<div class="k-grid k-grid--3">
				<?php
				while ( $kingdom_q->have_posts() ) :
					$kingdom_q->the_post();
					get_template_part( 'template-parts/content/sermon' );
				endwhile;
				?>
			</div>
			<nav class="k-pagination">
				<?php
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'total'   => $kingdom_q->max_num_pages,
						'current' => $kingdom_paged,
					)
				);
				wp_reset_postdata();
				?>
			</nav>
		</div>
	</section>
	<?php
else :
	echo '<div class="k-container k-section">' . kingdom_shortcode( '[kcs_sermons]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
endif;
get_footer();
