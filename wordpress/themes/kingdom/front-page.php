<?php
/**
 * Front page.
 *
 * A static front page that uses one of the page templates renders that template.
 * Otherwise the sections chosen in Customizer → Homepage Sections are rendered.
 *
 * @package Kingdom
 */

if ( 'page' === get_option( 'show_on_front' ) ) {
	$kingdom_template = get_page_template_slug( get_queried_object_id() );
	if ( $kingdom_template && locate_template( $kingdom_template ) ) {
		include locate_template( $kingdom_template );
		return;
	}
}

get_header();

$kingdom_sections = array_filter( array_map( 'trim', explode( ',', get_theme_mod( 'kingdom_home_sections', 'hero, service-times, welcome, live, sermons, ministries, events, stats, partnership, testimonies, give, newsletter' ) ) ) );
$kingdom_valid    = kingdom_available_sections();
foreach ( $kingdom_sections as $kingdom_section ) {
	if ( isset( $kingdom_valid[ $kingdom_section ] ) ) {
		kingdom_section( $kingdom_section );
	}
}

if ( 'page' === get_option( 'show_on_front' ) ) {
	kingdom_section( 'page-content' );
}

get_footer();
