<?php
/**
 * Template Name: What We Believe
 * Template Post Type: page
 *
 * Statement of faith — write each belief as an H3 in the editor; it renders as cards.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
?>
<section class="k-section">
	<div class="k-container k-container--narrow">
		<div class="k-entry__content k-beliefs">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</div>
</section>
<?php
kingdom_section( 'cta' );
get_footer();
