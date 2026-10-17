<?php
/**
 * Offline bank transfer / pledge (confirmed manually by an admin).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bank transfer gateway.
 */
class KCS_Gateway_Bank_Transfer extends KCS_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'bank_transfer';
		$this->title = __( 'Bank Transfer / Offline', 'kingdom-church-suite' );
	}

	/**
	 * Fields.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'instructions' => array(
				'label'   => __( 'Instructions', 'kingdom-church-suite' ),
				'type'    => 'editor',
				'default' => '<p><strong>Bank:</strong> Your Bank<br><strong>Account name:</strong> Your Church<br><strong>Account number:</strong> 0000000000</p><p>Please use your payment reference as the transfer narration.</p>',
			),
		);
	}

	/**
	 * Initialize: just show instructions on the result page.
	 *
	 * @param object $payment Payment.
	 * @return array
	 */
	public function initialize( $payment ) {
		return array(
			'redirect_url' => add_query_arg(
				array(
					'reference' => rawurlencode( $payment->reference ),
					'kcs_offline' => 1,
				),
				kcs_page_url( 'thank_you' )
			),
		);
	}

	/**
	 * Offline payments remain pending until an admin confirms them.
	 *
	 * @param object $payment Payment.
	 * @param array  $params  Params.
	 * @return array
	 */
	public function verify( $payment, $params ) {
		return array( 'status' => 'pending' );
	}
}
