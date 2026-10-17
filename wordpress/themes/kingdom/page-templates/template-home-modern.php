<?php
/**
 * Template Name: Home — Modern Video
 * Template Post Type: page
 *
 * Background video hero with countdown, live stream, featured sermon, give.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'video' ) );
kingdom_section( 'welcome' );
kingdom_section( 'live' );
kingdom_section( 'sermons', array( 'layout' => 'featured' ) );
kingdom_section( 'events', array( 'layout' => 'list', 'count' => 4 ) );
kingdom_section( 'verse' );
kingdom_section( 'ministries' );
kingdom_section( 'give' );
kingdom_section( 'testimonies' );
kingdom_section( 'page-content' );
kingdom_section( 'newsletter' );
get_footer();
