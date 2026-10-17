<?php
/**
 * Plugin Name:       Kingdom Church Suite
 * Plugin URI:        https://example.com/kingdom-church-suite
 * Description:       Church partnerships, partner portal, multi-gateway payments (Flutterwave, Paystack, PayPal, Stripe, Razorpay, bank transfer, custom API), REST API, email notifications, newsletter, YouTube live streaming, sermons, events, prayer requests and advanced SEO.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kingdom Church Suite
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kingdom-church-suite
 * Domain Path:       /languages
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

define( 'KCS_VERSION', '1.0.0' );
define( 'KCS_DB_VERSION', '1.1.0' );
define( 'KCS_FILE', __FILE__ );
define( 'KCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'KCS_URL', plugin_dir_url( __FILE__ ) );

require_once KCS_PATH . 'includes/functions.php';
require_once KCS_PATH . 'includes/class-kcs-installer.php';
require_once KCS_PATH . 'includes/class-kcs-settings.php';
require_once KCS_PATH . 'includes/class-kcs-post-types.php';
require_once KCS_PATH . 'includes/class-kcs-email.php';
require_once KCS_PATH . 'includes/class-kcs-partners.php';
require_once KCS_PATH . 'includes/class-kcs-plans.php';
require_once KCS_PATH . 'includes/gateways/abstract-kcs-gateway.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-flutterwave.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-paystack.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-paypal.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-stripe.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-razorpay.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-bank-transfer.php';
require_once KCS_PATH . 'includes/gateways/class-kcs-gateway-custom.php';
require_once KCS_PATH . 'includes/class-kcs-payments.php';
require_once KCS_PATH . 'includes/class-kcs-newsletter.php';
require_once KCS_PATH . 'includes/class-kcs-livestream.php';
require_once KCS_PATH . 'includes/class-kcs-prayer.php';
require_once KCS_PATH . 'includes/class-kcs-community.php';
require_once KCS_PATH . 'includes/class-kcs-church-admin.php';
require_once KCS_PATH . 'includes/class-kcs-seo.php';
require_once KCS_PATH . 'includes/class-kcs-features.php';
require_once KCS_PATH . 'includes/class-kcs-shortcodes.php';
require_once KCS_PATH . 'includes/class-kcs-portal.php';
require_once KCS_PATH . 'includes/class-kcs-rest-api.php';
require_once KCS_PATH . 'includes/class-kcs-plugin.php';

if ( is_admin() ) {
	require_once KCS_PATH . 'includes/admin/class-kcs-list-table.php';
	require_once KCS_PATH . 'includes/admin/class-kcs-admin.php';
}

register_activation_hook( __FILE__, array( 'KCS_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KCS_Installer', 'deactivate' ) );

/**
 * Main plugin instance.
 *
 * @return KCS_Plugin
 */
function kcs() {
	return KCS_Plugin::instance();
}

add_action( 'plugins_loaded', 'kcs' );
