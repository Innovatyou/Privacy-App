<?php
/**
 * Blog / fallback template.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
?>
<div class="k-container k-layout<?php echo is_active_sidebar( 'sidebar-1' ) ? ' has-sidebar' : ''; ?>">
	<div class="k-layout__main">
		<?php if ( have_posts() ) : ?>
			<div class="k-grid k-grid--2">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content/card' );
				endwhile;
				?>
			</div>
			<?php kingdom_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
