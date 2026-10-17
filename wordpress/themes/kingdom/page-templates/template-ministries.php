<?php
/**
 * Template Name: Ministries
 * Template Post Type: page
 *
 * All ministries in a card grid.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
kingdom_section( 'page-content' );
kingdom_section( 'ministries', array( 'count' => -1 ) );
kingdom_section( 'cta', array( 'title' => __( 'Ready to serve?', 'kingdom' ), 'text' => __( 'Use your gifts to make a difference. We will help you find the right team.', 'kingdom' ), 'button' => __( 'Get connected', 'kingdom' ), 'url' => kingdom_url( '', 'template-contact.php' ) ) );
get_footer();
