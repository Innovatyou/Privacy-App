<?php
/**
 * Template Name: Home — Split Hero
 * Template Post Type: page
 *
 * Text and image side by side, with a floating service-times card.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'split' ) );
kingdom_section( 'visit' );
kingdom_section( 'welcome' );
kingdom_section( 'ministries' );
kingdom_section( 'sermons' );
kingdom_section( 'verse' );
kingdom_section( 'events' );
kingdom_section( 'partnership' );
kingdom_section( 'page-content' );
kingdom_section( 'contact' );
kingdom_section( 'social' );
get_footer();
