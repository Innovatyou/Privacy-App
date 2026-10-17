<?php
/**
 * PayPal (Orders v2 API, redirect approval).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * PayPal gateway.
 */
class KCS_Gateway_PayPal extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'paypal';
		$this->title      = 'PayPal';
		$this->currencies = array( 'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD' );
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'test_mode'     => array( 'label' => __( 'Sandbox mode', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
			'client_id'     => array( 'label' => __( 'Client ID', 'kingdom-church-suite' ), 'type' => 'text' ),
			'client_secret' => array( 'label' => __( 'Client secret', 'kingdom-church-suite' ), 'type' => 'password' ),
			'webhook_id'    => array( 'label' => __( 'Webhook ID', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'From developer.paypal.com → your app → Webhooks. Subscribe to PAYMENT.CAPTURE.COMPLETED and CHECKOUT.ORDER.APPROVED.', 'kingdom-church-suite' ) ),
		);
	}

	/**
	 * API base.
	 *
	 * @return string
	 */
	private function base() {
		return $this->is_test() ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * OAuth access token (cached).
	 *
	 * @return string|WP_Error
	 */
	private function token() {
		$key    = 'kcs_paypal_token_' . md5( $this->opt( 'client_id' ) . $this->base() );
		$cached = get_transient( $key );
		if ( $cached ) {
			return $cached;
		}
		$res = $this->http(
			'POST',
			$this->base() . '/v1/oauth2/token',
			array(
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $this->opt( 'client_id' ) . ':' . $this->opt( 'client_secret' ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'body'    => array( 'grant_type' => 'client_credentials' ),
				'form'    => true,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['access_token'] ) ) {
			return new WP_Error( 'kcs_paypal_auth', __( 'PayPal authentication failed. Check your credentials.', 'kingdom-church-suite' ) );
		}
		set_transient( $key, $res['access_token'], max( 60, (int) ( $res['expires_in'] ?? 3600 ) - 120 ) );
		return $res['access_token'];
	}

	/**
	 * Authenticated request.
	 *
	 * @param string $method Method.
	 * @param string $path   Path.
	 * @param array  $body   Body.
	 * @return array|WP_Error
	 */
	private function api( $method, $path, $body = null ) {
		$token = $this->token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'headers'      => array( 'Authorization' => 'Bearer ' . $token ),
			'allow_errors' => true,
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		return $this->http( $method, $this->base() . $path, $args );
	}

	/**
	 * Initialize: create an order and redirect to approval.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$value = in_array( $payment->currency, array( 'JPY', 'HUF', 'TWD' ), true ) ? (string) round( $payment->amount ) : number_format( (float) $payment->amount, 2, '.', '' );
		$res   = $this->api(
			'POST',
			'/v2/checkout/orders',
			array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array(
					array(
						'reference_id' => $payment->reference,
						'custom_id'    => $payment->reference,
						'description'  => substr( $this->description( $payment ), 0, 127 ),
						'amount'       => array( 'currency_code' => $payment->currency, 'value' => $value ),
					),
				),
				'payment_source' => array(
					'paypal' => array(
						'experience_context' => array(
							'brand_name'          => substr( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ), 0, 127 ),
							'shipping_preference' => 'NO_SHIPPING',
							'user_action'         => 'PAY_NOW',
							'return_url'          => $this->callback_url( $payment ),
							'cancel_url'          => add_query_arg( 'cancelled', '1', $this->callback_url( $payment ) ),
						),
					),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$approve = '';
		foreach ( (array) ( $res['links'] ?? array() ) as $link ) {
			if ( in_array( $link['rel'] ?? '', array( 'payer-action', 'approve' ), true ) ) {
				$approve = $link['href'];
			}
		}
		if ( ! $approve || empty( $res['id'] ) ) {
			return new WP_Error( 'kcs_paypal', kcs_array_get( $res, 'message' ) ? kcs_array_get( $res, 'message' ) : __( 'Could not start PayPal checkout.', 'kingdom-church-suite' ) );
		}
		return array( 'redirect_url' => $approve, 'txn_id' => $res['id'] );
	}

	/**
	 * Verify: capture the approved order (or read an already captured one).
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		if ( ! empty( $params['cancelled'] ) ) {
			return array( 'status' => 'failed' );
		}
		$order_id = $payment->gateway_txn_id ? $payment->gateway_txn_id : ( $params['token'] ?? '' );
		if ( ! $order_id ) {
			return array( 'status' => 'pending' );
		}
		$order = $this->api( 'GET', '/v2/checkout/orders/' . rawurlencode( $order_id ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( kcs_array_get( $order, 'purchase_units.0.reference_id' ) !== $payment->reference ) {
			return new WP_Error( 'kcs_paypal_mismatch', 'Order does not belong to this payment.' );
		}
		if ( 'APPROVED' === ( $order['status'] ?? '' ) ) {
			$order = $this->api( 'POST', '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', new stdClass() );
			if ( is_wp_error( $order ) ) {
				return $order;
			}
		}
		$capture = kcs_array_get( $order, 'purchase_units.0.payments.captures.0' );
		if ( 'COMPLETED' === ( $order['status'] ?? '' ) && is_array( $capture ) && 'COMPLETED' === ( $capture['status'] ?? '' ) ) {
			return array(
				'status'   => 'completed',
				'txn_id'   => (string) $capture['id'],
				'amount'   => (float) kcs_array_get( $capture, 'amount.value' ),
				'currency' => (string) kcs_array_get( $capture, 'amount.currency_code' ),
			);
		}
		return array( 'status' => in_array( $order['status'] ?? '', array( 'VOIDED' ), true ) ? 'failed' : 'pending' );
	}

	/**
	 * Webhook: verify signature with PayPal's API.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$event = json_decode( $request->get_body(), true );
		if ( ! $this->opt( 'webhook_id' ) || ! is_array( $event ) ) {
			return new WP_Error( 'kcs_bad_signature', 'Webhook not configured', array( 'status' => 400 ) );
		}
		$check = $this->api(
			'POST',
			'/v1/notifications/verify-webhook-signature',
			array(
				'auth_algo'         => $request->get_header( 'paypal-auth-algo' ),
				'cert_url'          => $request->get_header( 'paypal-cert-url' ),
				'transmission_id'   => $request->get_header( 'paypal-transmission-id' ),
				'transmission_sig'  => $request->get_header( 'paypal-transmission-sig' ),
				'transmission_time' => $request->get_header( 'paypal-transmission-time' ),
				'webhook_id'        => $this->opt( 'webhook_id' ),
				'webhook_event'     => $event,
			)
		);
		if ( is_wp_error( $check ) || 'SUCCESS' !== ( $check['verification_status'] ?? '' ) ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$ref = kcs_array_get( $event, 'resource.custom_id' );
		if ( ! $ref ) {
			$ref = kcs_array_get( $event, 'resource.purchase_units.0.reference_id' );
		}
		$order_id = kcs_array_get( $event, 'resource.supplementary_data.related_ids.order_id' );
		if ( ! $order_id && 'CHECKOUT.ORDER.APPROVED' === ( $event['event_type'] ?? '' ) ) {
			$order_id = kcs_array_get( $event, 'resource.id' );
		}
		return array( 'reference' => (string) $ref, 'params' => array( 'token' => (string) $order_id ) );
	}
}
