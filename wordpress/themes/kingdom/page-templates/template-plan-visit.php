<?php
/**
 * Template Name: Plan Your Visit
 * Template Post Type: page
 *
 * What to expect, service times, map and kids info.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( '', __( 'We cannot wait to meet you!', 'kingdom' ) );
kingdom_section( 'visit' );
kingdom_section( 'service-times' );
kingdom_section( 'page-content' );
kingdom_section( 'contact' );
get_footer();
