<?php
/**
 * Plugin Name:       Kingdom Cloud (SaaS)
 * Plugin URI:        https://example.com/kingdom-cloud
 * Description:       Sell Kingdom Church websites as a service: church sign-up with free trial, a site per church on its own subdomain, custom domains, subscription billing (Paystack, Stripe, bank transfer), plan limits, automatic suspension and a platform dashboard. Requires WordPress Multisite (subdomain install).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Network:           true
 * Author:            Kingdom Church Suite
 * License:           GPL-2.0-or-later
 * Text Domain:       kingdom-saas
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

define( 'KS_VERSION', '1.0.0' );
define( 'KS_DB_VERSION', '1.0.0' );
define( 'KS_FILE', __FILE__ );
define( 'KS_PATH', plugin_dir_path( __FILE__ ) );
define( 'KS_URL', plugin_dir_url( __FILE__ ) );

if ( ! is_multisite() ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Kingdom Cloud needs WordPress Multisite with subdomains. See the plugin README for setup steps.', 'kingdom-saas' ) . '</p></div>';
		}
	);
	return;
}

require_once KS_PATH . 'includes/functions.php';
require_once KS_PATH . 'includes/class-ks-install.php';
require_once KS_PATH . 'includes/class-ks-plans.php';
require_once KS_PATH . 'includes/class-ks-tenants.php';
require_once KS_PATH . 'includes/class-ks-email.php';
require_once KS_PATH . 'includes/class-ks-provisioner.php';
require_once KS_PATH . 'includes/class-ks-billing.php';
require_once KS_PATH . 'includes/class-ks-domains.php';
require_once KS_PATH . 'includes/class-ks-lifecycle.php';
require_once KS_PATH . 'includes/class-ks-limits.php';
require_once KS_PATH . 'includes/class-ks-login.php';
require_once KS_PATH . 'includes/class-ks-rest.php';
require_once KS_PATH . 'includes/class-ks-church-admin.php';
require_once KS_PATH . 'includes/class-ks-platform.php';

register_activation_hook( __FILE__, array( 'KS_Install', 'activate' ) );

add_action( 'plugins_loaded', array( 'KS_Install', 'maybe_upgrade' ), 5 );
add_action(
	'plugins_loaded',
	function () {
		KS_Lifecycle::init();
		KS_Limits::init();
		KS_Login::init();
		KS_REST::init();
		KS_Domains::init();
		KS_Church_Admin::init();
		KS_Platform::init();
		KS_Provisioner::init();
	},
	20
);
