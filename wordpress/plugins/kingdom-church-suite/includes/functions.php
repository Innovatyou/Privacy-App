<?php
/**
 * Helper functions.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Full table name for a plugin table.
 *
 * @param string $name Short name, e.g. "partners".
 * @return string
 */
function kcs_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'kcs_' . $name;
}

/**
 * Read a plugin setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function kcs_get_option( $key, $default = '' ) {
	$settings = get_option( 'kcs_settings', array() );
	if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
		return $settings[ $key ];
	}
	if ( '' === $default && class_exists( 'KCS_Settings' ) ) {
		$defaults = KCS_Settings::defaults();
		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}
	}
	return $default;
}

/**
 * Update a single plugin setting.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Value.
 */
function kcs_update_option( $key, $value ) {
	$settings         = get_option( 'kcs_settings', array() );
	$settings[ $key ] = $value;
	update_option( 'kcs_settings', $settings );
}

/**
 * Supported currencies (code => label).
 *
 * @return array
 */
function kcs_currencies() {
	return apply_filters(
		'kcs_currencies',
		array(
			'NGN' => 'Nigerian Naira (₦)',
			'USD' => 'US Dollar ($)',
			'GBP' => 'British Pound (£)',
			'EUR' => 'Euro (€)',
			'GHS' => 'Ghanaian Cedi (GH₵)',
			'KES' => 'Kenyan Shilling (KSh)',
			'ZAR' => 'South African Rand (R)',
			'UGX' => 'Ugandan Shilling (USh)',
			'TZS' => 'Tanzanian Shilling (TSh)',
			'RWF' => 'Rwandan Franc (FRw)',
			'XOF' => 'West African CFA (CFA)',
			'XAF' => 'Central African CFA (FCFA)',
			'CAD' => 'Canadian Dollar (C$)',
			'AUD' => 'Australian Dollar (A$)',
			'INR' => 'Indian Rupee (₹)',
		)
	);
}

/**
 * Currency symbols.
 *
 * @param string $code Currency code.
 * @return string
 */
function kcs_currency_symbol( $code ) {
	$symbols = array(
		'NGN' => '₦',
		'USD' => '$',
		'GBP' => '£',
		'EUR' => '€',
		'GHS' => 'GH₵',
		'KES' => 'KSh ',
		'ZAR' => 'R',
		'UGX' => 'USh ',
		'TZS' => 'TSh ',
		'RWF' => 'FRw ',
		'XOF' => 'CFA ',
		'XAF' => 'FCFA ',
		'CAD' => 'C$',
		'AUD' => 'A$',
		'INR' => '₹',
	);
	return isset( $symbols[ $code ] ) ? $symbols[ $code ] : $code . ' ';
}

/**
 * Format an amount of money.
 *
 * @param float  $amount   Amount.
 * @param string $currency Currency code.
 * @return string
 */
function kcs_format_money( $amount, $currency = '' ) {
	$currency = $currency ? $currency : kcs_get_option( 'currency', 'USD' );
	return kcs_currency_symbol( $currency ) . number_format_i18n( (float) $amount, 2 );
}

/**
 * Partnership frequencies.
 *
 * @return array
 */
function kcs_frequencies() {
	return array(
		'one_time'  => __( 'One-time', 'kingdom-church-suite' ),
		'weekly'    => __( 'Weekly', 'kingdom-church-suite' ),
		'monthly'   => __( 'Monthly', 'kingdom-church-suite' ),
		'quarterly' => __( 'Quarterly', 'kingdom-church-suite' ),
		'yearly'    => __( 'Yearly', 'kingdom-church-suite' ),
	);
}

/**
 * Compute the next due date for a frequency.
 *
 * @param string      $frequency Frequency key.
 * @param string|null $from      Start date (Y-m-d). Defaults to today.
 * @return string|null
 */
function kcs_next_due_date( $frequency, $from = null ) {
	$intervals = array(
		'weekly'    => '+1 week',
		'monthly'   => '+1 month',
		'quarterly' => '+3 months',
		'yearly'    => '+1 year',
	);
	if ( ! isset( $intervals[ $frequency ] ) ) {
		return null;
	}
	$base = $from ? strtotime( $from ) : current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	return gmdate( 'Y-m-d', strtotime( $intervals[ $frequency ], $base ) );
}

/**
 * Giving purposes.
 *
 * @return array
 */
function kcs_giving_purposes() {
	$raw  = kcs_get_option( 'giving_purposes', "Tithe\nOffering\nPartnership\nBuilding Project\nMissions\nWelfare" );
	$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	$out  = array();
	foreach ( $list as $item ) {
		$out[ sanitize_title( $item ) ] = $item;
	}
	return apply_filters( 'kcs_giving_purposes', $out );
}

/**
 * Unique payment reference.
 *
 * @param string $prefix Prefix.
 * @return string
 */
function kcs_generate_reference( $prefix = 'KCS' ) {
	return strtoupper( $prefix ) . '-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 10, false, false ) );
}

/**
 * Get URL of a page configured in settings.
 *
 * @param string $key Page setting key (partner_login, partner_register, partner_dashboard, give, thank_you, partnership).
 * @return string
 */
function kcs_page_url( $key ) {
	$page_id = (int) kcs_get_option( 'page_' . $key, 0 );
	if ( $page_id && get_post_status( $page_id ) ) {
		return get_permalink( $page_id );
	}
	return home_url( '/' );
}

/**
 * Client IP address (best effort, used only for rate limiting).
 *
 * @return string
 */
function kcs_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Simple transient-based rate limiter.
 *
 * @param string $action Action name.
 * @param int    $limit  Allowed hits.
 * @param int    $window Window in seconds.
 * @return bool True when allowed, false when limited.
 */
function kcs_rate_limit( $action, $limit = 10, $window = 600 ) {
	$key  = 'kcs_rl_' . md5( $action . '|' . kcs_client_ip() );
	$hits = (int) get_transient( $key );
	if ( $hits >= $limit ) {
		return false;
	}
	set_transient( $key, $hits + 1, $window );
	return true;
}

/**
 * Read a value from a nested array using dot notation (e.g. "data.link").
 *
 * @param array  $data Array.
 * @param string $path Dot path.
 * @return mixed|null
 */
function kcs_array_get( $data, $path ) {
	if ( '' === $path || null === $path ) {
		return null;
	}
	foreach ( explode( '.', $path ) as $segment ) {
		if ( is_array( $data ) && array_key_exists( $segment, $data ) ) {
			$data = $data[ $segment ];
		} else {
			return null;
		}
	}
	return $data;
}

/**
 * Is the current user a partner?
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function kcs_is_partner( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && (bool) KCS_Partners::get_by_user( $user_id );
}

/**
 * Load a plugin template, allowing themes to override it in /kingdom-church-suite/.
 *
 * @param string $name Template file name (without .php).
 * @param array  $vars Variables to extract.
 * @return string Rendered HTML.
 */
function kcs_get_template( $name, $vars = array() ) {
	$theme_file = locate_template( 'kingdom-church-suite/' . $name . '.php' );
	$file       = $theme_file ? $theme_file : KCS_PATH . 'templates/' . $name . '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	ob_start();
	extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $file;
	return ob_get_clean();
}

/**
 * Status badge HTML.
 *
 * @param string $status Status.
 * @return string
 */
function kcs_status_badge( $status ) {
	return '<span class="kcs-badge kcs-badge--' . esc_attr( $status ) . '">' . esc_html( ucwords( str_replace( '_', ' ', $status ) ) ) . '</span>';
}
