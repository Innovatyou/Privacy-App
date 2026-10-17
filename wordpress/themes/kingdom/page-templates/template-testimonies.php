<?php
/**
 * Template Name: Testimonies
 * Template Post Type: page
 *
 * All testimonies.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
kingdom_section( 'page-content' );
kingdom_section( 'testimonies', array( 'count' => 24 ) );
if ( kingdom_plugin_active() && ! kingdom_content_has( 'kcs_testimony_form' ) ) {
	echo '<section class="k-section k-section--alt" id="share"><div class="k-container k-container--narrow">' . do_shortcode( '[kcs_testimony_form]' ) . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	kingdom_section( 'cta', array( 'title' => __( 'Have a testimony?', 'kingdom' ), 'text' => __( 'Share what God has done in your life and encourage someone today.', 'kingdom' ), 'button' => __( 'Share yours', 'kingdom' ), 'url' => kingdom_url( '', 'template-contact.php' ) ) );
}
get_footer();
