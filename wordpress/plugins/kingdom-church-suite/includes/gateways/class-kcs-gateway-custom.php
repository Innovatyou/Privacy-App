<?php
/**
 * Custom API gateway — connect any provider with a hosted checkout
 * (e.g. Monnify, Interswitch, Remita, Squad, Opay, Chapa, PesaPal, M-Pesa aggregators)
 * purely from settings: endpoints, JSON body templates and response paths.
 *
 * Placeholders usable in URLs, headers and body templates:
 * {amount} {amount_minor} {currency} {reference} {email} {name} {phone}
 * {callback_url} {description} {secret_key} {public_key} {txn_id}
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Custom gateway.
 */
class KCS_Gateway_Custom extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'custom';
		$this->title = __( 'Custom API Gateway', 'kingdom-church-suite' );
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'public_key'       => array( 'label' => __( 'Public key / client ID', 'kingdom-church-suite' ), 'type' => 'text' ),
			'secret_key'       => array( 'label' => __( 'Secret key', 'kingdom-church-suite' ), 'type' => 'password' ),
			'currencies'       => array( 'label' => __( 'Supported currencies', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'Comma separated; empty = all.', 'kingdom-church-suite' ) ),
			'headers'          => array( 'label' => __( 'Request headers', 'kingdom-church-suite' ), 'type' => 'textarea', 'default' => 'Authorization: Bearer {secret_key}', 'desc' => __( 'One per line: Name: value', 'kingdom-church-suite' ) ),
			'init_url'         => array( 'label' => __( 'Initialize endpoint URL', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => 'https://api.provider.com/v1/checkout' ),
			'init_method'      => array( 'label' => __( 'Initialize method', 'kingdom-church-suite' ), 'type' => 'select', 'options' => array( 'POST' => 'POST', 'GET' => 'GET' ), 'default' => 'POST' ),
			'init_body'        => array( 'label' => __( 'Initialize JSON body', 'kingdom-church-suite' ), 'type' => 'code', 'default' => "{\n  \"amount\": {amount},\n  \"currency\": \"{currency}\",\n  \"reference\": \"{reference}\",\n  \"email\": \"{email}\",\n  \"name\": \"{name}\",\n  \"redirect_url\": \"{callback_url}\",\n  \"description\": \"{description}\"\n}" ),
			'redirect_path'    => array( 'label' => __( 'Checkout URL path in response', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'data.checkout_url', 'desc' => __( 'Dot notation, e.g. data.link or responseBody.checkoutUrl', 'kingdom-church-suite' ) ),
			'txn_path'         => array( 'label' => __( 'Transaction ID path in response (optional)', 'kingdom-church-suite' ), 'type' => 'text' ),
			'verify_url'       => array( 'label' => __( 'Verify endpoint URL', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => 'https://api.provider.com/v1/transactions/{reference}' ),
			'verify_method'    => array( 'label' => __( 'Verify method', 'kingdom-church-suite' ), 'type' => 'select', 'options' => array( 'GET' => 'GET', 'POST' => 'POST' ), 'default' => 'GET' ),
			'verify_body'      => array( 'label' => __( 'Verify JSON body (POST only)', 'kingdom-church-suite' ), 'type' => 'code' ),
			'status_path'      => array( 'label' => __( 'Status path', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'data.status' ),
			'success_values'   => array( 'label' => __( 'Success status values', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'success,successful,paid,completed,PAID', 'desc' => __( 'Comma separated.', 'kingdom-church-suite' ) ),
			'failed_values'    => array( 'label' => __( 'Failed status values', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'failed,cancelled,abandoned,expired' ),
			'amount_path'      => array( 'label' => __( 'Amount path', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'data.amount' ),
			'amount_minor'     => array( 'label' => __( 'Amounts are in minor units (kobo/cents)', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '0' ),
			'currency_path'    => array( 'label' => __( 'Currency path (optional)', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'data.currency' ),
			'verify_txn_path'  => array( 'label' => __( 'Transaction ID path in verify response', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'data.id' ),
			'webhook_header'   => array( 'label' => __( 'Webhook signature header', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => 'e.g. x-signature' ),
			'webhook_algo'     => array( 'label' => __( 'Webhook signature algorithm', 'kingdom-church-suite' ), 'type' => 'select', 'options' => array( 'sha256' => 'HMAC-SHA256', 'sha512' => 'HMAC-SHA512', 'sha1' => 'HMAC-SHA1', 'plain' => __( 'Plain shared secret', 'kingdom-church-suite' ) ), 'default' => 'sha256' ),
			'webhook_secret'   => array( 'label' => __( 'Webhook secret', 'kingdom-church-suite' ), 'type' => 'password' ),
			'webhook_ref_path' => array( 'label' => __( 'Reference path in webhook body', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'data.reference' ),
		);
	}

	/**
	 * Currencies from settings.
	 *
	 * @return array
	 */
	public function currency_list() {
		return array_values( array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $this->opt( 'currencies' ) ) ) ) ) );
	}

	/**
	 * Currency support from settings.
	 *
	 * @param string $currency Code.
	 * @return bool
	 */
	public function supports_currency( $currency ) {
		$list = $this->currency_list();
		return empty( $list ) || in_array( strtoupper( $currency ), $list, true );
	}

	/**
	 * Placeholder values for a payment.
	 *
	 * @param object $payment Payment.
	 * @return array
	 */
	private function vars( $payment ) {
		return array(
			'amount'       => number_format( (float) $payment->amount, 2, '.', '' ),
			'amount_minor' => (string) $this->to_minor( $payment->amount, $payment->currency ),
			'currency'     => $payment->currency,
			'reference'    => $payment->reference,
			'email'        => $payment->email,
			'name'         => $payment->name,
			'phone'        => $payment->phone,
			'callback_url' => $this->callback_url( $payment ),
			'description'  => $this->description( $payment ),
			'secret_key'   => $this->opt( 'secret_key' ),
			'public_key'   => $this->opt( 'public_key' ),
			'txn_id'       => $payment->gateway_txn_id,
		);
	}

	/**
	 * Fill a template.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Vars.
	 * @param string $mode     raw|url|json.
	 * @return string
	 */
	private function fill( $template, $vars, $mode = 'raw' ) {
		$map = array();
		foreach ( $vars as $k => $v ) {
			if ( 'url' === $mode ) {
				$v = rawurlencode( (string) $v );
			} elseif ( 'json' === $mode ) {
				$v = substr( wp_json_encode( (string) $v ), 1, -1 );
			}
			$map[ '{' . $k . '}' ] = $v;
		}
		return strtr( (string) $template, $map );
	}

	/**
	 * Parsed headers.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	private function headers( $vars ) {
		$out = array();
		foreach ( explode( "\n", (string) $this->opt( 'headers' ) ) as $line ) {
			$parts = explode( ':', $line, 2 );
			if ( 2 === count( $parts ) && trim( $parts[0] ) ) {
				$out[ trim( $parts[0] ) ] = trim( $this->fill( $parts[1], $vars ) );
			}
		}
		return $out;
	}

	/**
	 * Initialize.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$vars = $this->vars( $payment );
		$url  = $this->fill( $this->opt( 'init_url' ), $vars, 'url' );
		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'kcs_custom_config', __( 'Custom gateway initialize URL is not configured.', 'kingdom-church-suite' ) );
		}
		$args = array( 'headers' => $this->headers( $vars ) );
		if ( 'POST' === $this->opt( 'init_method', 'POST' ) ) {
			$args['body'] = $this->fill( $this->opt( 'init_body' ), $vars, 'json' );
		}
		$res = $this->http( $this->opt( 'init_method', 'POST' ), $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$redirect = kcs_array_get( $res, $this->opt( 'redirect_path', 'data.checkout_url' ) );
		if ( ! $redirect || ! wp_http_validate_url( $redirect ) ) {
			return new WP_Error( 'kcs_custom', __( 'Checkout URL not found in the provider response. Check "Checkout URL path".', 'kingdom-church-suite' ) );
		}
		$txn = $this->opt( 'txn_path' ) ? (string) kcs_array_get( $res, $this->opt( 'txn_path' ) ) : '';
		return array( 'redirect_url' => $redirect, 'txn_id' => $txn );
	}

	/**
	 * Verify.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		$vars = $this->vars( $payment );
		$url  = $this->fill( $this->opt( 'verify_url' ), $vars, 'url' );
		if ( ! wp_http_validate_url( $url ) ) {
			return array( 'status' => 'pending' );
		}
		$args = array( 'headers' => $this->headers( $vars ), 'allow_errors' => true );
		if ( 'POST' === $this->opt( 'verify_method', 'GET' ) ) {
			$args['body'] = $this->fill( $this->opt( 'verify_body' ), $vars, 'json' );
		}
		$res = $this->http( $this->opt( 'verify_method', 'GET' ), $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status  = (string) kcs_array_get( $res, $this->opt( 'status_path', 'data.status' ) );
		$success = array_map( 'trim', explode( ',', (string) $this->opt( 'success_values', 'success' ) ) );
		$failed  = array_map( 'trim', explode( ',', (string) $this->opt( 'failed_values', 'failed' ) ) );
		if ( '' !== $status && in_array( $status, $success, true ) ) {
			$amount   = (float) kcs_array_get( $res, $this->opt( 'amount_path', 'data.amount' ) );
			$currency = $this->opt( 'currency_path' ) ? (string) kcs_array_get( $res, $this->opt( 'currency_path' ) ) : '';
			$currency = $currency ? $currency : $payment->currency;
			if ( '1' === $this->opt( 'amount_minor' ) ) {
				$amount = $this->from_minor( (int) $amount, $currency );
			}
			return array(
				'status'   => 'completed',
				'txn_id'   => (string) kcs_array_get( $res, $this->opt( 'verify_txn_path', 'data.id' ) ),
				'amount'   => $amount,
				'currency' => $currency,
			);
		}
		return array( 'status' => in_array( $status, $failed, true ) ? 'failed' : 'pending' );
	}

	/**
	 * Webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$header = strtolower( (string) $this->opt( 'webhook_header' ) );
		$secret = (string) $this->opt( 'webhook_secret' );
		$sig    = $header ? (string) $request->get_header( $header ) : '';
		if ( ! $secret || ! $sig ) {
			return new WP_Error( 'kcs_bad_signature', 'Webhook not configured', array( 'status' => 401 ) );
		}
		$algo     = $this->opt( 'webhook_algo', 'sha256' );
		$expected = 'plain' === $algo ? $secret : hash_hmac( $algo, $request->get_body(), $secret );
		$sig      = preg_replace( '/^(sha\d+=)/i', '', $sig );
		if ( ! hash_equals( strtolower( $expected ), strtolower( $sig ) ) && ! hash_equals( $expected, $sig ) ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$body = json_decode( $request->get_body(), true );
		return array( 'reference' => (string) kcs_array_get( $body, $this->opt( 'webhook_ref_path', 'data.reference' ) ), 'params' => array() );
	}
}
