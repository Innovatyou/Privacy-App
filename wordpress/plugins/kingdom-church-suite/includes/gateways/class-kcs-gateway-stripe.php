<?php
/**
 * Stripe (Checkout Sessions).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stripe gateway.
 */
class KCS_Gateway_Stripe extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'stripe';
		$this->title = 'Stripe (Cards, Apple Pay, Google Pay)';
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'publishable_key' => array( 'label' => __( 'Publishable key', 'kingdom-church-suite' ), 'type' => 'text' ),
			'secret_key'      => array( 'label' => __( 'Secret key', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'sk_test_… for testing, sk_live_… for live.', 'kingdom-church-suite' ) ),
			'webhook_secret'  => array( 'label' => __( 'Webhook signing secret', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'whsec_… — subscribe to checkout.session.completed and checkout.session.async_payment_succeeded.', 'kingdom-church-suite' ) ),
		);
	}

	/**
	 * Headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization'  => 'Bearer ' . $this->opt( 'secret_key' ),
			'Stripe-Version' => '2024-06-20',
		);
	}

	/**
	 * Initialize.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$success = add_query_arg( 'session_id', '{CHECKOUT_SESSION_ID}', $this->callback_url( $payment ) );
		$res     = $this->http(
			'POST',
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'headers' => $this->headers(),
				'form'    => true,
				'body'    => array(
					'mode'                => 'payment',
					'success_url'         => $success,
					'cancel_url'          => add_query_arg( 'cancelled', '1', $this->callback_url( $payment ) ),
					'client_reference_id' => $payment->reference,
					'customer_email'      => $payment->email,
					'submit_type'         => 'donate',
					'line_items'          => array(
						array(
							'quantity'   => 1,
							'price_data' => array(
								'currency'     => strtolower( $payment->currency ),
								'unit_amount'  => $this->to_minor( $payment->amount, $payment->currency ),
								'product_data' => array( 'name' => $this->description( $payment ) ),
							),
						),
					),
					'metadata'            => array(
						'reference'  => $payment->reference,
						'purpose'    => $payment->purpose,
						'partner_id' => (int) $payment->partner_id,
					),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['url'] ) ) {
			return new WP_Error( 'kcs_stripe', __( 'Could not start Stripe checkout.', 'kingdom-church-suite' ) );
		}
		return array( 'redirect_url' => $res['url'], 'txn_id' => $res['id'] );
	}

	/**
	 * Verify.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		if ( ! empty( $params['cancelled'] ) ) {
			return array( 'status' => 'failed' );
		}
		$session_id = $payment->gateway_txn_id ? $payment->gateway_txn_id : ( $params['session_id'] ?? '' );
		if ( ! $session_id || 0 !== strpos( $session_id, 'cs_' ) ) {
			return array( 'status' => 'pending' );
		}
		$res = $this->http( 'GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( $session_id ), array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ( $res['client_reference_id'] ?? '' ) !== $payment->reference ) {
			return new WP_Error( 'kcs_stripe_mismatch', 'Session does not belong to this payment.' );
		}
		if ( 'paid' === ( $res['payment_status'] ?? '' ) ) {
			$currency = strtoupper( (string) $res['currency'] );
			return array(
				'status'   => 'completed',
				'txn_id'   => (string) ( $res['payment_intent'] ?? $res['id'] ),
				'amount'   => $this->from_minor( (int) $res['amount_total'], $currency ),
				'currency' => $currency,
			);
		}
		return array( 'status' => 'expired' === ( $res['status'] ?? '' ) ? 'failed' : 'pending' );
	}

	/**
	 * Webhook with Stripe-Signature verification.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$payload = $request->get_body();
		$header  = (string) $request->get_header( 'stripe-signature' );
		$secret  = (string) $this->opt( 'webhook_secret' );
		$parts   = array();
		foreach ( explode( ',', $header ) as $pair ) {
			$kv = explode( '=', trim( $pair ), 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ][] = $kv[1];
			}
		}
		$t     = isset( $parts['t'][0] ) ? (int) $parts['t'][0] : 0;
		$valid = false;
		if ( $secret && $t && abs( time() - $t ) < 600 ) {
			$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
			foreach ( (array) ( $parts['v1'] ?? array() ) as $sig ) {
				if ( hash_equals( $expected, $sig ) ) {
					$valid = true;
				}
			}
		}
		if ( ! $valid ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$event = json_decode( $payload, true );
		$obj   = kcs_array_get( $event, 'data.object' );
		return array(
			'reference' => (string) ( $obj['client_reference_id'] ?? '' ),
			'params'    => array( 'session_id' => (string) ( $obj['id'] ?? '' ) ),
		);
	}
}
