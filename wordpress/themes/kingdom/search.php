<?php
/**
 * Search results.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
?>
<div class="k-container k-container--narrow k-section">
	<?php get_search_form(); ?>
	<?php if ( have_posts() ) : ?>
		<div class="k-search-results">
			<?php
			while ( have_posts() ) :
				the_post();
				$kingdom_obj = get_post_type_object( get_post_type() );
				?>
				<article <?php post_class( 'k-search-item' ); ?>>
					<span class="k-eyebrow"><?php echo esc_html( $kingdom_obj ? $kingdom_obj->labels->singular_name : '' ); ?></span>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
				</article>
			<?php endwhile; ?>
		</div>
		<?php kingdom_pagination(); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content/none' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
