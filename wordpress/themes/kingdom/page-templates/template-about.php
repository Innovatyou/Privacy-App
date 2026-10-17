<?php
/**
 * Template Name: About Us
 * Template Post Type: page
 *
 * Story (page content), pastor welcome, stats, leadership and a call to action.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( '', '', has_post_thumbnail() ? '' : kingdom_image( 'kingdom_church_image' ) );
kingdom_section( 'page-content' );
kingdom_section( 'welcome' );
kingdom_section( 'stats' );
kingdom_section( 'gallery' );
kingdom_section( 'leadership' );
kingdom_section( 'testimonies' );
kingdom_section( 'cta' );
get_footer();
