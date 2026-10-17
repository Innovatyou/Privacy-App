<?php
/**
 * Template Name: Watch Live
 * Template Post Type: page
 *
 * Live player with chat, countdown, service times, giving and prayer.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
?>
<section class="k-section k-section--tight">
	<div class="k-container">
		<?php kingdom_feature( 'kcs_livestream', '[kcs_livestream chat="yes"]' ); ?>
		<?php kingdom_section( 'page-content', array( 'narrow' => false ) ); ?>
	</div>
</section>
<?php
kingdom_section( 'countdown' );
kingdom_section( 'sermons', array( 'title' => __( 'Catch up on recent messages', 'kingdom' ) ) );
kingdom_section( 'prayer' );
get_footer();
