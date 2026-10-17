<?php
/**
 * Template Name: Prayer Request
 * Template Post Type: page
 *
 * Prayer form and public prayer wall.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
if ( kingdom_content_has( 'kcs_prayer_form' ) ) {
	kingdom_section( 'page-content' );
} else {
	kingdom_section( 'prayer' );
	kingdom_section( 'page-content' );
	echo '<div class="k-container k-section">' . kingdom_shortcode( '[kcs_prayer_wall]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
get_footer();
