<?php
/**
 * Flutterwave (Standard checkout, v3 API).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flutterwave gateway.
 */
class KCS_Gateway_Flutterwave extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'flutterwave';
		$this->title = 'Flutterwave';
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'public_key'  => array( 'label' => __( 'Public key', 'kingdom-church-suite' ), 'type' => 'text' ),
			'secret_key'  => array( 'label' => __( 'Secret key', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'Use your TEST secret key while testing — Flutterwave decides test/live mode from the key.', 'kingdom-church-suite' ) ),
			'secret_hash' => array( 'label' => __( 'Webhook secret hash', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'Must match "Secret hash" in Flutterwave Dashboard → Settings → Webhooks.', 'kingdom-church-suite' ) ),
			'logo'        => array( 'label' => __( 'Checkout logo URL', 'kingdom-church-suite' ), 'type' => 'url' ),
		);
	}

	/**
	 * Auth headers.
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
		$res = $this->http(
			'POST',
			'https://api.flutterwave.com/v3/payments',
			array(
				'headers' => $this->headers(),
				'body'    => array(
					'tx_ref'         => $payment->reference,
					'amount'         => (float) $payment->amount,
					'currency'       => $payment->currency,
					'redirect_url'   => $this->callback_url( $payment ),
					'customer'       => array(
						'email'       => $payment->email,
						'name'        => $payment->name,
						'phonenumber' => $payment->phone,
					),
					'customizations' => array(
						'title'       => kcs_get_option( 'church_name', get_bloginfo( 'name' ) ),
						'description' => $this->description( $payment ),
						'logo'        => $this->opt( 'logo', kcs_get_option( 'church_logo' ) ),
					),
					'meta'           => array(
						'purpose'    => $payment->purpose,
						'partner_id' => (int) $payment->partner_id,
					),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$link = kcs_array_get( $res, 'data.link' );
		if ( ! $link ) {
			return new WP_Error( 'kcs_flutterwave', __( 'Could not start Flutterwave checkout.', 'kingdom-church-suite' ) );
		}
		return array( 'redirect_url' => $link );
	}

	/**
	 * Verify by our reference.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array|WP_Error
	 */
	public function verify( $payment, $params ) {
		$res = $this->http(
			'GET',
			add_query_arg( 'tx_ref', rawurlencode( $payment->reference ), 'https://api.flutterwave.com/v3/transactions/verify_by_reference' ),
			array( 'headers' => $this->headers(), 'allow_errors' => true )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$status = kcs_array_get( $res, 'data.status' );
		if ( 'successful' === $status ) {
			return array(
				'status'   => 'completed',
				'txn_id'   => (string) kcs_array_get( $res, 'data.id' ),
				'amount'   => (float) kcs_array_get( $res, 'data.amount' ),
				'currency' => (string) kcs_array_get( $res, 'data.currency' ),
			);
		}
		$cancelled = isset( $params['status'] ) && 'cancelled' === $params['status'];
		return array( 'status' => ( 'failed' === $status || $cancelled ) ? 'failed' : 'pending' );
	}

	/**
	 * Webhook: verif-hash header must equal the configured secret hash.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function parse_webhook( $request ) {
		$hash = (string) $request->get_header( 'verif-hash' );
		$conf = (string) $this->opt( 'secret_hash' );
		if ( ! $conf || ! hash_equals( $conf, $hash ) ) {
			return new WP_Error( 'kcs_bad_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$body = $request->get_json_params();
		$ref  = kcs_array_get( $body, 'data.tx_ref' );
		if ( ! $ref ) {
			$ref = kcs_array_get( $body, 'txRef' );
		}
		return array( 'reference' => (string) $ref, 'params' => array() );
	}
}
