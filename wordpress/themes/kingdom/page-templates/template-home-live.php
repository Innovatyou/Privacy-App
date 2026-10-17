<?php
/**
 * Template Name: Home — Live Stream First
 * Template Post Type: page
 *
 * Puts the live player and chat at the top — ideal for online-first churches.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'live', 'title' => get_theme_mod( 'kingdom_hero_title', __( 'Church online, every week', 'kingdom' ) ) ) );
kingdom_section( 'service-times' );
kingdom_section( 'sermons' );
kingdom_section( 'prayer' );
kingdom_section( 'give' );
kingdom_section( 'events', array( 'layout' => 'list' ) );
kingdom_section( 'page-content' );
kingdom_section( 'newsletter' );
get_footer();
