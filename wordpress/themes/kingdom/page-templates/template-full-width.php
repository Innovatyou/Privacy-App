<?php
/**
 * Template Name: Full Width
 * Template Post Type: page
 *
 * Page content edge to edge (great with the block editor).
 *
 * @package Kingdom
 */

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'k-entry k-entry--full' ); ?>>
		<div class="k-entry__content k-entry__content--full"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;
get_footer();
