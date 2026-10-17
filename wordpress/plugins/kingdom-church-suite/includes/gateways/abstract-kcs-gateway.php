<?php
/**
 * Base payment gateway.
 *
 * To add your own gateway, extend this class and register it:
 *
 *     add_filter( 'kcs_payment_gateways', function ( $gateways ) {
 *         $gateways['mygateway'] = new My_Gateway();
 *         return $gateways;
 *     } );
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract gateway.
 */
abstract class KCS_Gateway {

	/**
	 * Gateway ID (also used as setting prefix).
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Admin title.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Supported currencies; empty = any.
	 *
	 * @var array
	 */
	public $currencies = array();

	/**
	 * Currencies without minor units.
	 *
	 * @var array
	 */
	protected $zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	/**
	 * Gateway-specific setting fields (without prefix).
	 *
	 * @return array
	 */
	abstract protected function fields();

	/**
	 * Start a payment.
	 *
	 * @param object $payment Payment row.
	 * @return array|WP_Error ['redirect_url' => string, 'txn_id' => string(optional)].
	 */
	abstract public function initialize( $payment );

	/**
	 * Verify a payment with the provider (server to server).
	 *
	 * @param object $payment Payment row.
	 * @param array  $params  Callback/webhook parameters.
	 * @return array|WP_Error ['status' => completed|failed|pending, 'txn_id', 'amount', 'currency'].
	 */
	abstract public function verify( $payment, $params );

	/**
	 * Validate an incoming webhook and extract the payment reference.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error ['reference' => string, 'params' => array].
	 */
	public function parse_webhook( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return new WP_Error( 'kcs_webhook_unsupported', 'Webhooks not supported for this gateway.', array( 'status' => 400 ) );
	}

	/**
	 * Settings fields with prefix.
	 *
	 * @return array
	 */
	public function settings_fields() {
		$base = array(
			'enabled'       => array( 'label' => __( 'Enable', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '0' ),
			'display_title' => array( 'label' => __( 'Title shown to givers', 'kingdom-church-suite' ), 'type' => 'text', 'default' => $this->title ),
		);
		$out  = array();
		foreach ( array_merge( $base, $this->fields() ) as $k => $f ) {
			$out[ $this->id . '_' . $k ] = $f;
		}
		$out[ $this->id . '_webhook_info' ] = array(
			'label' => __( 'Webhook / callback URL', 'kingdom-church-suite' ),
			'type'  => 'info',
			'desc'  => did_action( 'init' ) ? '<code>' . esc_html( rest_url( 'kcs/v1/webhooks/' . $this->id ) ) . '</code>' : '',
		);
		return $out;
	}

	/**
	 * Read a gateway option.
	 *
	 * @param string $key     Key without prefix.
	 * @param string $default Default.
	 * @return string
	 */
	public function opt( $key, $default = '' ) {
		return kcs_get_option( $this->id . '_' . $key, $default );
	}

	/**
	 * Enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === $this->opt( 'enabled', '0' );
	}

	/**
	 * Test mode?
	 *
	 * @return bool
	 */
	public function is_test() {
		return '1' === $this->opt( 'test_mode', '0' );
	}

	/**
	 * Title displayed on checkout.
	 *
	 * @return string
	 */
	public function display_title() {
		return $this->opt( 'display_title', $this->title );
	}

	/**
	 * Does the gateway accept this currency?
	 *
	 * @param string $currency Code.
	 * @return bool
	 */
	public function supports_currency( $currency ) {
		return empty( $this->currencies ) || in_array( strtoupper( $currency ), $this->currencies, true );
	}

	/**
	 * Currencies the checkout should allow for this gateway (empty = any).
	 *
	 * @return array
	 */
	public function currency_list() {
		return $this->currencies;
	}

	/**
	 * Amount in the smallest currency unit.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @return int
	 */
	protected function to_minor( $amount, $currency ) {
		return in_array( strtoupper( $currency ), $this->zero_decimal, true ) ? (int) round( $amount ) : (int) round( $amount * 100 );
	}

	/**
	 * Amount from smallest unit.
	 *
	 * @param int    $minor    Minor units.
	 * @param string $currency Currency.
	 * @return float
	 */
	protected function from_minor( $minor, $currency ) {
		return in_array( strtoupper( $currency ), $this->zero_decimal, true ) ? (float) $minor : $minor / 100;
	}

	/**
	 * Where the provider sends the giver back to.
	 *
	 * @param object $payment Payment.
	 * @return string
	 */
	public function callback_url( $payment ) {
		return add_query_arg( 'reference', rawurlencode( $payment->reference ), rest_url( 'kcs/v1/payments/callback/' . $this->id ) );
	}

	/**
	 * Description shown on the provider's checkout.
	 *
	 * @param object $payment Payment.
	 * @return string
	 */
	protected function description( $payment ) {
		$purposes = kcs_giving_purposes();
		$purpose  = isset( $purposes[ $payment->purpose ] ) ? $purposes[ $payment->purpose ] : ucwords( str_replace( array( '-', '_' ), ' ', $payment->purpose ) );
		return sprintf( '%s — %s', $purpose, kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) );
	}

	/**
	 * JSON HTTP helper.
	 *
	 * @param string $method Method.
	 * @param string $url    URL.
	 * @param array  $args   headers, body (array → JSON unless form=true), form.
	 * @return array|WP_Error Decoded body with "_code".
	 */
	protected function http( $method, $url, $args = array() ) {
		$headers = isset( $args['headers'] ) ? $args['headers'] : array();
		$body    = null;
		if ( isset( $args['body'] ) ) {
			if ( ! empty( $args['form'] ) ) {
				$body                    = is_array( $args['body'] ) ? http_build_query( $args['body'], '', '&' ) : $args['body'];
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			} else {
				$body                    = is_string( $args['body'] ) ? $args['body'] : wp_json_encode( $args['body'] );
				$headers['Content-Type'] = 'application/json';
			}
		}
		$headers['Accept'] = 'application/json';

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 45,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			$data = array( '_raw' => wp_remote_retrieve_body( $response ) );
		}
		$data['_code'] = $code;
		if ( $code >= 400 && empty( $args['allow_errors'] ) ) {
			$msg = kcs_array_get( $data, 'message' );
			if ( ! $msg ) {
				$msg = kcs_array_get( $data, 'error.message' );
			}
			if ( ! $msg ) {
				$msg = kcs_array_get( $data, 'error.description' );
			}
			if ( ! is_string( $msg ) || ! $msg ) {
				/* translators: %d: HTTP status code */
				$msg = sprintf( __( 'Payment provider returned HTTP %d.', 'kingdom-church-suite' ), $code );
			}
			$this->log( 'HTTP ' . $code . ' ' . $url . ' ' . wp_remote_retrieve_body( $response ) );
			return new WP_Error( 'kcs_gateway_http', $msg, array( 'status' => 502 ) );
		}
		return $data;
	}

	/**
	 * Debug log (only when WP_DEBUG_LOG is enabled).
	 *
	 * @param string $message Message.
	 */
	protected function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[KCS ' . $this->id . '] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
