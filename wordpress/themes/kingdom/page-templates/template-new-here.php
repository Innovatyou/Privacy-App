<?php
/**
 * Template Name: I'm New / Connect
 * Template Post Type: page
 *
 * First-time guest journey: welcome, visit, ministries, next steps.
 *
 * @package Kingdom
 */

get_header();
kingdom_section( 'hero', array( 'variant' => 'minimal', 'eyebrow' => __( "I'm new", 'kingdom' ), 'title' => get_the_title(), 'text' => has_excerpt() ? get_the_excerpt() : __( 'Welcome! Here is how to take your next step with us.', 'kingdom' ) ) );
kingdom_section( 'welcome' );
kingdom_section( 'visit' );
kingdom_section( 'page-content' );
kingdom_section( 'ministries', array( 'count' => 3 ) );
kingdom_section( 'newsletter' );
get_footer();
