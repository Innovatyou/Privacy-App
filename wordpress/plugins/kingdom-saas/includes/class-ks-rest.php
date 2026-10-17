<?php
/**
 * REST endpoints (namespace ks/v1, served by the platform site).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
class KS_REST {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$public = '__return_true';
		register_rest_route( 'ks/v1', '/plans', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'plans' ), 'permission_callback' => $public ) );
		register_rest_route( 'ks/v1', '/subdomain', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'subdomain' ), 'permission_callback' => $public ) );
		register_rest_route( 'ks/v1', '/billing/return', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'billing_return' ), 'permission_callback' => $public ) );
		register_rest_route( 'ks/v1', '/webhooks/paystack', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'paystack' ), 'permission_callback' => $public ) );
		register_rest_route( 'ks/v1', '/webhooks/stripe', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'stripe' ), 'permission_callback' => $public ) );
		register_rest_route( 'ks/v1', '/tls', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'tls' ), 'permission_callback' => $public ) );
	}

	/**
	 * GET /plans.
	 *
	 * @return WP_REST_Response
	 */
	public static function plans() {
		$out = array();
		foreach ( KS_Plans::all() as $id => $p ) {
			$out[] = array(
				'id'            => $id,
				'name'          => $p['name'],
				'prices'        => array(
					'international' => array( 'month' => KS_Plans::price( $id, 'month', 'international' ), 'year' => KS_Plans::price( $id, 'year', 'international' ) ),
					'local'         => array( 'month' => KS_Plans::price( $id, 'month', 'local' ), 'year' => KS_Plans::price( $id, 'year', 'local' ) ),
				),
				'max_partners'  => (int) $p['max_partners'],
				'custom_domain' => (bool) $p['custom_domain'],
				'white_label'   => (bool) $p['white_label'],
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET /subdomain?name= — live availability check on the sign-up form.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function subdomain( $req ) {
		$name   = strtolower( sanitize_text_field( (string) $req['name'] ) );
		$domain = KS_Provisioner::validate_subdomain( $name );
		return rest_ensure_response(
			array(
				'available' => ! is_wp_error( $domain ),
				'domain'    => is_wp_error( $domain ) ? $name . '.' . ks_get( 'base_domain' ) : $domain,
				'message'   => is_wp_error( $domain ) ? $domain->get_error_message() : __( 'Available', 'kingdom-saas' ),
			)
		);
	}

	/**
	 * GET /billing/return — the gateway sends the payer back here.
	 *
	 * @param WP_REST_Request $req Request.
	 */
	public static function billing_return( $req ) {
		$inv = KS_Billing::invoice( sanitize_text_field( (string) $req['reference'] ) );
		if ( ! $inv ) {
			wp_safe_redirect( ks_platform_url() );
			exit;
		}
		$paid = KS_Billing::verify( $inv, array( 'session_id' => sanitize_text_field( (string) $req['session_id'] ) ) );
		$to   = add_query_arg( array( 'view' => 'subscription', 'ks_paid' => $paid ? 1 : 0 ), KS_Tenants::admin_url( $inv->blog_id ) );
		add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'allow_site_host' ) );
		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Let redirects reach church domains.
	 *
	 * @param array $hosts Hosts.
	 * @return array
	 */
	public static function allow_site_host( $hosts ) {
		global $wpdb;
		return array_merge( $hosts, $wpdb->get_col( "SELECT domain FROM {$wpdb->blogs}" ) );
	}

	/**
	 * POST /webhooks/paystack.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function paystack( $req ) {
		$ok = KS_Billing::paystack_webhook( $req->get_body(), (string) $req->get_header( 'x-paystack-signature' ) );
		return new WP_REST_Response( array( 'ok' => $ok ), $ok ? 200 : 401 );
	}

	/**
	 * POST /webhooks/stripe.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function stripe( $req ) {
		$ok = KS_Billing::stripe_webhook( $req->get_body(), (string) $req->get_header( 'stripe-signature' ) );
		return new WP_REST_Response( array( 'ok' => $ok ), $ok ? 200 : 400 );
	}

	/**
	 * GET /tls?domain= — Caddy on-demand TLS "ask" endpoint.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function tls( $req ) {
		$ok = KS_Domains::tls_allowed( (string) $req['domain'] );
		return new WP_REST_Response( array( 'allowed' => $ok ), $ok ? 200 : 404 );
	}
}
