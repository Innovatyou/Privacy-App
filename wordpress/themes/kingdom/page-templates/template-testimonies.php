<?php
/**
 * Template Name: Testimonies
 * Template Post Type: page
 *
 * All testimonies.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
kingdom_section( 'page-content' );
kingdom_section( 'testimonies', array( 'count' => 24 ) );
kingdom_section( 'cta', array( 'title' => __( 'Have a testimony?', 'kingdom' ), 'text' => __( 'Share what God has done in your life and encourage someone today.', 'kingdom' ), 'button' => __( 'Share yours', 'kingdom' ), 'url' => kingdom_url( '', 'template-contact.php' ) ) );
get_footer();
