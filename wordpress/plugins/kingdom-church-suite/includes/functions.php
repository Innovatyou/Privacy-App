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
	$raw  = kcs_get_option( 'giving_purposes', "Tithe\nOffering\nThanksgiving\nFirst Fruit\nSeed\nPartnership\nBuilding Project\nMissions\nWelfare" );
	$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	$out  = array();
	foreach ( $list as $item ) {
		$out[ sanitize_title( $item ) ] = $item;
	}
	return apply_filters( 'kcs_giving_purposes', $out );
}

/**
 * Giving types shown on the Tithe & Offering page.
 *
 * @return array slug => label.
 */
function kcs_tithe_types() {
	$raw = kcs_get_option( 'tithe_types', "Tithe\nOffering\nThanksgiving\nFirst Fruit\nSeed" );
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) as $item ) {
		$out[ sanitize_title( $item ) ] = $item;
	}
	return apply_filters( 'kcs_tithe_types', $out );
}

/**
 * Every purpose a payment may be recorded against.
 *
 * @return array slug => label.
 */
function kcs_all_purposes() {
	return array_merge(
		array(
			'donation'    => __( 'Donation', 'kingdom-church-suite' ),
			'partnership' => __( 'Partnership', 'kingdom-church-suite' ),
		),
		kcs_giving_purposes(),
		kcs_tithe_types()
	);
}

/**
 * Local currency, or '' when the church only uses its international currency.
 *
 * @return string
 */
function kcs_local_currency() {
	$intl  = kcs_get_option( 'currency', 'USD' );
	$local = strtoupper( (string) kcs_get_option( 'local_currency', 'NGN' ) );
	return ( $local !== $intl && array_key_exists( $local, kcs_currencies() ) ) ? $local : '';
}

/**
 * Partner/giver regions. International (the default currency) always comes first.
 *
 * @return array region => array( label, currency ).
 */
function kcs_regions() {
	$out = array(
		'international' => array(
			'label'    => kcs_get_option( 'international_label', __( 'International', 'kingdom-church-suite' ) ),
			'currency' => kcs_get_option( 'currency', 'USD' ),
		),
	);
	$local = kcs_local_currency();
	if ( $local ) {
		$out['local'] = array(
			'label'    => kcs_get_option( 'local_label', __( 'Local', 'kingdom-church-suite' ) ),
			'currency' => $local,
		);
	}
	return $out;
}

/**
 * Clean a region value, falling back to international.
 *
 * @param string $region Region.
 * @return string
 */
function kcs_sanitize_region( $region ) {
	return array_key_exists( (string) $region, kcs_regions() ) ? (string) $region : 'international';
}

/**
 * Currency for a region.
 *
 * @param string $region Region.
 * @return string
 */
function kcs_region_currency( $region ) {
	$regions = kcs_regions();
	return $regions[ kcs_sanitize_region( $region ) ]['currency'];
}

/**
 * Region a currency belongs to.
 *
 * @param string $currency Currency.
 * @return string
 */
function kcs_currency_region( $currency ) {
	$local = kcs_local_currency();
	return $local && strtoupper( (string) $currency ) === $local ? 'local' : 'international';
}

/**
 * Quick-pick amounts for a region.
 *
 * @param string $region Region.
 * @return float[]
 */
function kcs_region_amounts( $region ) {
	$raw = 'local' === $region ? kcs_get_option( 'amounts_local', '5000,10000,20000,50000,100000,250000' ) : kcs_get_option( 'amounts_international', '10,25,50,100,250,500' );
	return array_values( array_filter( array_map( 'floatval', explode( ',', (string) $raw ) ) ) );
}

/**
 * International / Local switch. With $name it is a real form field; otherwise it only changes the view.
 *
 * @param string $selected Selected region.
 * @param string $name     Field name ('' for a view-only switch).
 * @return string
 */
function kcs_region_toggle( $selected = 'international', $name = '' ) {
	$regions = kcs_regions();
	if ( count( $regions ) < 2 ) {
		return '';
	}
	$group = $name ? $name : 'kcs_region_view_' . wp_rand( 1000, 99999 );
	$html  = '<div class="kcs-region" role="radiogroup" aria-label="' . esc_attr__( 'Where are you giving from?', 'kingdom-church-suite' ) . '">';
	foreach ( $regions as $key => $r ) {
		$icon  = 'local' === $key ? '📍' : '🌍';
		$html .= '<label class="kcs-region__opt"><input type="radio" data-kcs-region data-currency="' . esc_attr( $r['currency'] ) . '" name="' . esc_attr( $group ) . '" value="' . esc_attr( $key ) . '"' . checked( $selected, $key, false ) . '><span>' . $icon . ' ' . esc_html( $r['label'] ) . ' <b>' . esc_html( $r['currency'] ) . '</b></span></label>';
	}
	return $html . '</div>';
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
	// Include from a clean scope so template variables such as $name are not shadowed by this function's own.
	( static function ( $kcs_file, $kcs_vars ) {
		extract( $kcs_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $kcs_file;
	} )( $file, $vars );
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
