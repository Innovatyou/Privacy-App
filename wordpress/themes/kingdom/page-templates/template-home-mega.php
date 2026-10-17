<?php
/**
 * Template Name: Home — Mega Church
 * Template Post Type: page
 *
 * Full-screen hero with service strip, stats, ministries, leadership, partnership, giving.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'mega' ) );
kingdom_section( 'welcome' );
kingdom_section( 'stats' );
kingdom_section( 'sermons', array( 'layout' => 'featured' ) );
kingdom_section( 'ministries', array( 'count' => 9 ) );
kingdom_section( 'countdown' );
kingdom_section( 'events' );
kingdom_section( 'leadership' );
kingdom_section( 'partnership' );
kingdom_section( 'testimonies' );
kingdom_section( 'give' );
kingdom_section( 'page-content' );
kingdom_section( 'blog' );
kingdom_section( 'contact' );
get_footer();
