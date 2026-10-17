<?php
/**
 * Template Name: Home — Classic
 * Template Post Type: page
 *
 * Image hero, service times, welcome, sermons, ministries, events, partnership, newsletter.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'classic' ) );
kingdom_section( 'service-times' );
kingdom_section( 'welcome' );
kingdom_section( 'sermons' );
kingdom_section( 'ministries' );
kingdom_section( 'events' );
kingdom_section( 'stats' );
kingdom_section( 'gallery' );
kingdom_section( 'partnership' );
kingdom_section( 'testimonies' );
kingdom_section( 'prayer' );
kingdom_section( 'visit' );
kingdom_section( 'cta' );
kingdom_section( 'social' );
kingdom_section( 'page-content' );
kingdom_section( 'blog' );
get_footer();
