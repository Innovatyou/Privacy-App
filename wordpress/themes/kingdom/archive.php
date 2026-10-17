<?php
/**
 * Archives (posts, categories, tags, ministries, leaders, testimonies).
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
$kingdom_type = get_post_type() ? get_post_type() : 'post';
$kingdom_cols = in_array( $kingdom_type, array( 'kcs_leader' ), true ) ? 4 : 3;
?>
<div class="k-container k-section">
	<?php if ( have_posts() ) : ?>
		<div class="k-grid k-grid--<?php echo (int) $kingdom_cols; ?>">
			<?php
			while ( have_posts() ) :
				the_post();
				if ( 'kcs_leader' === $kingdom_type ) {
					get_template_part( 'template-parts/content/leader' );
				} elseif ( 'kcs_ministry' === $kingdom_type ) {
					get_template_part( 'template-parts/content/ministry' );
				} elseif ( 'kcs_testimony' === $kingdom_type ) {
					get_template_part( 'template-parts/content/testimony' );
				} else {
					get_template_part( 'template-parts/content/card' );
				}
			endwhile;
			?>
		</div>
		<?php kingdom_pagination(); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content/none' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
