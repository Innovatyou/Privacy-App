<?php
/**
 * Razorpay (Payment Links).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Razorpay gateway.
 */
class KCS_Gateway_Razorpay extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'razorpay';
		$this->title = 'Razorpay';
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'key_id'         => array( 'label' => __( 'Key ID', 'kingdom-church-suite' ), 'type' => 'text' ),
			'key_secret'     => array( 'label' => __( 'Key secret', 'kingdom-church-suite' ), 'type' => 'password' ),
			'webhook_secret' => array( 'label' => __( 'Webhook secret', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'Subscribe to payment_link.paid.', 'kingdom-church-suite' ) ),
		);
	}

	/**
	 * Headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'Authorization' => 'Basic ' . base64_encode( $this->opt( 'key_id' ) . ':' . $this->opt( 'key_secret' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Initialize.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$res = $this->http(
			'POST',
			'https://api.razorpay.com/v1/payment_links',
			array(
				'headers' => $this->headers(),
				'body'    => array(
					'amount'          => $this->to_minor( $payment->amount, $payment->currency ),
					'currency'        => $payment->currency,
					'reference_id'    => $payment->reference,
					'description'     => substr( $this->description( $payment ), 0, 2048 ),
					'customer'        => array_filter(
						array(
							'name'    => $payment->name,
							'email'   => $payment->email,
							'contact' => $payment->phone,
						)
					),
					'notify'          => array( 'sms' => false, 'email' => false ),
					'callback_url'    => $this->callback_url( $payment ),
					'callback_method' => 'get',
					'notes'           => array( 'purpose' => $payment->purpose ),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['short_url'] ) ) {
			return new WP_Error( 'kcs_razorpay', __( 'Could not start Razorpay checkout.', 'kingdom-church-suite' ) );
		}
		return array( 'redirect_url' => $res['short_url'], 'txn_id' => $res['id'] );
	}

	/**
	 * Verify through the API.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		$link_id = $payment->gateway_txn_id;
		if ( ! $link_id ) {
			return array( 'status' => 'pending' );
		}
		$res = $this->http( 'GET', 'https://api.razorpay.com/v1/payment_links/' . rawurlencode( $link_id ), array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ( $res['reference_id'] ?? '' ) !== $payment->reference ) {
			return new WP_Error( 'kcs_razorpay_mismatch', 'Link does not belong to this payment.' );
		}
		if ( 'paid' === ( $res['status'] ?? '' ) ) {
			$currency = (string) $res['currency'];
			return array(
				'status'   => 'completed',
				'txn_id'   => (string) ( kcs_array_get( $res, 'payments.0.payment_id' ) ? kcs_array_get( $res, 'payments.0.payment_id' ) : $link_id ),
				'amount'   => $this->from_minor( (int) $res['amount_paid'], $currency ),
				'currency' => $currency,
			);
		}
		return array( 'status' => in_array( $res['status'] ?? '', array( 'cancelled', 'expired' ), true ) ? 'failed' : 'pending' );
	}

	/**
	 * Webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$sig    = (string) $request->get_header( 'x-razorpay-signature' );
		$secret = (string) $this->opt( 'webhook_secret' );
		if ( ! $secret || ! $sig || ! hash_equals( hash_hmac( 'sha256', $request->get_body(), $secret ), $sig ) ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$body = $request->get_json_params();
		return array( 'reference' => (string) kcs_array_get( $body, 'payload.payment_link.entity.reference_id' ), 'params' => array() );
	}
}
