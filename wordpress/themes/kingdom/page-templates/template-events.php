<?php
/**
 * Template Name: Events
 * Template Post Type: page
 *
 * Upcoming events as cards plus a compact list.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
kingdom_section( 'page-content' );
kingdom_section( 'events', array( 'count' => 12 ) );
if ( post_type_exists( 'kcs_event' ) ) {
	echo '<p class="k-center k-mb"><a class="k-btn k-btn--outline" href="' . esc_url( add_query_arg( 'past', 1, get_post_type_archive_link( 'kcs_event' ) ) ) . '">' . esc_html__( 'View past events', 'kingdom' ) . '</a></p>';
}
get_footer();
