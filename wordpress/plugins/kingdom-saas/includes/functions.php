<?php
/**
 * Helpers.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Network table name.
 *
 * @param string $name Short name.
 * @return string
 */
function ks_table( $name ) {
	global $wpdb;
	return $wpdb->base_prefix . 'ks_' . $name;
}

/**
 * Default platform settings.
 *
 * @return array
 */
function ks_default_settings() {
	return array(
		'platform_name'   => 'ChurchHub',
		'tagline'         => __( 'Your church website, partner portal and online giving — ready in minutes.', 'kingdom-saas' ),
		'brand_color'     => '#5b2a86',
		'support_email'   => get_site_option( 'admin_email' ),
		'base_domain'     => defined( 'DOMAIN_CURRENT_SITE' ) ? DOMAIN_CURRENT_SITE : wp_parse_url( network_home_url(), PHP_URL_HOST ),
		'cname_target'    => '',
		'server_ip'       => '',
		'https'           => '1',
		'trial_days'      => '14',
		'grace_days'      => '7',
		'reminder_days'   => '3',
		'local_currency'  => 'NGN',
		'local_label'     => 'Nigeria',
		'paystack_secret' => '',
		'stripe_secret'   => '',
		'stripe_webhook'  => '',
		'bank_details'    => '',
		'test_mode'       => '0',
		'reserved'        => 'www,mail,ftp,admin,administrator,api,app,platform,billing,support,help,docs,status,blog,static,assets,cdn,dev,staging,test,root,webmail,smtp,ns1,ns2',
	);
}

/**
 * Read a platform setting.
 *
 * @param string $key Key.
 * @return mixed
 */
function ks_get( $key ) {
	$all      = get_site_option( 'ks_settings', array() );
	$defaults = ks_default_settings();
	if ( isset( $all[ $key ] ) && '' !== $all[ $key ] ) {
		return $all[ $key ];
	}
	return $defaults[ $key ] ?? '';
}

/**
 * Save platform settings (merged).
 *
 * @param array $values Values.
 */
function ks_set( $values ) {
	$all = get_site_option( 'ks_settings', array() );
	update_site_option( 'ks_settings', array_merge( $all, $values ) );
}

/**
 * Is billing in test mode (simulated checkout, no real charges)?
 *
 * @return bool
 */
function ks_test_mode() {
	return '1' === (string) ks_get( 'test_mode' );
}

/**
 * Scheme used for church sites.
 *
 * @return string
 */
function ks_scheme() {
	return '1' === (string) ks_get( 'https' ) ? 'https' : 'http';
}

/**
 * Format money.
 *
 * @param float  $amount   Amount.
 * @param string $currency Currency.
 * @return string
 */
function ks_money( $amount, $currency ) {
	$symbols = array( 'USD' => '$', 'NGN' => '₦', 'GHS' => 'GH₵', 'KES' => 'KSh ', 'ZAR' => 'R', 'GBP' => '£', 'EUR' => '€' );
	$sym     = $symbols[ strtoupper( $currency ) ] ?? strtoupper( $currency ) . ' ';
	$dec     = floor( (float) $amount ) == (float) $amount ? 0 : 2; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	return $sym . number_format_i18n( (float) $amount, $dec );
}

/**
 * Currency for a billing region.
 *
 * @param string $region international|local.
 * @return string
 */
function ks_region_currency( $region ) {
	return 'local' === $region && ks_get( 'local_currency' ) ? strtoupper( ks_get( 'local_currency' ) ) : 'USD';
}

/**
 * URL on the platform (main) site.
 *
 * @param string $path Path.
 * @return string
 */
function ks_platform_url( $path = '' ) {
	return network_home_url( ltrim( $path, '/' ) );
}

/**
 * Simple rate limit by IP.
 *
 * @param string $key    Bucket.
 * @param int    $limit  Max hits.
 * @param int    $window Seconds.
 * @return bool True when allowed.
 */
function ks_rate_limit( $key, $limit, $window ) {
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	$name = 'ks_rl_' . md5( $key . $ip );
	$hits = (int) get_site_transient( $name );
	if ( $hits >= $limit ) {
		return false;
	}
	set_site_transient( $name, $hits + 1, $window );
	return true;
}

/**
 * Render a plugin template.
 *
 * @param string $name Template name.
 * @param array  $vars Variables.
 * @return string
 */
function ks_template( $name, $vars = array() ) {
	$file = KS_PATH . 'templates/' . $name . '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	ob_start();
	( static function ( $ks_file, $ks_vars ) {
		extract( $ks_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $ks_file;
	} )( $file, $vars );
	return ob_get_clean();
}

/**
 * Status badge.
 *
 * @param string $status Status.
 * @return string
 */
function ks_badge( $status ) {
	$labels = array(
		'trialing'  => __( 'Free trial', 'kingdom-saas' ),
		'active'    => __( 'Active', 'kingdom-saas' ),
		'past_due'  => __( 'Payment due', 'kingdom-saas' ),
		'suspended' => __( 'Suspended', 'kingdom-saas' ),
		'cancelled' => __( 'Cancelled', 'kingdom-saas' ),
		'pending'   => __( 'Pending', 'kingdom-saas' ),
		'paid'      => __( 'Paid', 'kingdom-saas' ),
		'failed'    => __( 'Failed', 'kingdom-saas' ),
		'verified'  => __( 'Connected', 'kingdom-saas' ),
	);
	return '<span class="ks-badge ks-badge--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? ucfirst( $status ) ) . '</span>';
}
