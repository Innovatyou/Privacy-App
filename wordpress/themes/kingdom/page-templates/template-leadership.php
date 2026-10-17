<?php
/**
 * Template Name: Leadership
 * Template Post Type: page
 *
 * Grid of all leaders (Church Suite → Leadership).
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
kingdom_section( 'page-content' );
kingdom_section( 'leadership', array( 'count' => -1 ) );
get_footer();
