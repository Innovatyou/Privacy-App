<?php
/**
 * Sermon archive & sermon taxonomies.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( is_post_type_archive() ? __( 'Sermons', 'kingdom' ) : '' );
?>
<section class="k-section">
	<div class="k-container">
		<?php get_template_part( 'template-parts/sermon-filters' ); ?>
		<?php if ( have_posts() ) : ?>
			<div class="k-grid k-grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content/sermon' );
				endwhile;
				?>
			</div>
			<?php kingdom_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
