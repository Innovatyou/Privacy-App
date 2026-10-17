<?php
/**
 * Template Name: Home — Minimal
 * Template Post Type: page
 *
 * Typographic hero and a calm, content-first layout.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'minimal' ) );
kingdom_section( 'service-times' );
kingdom_section( 'page-content' );
kingdom_section( 'sermons' );
kingdom_section( 'events', array( 'layout' => 'list' ) );
kingdom_section( 'cta' );
get_footer();
