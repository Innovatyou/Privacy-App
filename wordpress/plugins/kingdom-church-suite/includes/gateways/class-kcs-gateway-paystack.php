<?php
/**
 * Paystack (hosted checkout).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paystack gateway.
 */
class KCS_Gateway_Paystack extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'paystack';
		$this->title      = 'Paystack';
		$this->currencies = array( 'NGN', 'GHS', 'ZAR', 'KES', 'USD', 'XOF', 'EGP', 'RWF' );
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'public_key' => array( 'label' => __( 'Public key', 'kingdom-church-suite' ), 'type' => 'text' ),
			'secret_key' => array( 'label' => __( 'Secret key', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'sk_test_… for testing, sk_live_… for live. Also used to verify webhook signatures.', 'kingdom-church-suite' ) ),
			'channels'   => array( 'label' => __( 'Channels (optional)', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => 'card,bank,ussd,qr,mobile_money,bank_transfer' ),
		);
	}

	/**
	 * Headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'Authorization' => 'Bearer ' . $this->opt( 'secret_key' ) );
	}

	/**
	 * Initialize.
	 *
	 * @param object $payment Payment.
	 * @return array|WP_Error
	 */
	public function initialize( $payment ) {
		$body     = array(
			'email'        => $payment->email,
			'amount'       => $this->to_minor( $payment->amount, $payment->currency ),
			'currency'     => $payment->currency,
			'reference'    => $payment->reference,
			'callback_url' => $this->callback_url( $payment ),
			'metadata'     => array(
				'purpose'       => $payment->purpose,
				'partner_id'    => (int) $payment->partner_id,
				'custom_fields' => array(
					array( 'display_name' => 'Name', 'variable_name' => 'name', 'value' => $payment->name ),
					array( 'display_name' => 'Purpose', 'variable_name' => 'purpose', 'value' => $payment->purpose ),
				),
			),
		);
		$channels = array_filter( array_map( 'trim', explode( ',', (string) $this->opt( 'channels' ) ) ) );
		if ( $channels ) {
			$body['channels'] = array_values( $channels );
		}
		$res = $this->http( 'POST', 'https://api.paystack.co/transaction/initialize', array( 'headers' => $this->headers(), 'body' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$url = kcs_array_get( $res, 'data.authorization_url' );
		if ( ! $url ) {
			return new WP_Error( 'kcs_paystack', __( 'Could not start Paystack checkout.', 'kingdom-church-suite' ) );
		}
		return array( 'redirect_url' => $url, 'txn_id' => (string) kcs_array_get( $res, 'data.access_code' ) );
	}

	/**
	 * Verify.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		$res = $this->http( 'GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode( $payment->reference ), array( 'headers' => $this->headers(), 'allow_errors' => true ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status   = kcs_array_get( $res, 'data.status' );
		$currency = (string) kcs_array_get( $res, 'data.currency' );
		if ( 'success' === $status ) {
			return array(
				'status'   => 'completed',
				'txn_id'   => (string) kcs_array_get( $res, 'data.id' ),
				'amount'   => $this->from_minor( (int) kcs_array_get( $res, 'data.amount' ), $currency ),
				'currency' => $currency,
			);
		}
		return array( 'status' => in_array( $status, array( 'failed', 'abandoned', 'reversed' ), true ) ? 'failed' : 'pending' );
	}

	/**
	 * Webhook: HMAC-SHA512 of raw body with the secret key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$sig    = (string) $request->get_header( 'x-paystack-signature' );
		$expect = hash_hmac( 'sha512', $request->get_body(), (string) $this->opt( 'secret_key' ) );
		if ( ! $sig || ! hash_equals( $expect, $sig ) ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$body = $request->get_json_params();
		return array( 'reference' => (string) kcs_array_get( $body, 'data.reference' ), 'params' => array() );
	}
}
