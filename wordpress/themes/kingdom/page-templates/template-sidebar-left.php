<?php
/**
 * Template Name: Sidebar Left
 * Template Post Type: page
 *
 * Page with the widget sidebar on the left.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
?>
<div class="k-container k-layout has-sidebar k-layout--sidebar-left k-section">
	<article <?php post_class( 'k-entry k-layout__main' ); ?>>
		<div class="k-entry__content">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</article>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
