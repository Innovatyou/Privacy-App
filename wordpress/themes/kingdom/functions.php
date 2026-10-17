<?php
/**
 * Kingdom Church theme bootstrap.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

define( 'KINGDOM_VERSION', '1.1.0' );
define( 'KINGDOM_DIR', get_template_directory() );
define( 'KINGDOM_URI', get_template_directory_uri() );

require KINGDOM_DIR . '/inc/setup.php';
require KINGDOM_DIR . '/inc/template-tags.php';
require KINGDOM_DIR . '/inc/youtube-live.php';
require KINGDOM_DIR . '/inc/customizer.php';
require KINGDOM_DIR . '/inc/setup-wizard.php';
