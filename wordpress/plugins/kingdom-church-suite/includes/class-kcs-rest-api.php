<?php
/**
 * REST API (namespace kcs/v1).
 *
 * Authentication for admin endpoints:
 *  - logged-in users with the manage_kcs capability (cookie + X-WP-Nonce, or Application Passwords), or
 *  - the X-KCS-API-Key header matching the key in Settings → REST API.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API.
 */
class KCS_REST_API {

	const NS = 'kcs/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
	}

	/* ---------------------------------------------------------------------
	 * Permissions
	 * ------------------------------------------------------------------- */

	/**
	 * Valid API key in request?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function has_api_key( $request ) {
		$key  = (string) $request->get_header( 'x-kcs-api-key' );
		$conf = (string) kcs_get_option( 'api_key' );
		return $key && $conf && strlen( $conf ) >= 20 && hash_equals( $conf, $key );
	}

	/**
	 * Admin permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function can_manage( $request ) {
		if ( current_user_can( 'manage_kcs' ) || self::has_api_key( $request ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do that.', 'kingdom-church-suite' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Partner permission.
	 *
	 * @return bool|WP_Error
	 */
	public static function is_partner() {
		if ( is_user_logged_in() && KCS_Partners::get_by_user( get_current_user_id() ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'Partner login required.', 'kingdom-church-suite' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * CORS headers for configured origins.
	 *
	 * @param bool             $served  Served.
	 * @param WP_HTTP_Response $result  Result.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public static function cors( $served, $result, $request, $server ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 0 !== strpos( $request->get_route(), '/' . self::NS ) ) {
			return $served;
		}
		$origin  = get_http_origin();
		$allowed = array_filter( array_map( 'trim', explode( ',', (string) kcs_get_option( 'api_cors' ) ) ) );
		if ( $origin && in_array( $origin, $allowed, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-KCS-API-Key' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Vary: Origin' );
		}
		return $served;
	}

	/* ---------------------------------------------------------------------
	 * Routes
	 * ------------------------------------------------------------------- */

	/**
	 * Register routes.
	 */
	public static function routes() {
		$public  = '__return_true';
		$manage  = array( __CLASS__, 'can_manage' );
		$partner = array( __CLASS__, 'is_partner' );
		$id      = '(?P<id>\d+)';

		$routes = array(
			// Public.
			array( '/plans', 'GET', 'get_plans', $public ),
			array( '/gateways', 'GET', 'get_gateways', $public ),
			array( '/payments/initialize', 'POST', 'initialize_payment', $public ),
			array( '/payments/callback/(?P<gateway>[a-z0-9_-]+)', 'GET,POST', 'payment_callback', $public ),
			array( '/payments/status', 'GET', 'payment_status', $public ),
			array( '/webhooks/(?P<gateway>[a-z0-9_-]+)', 'POST', 'webhook', $public ),
			array( '/subscribe', 'POST', 'subscribe', $public ),
			array( '/unsubscribe', 'POST', 'unsubscribe', $public ),
			array( '/live', 'GET', 'live', $public ),
			array( '/services/next', 'GET', 'next_service', $public ),
			array( '/verse', 'GET', 'verse', $public ),
			array( '/prayer-requests', 'POST', 'create_prayer', $public ),
			array( '/prayer-requests/' . $id . '/pray', 'POST', 'pray', $public ),
			array( '/prayer-wall', 'GET', 'prayer_wall', $public ),

			// Partner (self-service).
			array( '/me', 'GET', 'me', $partner ),
			array( '/me', 'PUT,PATCH', 'update_me', $partner ),
			array( '/me/payments', 'GET', 'my_payments', $partner ),

			// Admin.
			array( '/stats', 'GET', 'stats', $manage ),
			array( '/partners', 'GET', 'list_partners', $manage ),
			array( '/partners', 'POST', 'create_partner', $manage ),
			array( '/partners/' . $id, 'GET', 'get_partner', $manage ),
			array( '/partners/' . $id, 'PUT,PATCH', 'update_partner', $manage ),
			array( '/partners/' . $id, 'DELETE', 'delete_partner', $manage ),
			array( '/partners/' . $id . '/payments', 'GET', 'partner_payments', $manage ),
			array( '/payments', 'GET', 'list_payments', $manage ),
			array( '/payments/' . $id, 'GET', 'get_payment', $manage ),
			array( '/payments/' . $id . '/complete', 'POST', 'complete_payment', $manage ),
			array( '/payments/' . $id . '/verify', 'POST', 'reverify_payment', $manage ),
			array( '/plans', 'POST', 'create_plan', $manage ),
			array( '/plans/' . $id, 'PUT,PATCH', 'update_plan', $manage ),
			array( '/plans/' . $id, 'DELETE', 'delete_plan', $manage ),
			array( '/notifications/email', 'POST', 'send_notification', $manage ),
			array( '/subscribers', 'GET', 'list_subscribers', $manage ),
			array( '/subscribers', 'POST', 'add_subscriber', $manage ),
			array( '/subscribers/' . $id, 'DELETE', 'delete_subscriber', $manage ),
			array( '/campaigns', 'GET', 'list_campaigns', $manage ),
			array( '/campaigns', 'POST', 'create_campaign', $manage ),
			array( '/campaigns/' . $id, 'GET', 'get_campaign', $manage ),
			array( '/campaigns/' . $id . '/send', 'POST', 'send_campaign', $manage ),
			array( '/prayer-requests', 'GET', 'list_prayers', $manage ),
			array( '/prayer-requests/' . $id, 'PUT,PATCH', 'update_prayer', $manage ),
		);

		foreach ( $routes as $r ) {
			register_rest_route(
				self::NS,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( __CLASS__, $r[2] ),
					'permission_callback' => $r[3],
				),
				false
			);
		}
	}

	/**
	 * Pagination helper.
	 *
	 * @param array           $result items/total.
	 * @param callable        $map    Mapper.
	 * @param WP_REST_Request $req    Request.
	 * @return WP_REST_Response
	 */
	private static function paged( $result, $map, $req ) {
		$per      = max( 1, min( 100, (int) ( $req['per_page'] ? $req['per_page'] : 20 ) ) );
		$response = rest_ensure_response( array_map( $map, $result['items'] ) );
		$response->header( 'X-WP-Total', (int) $result['total'] );
		$response->header( 'X-WP-TotalPages', (int) ceil( $result['total'] / $per ) );
		return $response;
	}

	/* ---------------------------------------------------------------------
	 * Public endpoints
	 * ------------------------------------------------------------------- */

	/**
	 * GET /plans.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_plans() {
		return rest_ensure_response( array_map( array( 'KCS_Plans', 'to_array' ), KCS_Plans::all( true ) ) );
	}

	/**
	 * GET /gateways.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function get_gateways( $req ) {
		$out = array();
		foreach ( KCS_Payments::enabled_gateways( strtoupper( (string) $req['currency'] ) ) as $g ) {
			$out[] = array( 'id' => $g->id, 'title' => $g->display_title() );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * POST /payments/initialize.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function initialize_payment( $req ) {
		if ( ! empty( $req['website'] ) ) {
			return new WP_Error( 'kcs_spam', 'Rejected', array( 'status' => 400 ) );
		}
		if ( ! kcs_rate_limit( 'pay', 20, 600 ) ) {
			return new WP_Error( 'kcs_rate', __( 'Too many attempts. Please wait a few minutes.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
		}
		$args = array(
			'gateway'  => sanitize_key( $req['gateway'] ),
			'amount'   => (float) $req['amount'],
			'currency' => strtoupper( sanitize_text_field( (string) $req['currency'] ) ),
			'purpose'  => sanitize_key( $req['purpose'] ? $req['purpose'] : 'donation' ),
			'name'     => sanitize_text_field( (string) $req['name'] ),
			'email'    => sanitize_email( (string) $req['email'] ),
			'phone'    => sanitize_text_field( (string) $req['phone'] ),
			'note'     => sanitize_textarea_field( (string) $req['note'] ),
		);
		if ( ! in_array( $args['currency'], array_keys( KCS_Payments::allowed_currencies() ), true ) ) {
			$args['currency'] = kcs_get_option( 'currency', 'USD' );
		}

		$partner = is_user_logged_in() ? KCS_Partners::get_by_user( get_current_user_id() ) : null;
		if ( $partner && ( 'partnership' === $args['purpose'] || $req['as_partner'] ) ) {
			$args['purpose']    = 'partnership';
			$args['partner_id'] = $partner->id;
			$args['plan_id']    = $partner->plan_id;
			$args['email']      = $args['email'] ? $args['email'] : $partner->email;
			$args['name']       = $args['name'] ? $args['name'] : trim( $partner->first_name . ' ' . $partner->last_name );
			$args['phone']      = $args['phone'] ? $args['phone'] : $partner->phone;
		} elseif ( 'partnership' === $args['purpose'] ) {
			// Guests can sow a partnership seed, but it is not tied to a partner record.
			$args['purpose'] = 'partnership';
		}
		$purposes = kcs_giving_purposes();
		if ( ! isset( $purposes[ $args['purpose'] ] ) && ! in_array( $args['purpose'], array( 'partnership', 'donation' ), true ) ) {
			$args['purpose'] = 'donation';
		}

		$result = KCS_Payments::start( $args );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * GET /payments/callback/{gateway} — the giver returns here from the provider.
	 *
	 * @param WP_REST_Request $req Request.
	 */
	public static function payment_callback( $req ) {
		$params = $req->get_params();
		$ref    = '';
		foreach ( array( 'reference', 'tx_ref', 'trxref', 'razorpay_payment_link_reference_id' ) as $k ) {
			if ( ! empty( $params[ $k ] ) && is_string( $params[ $k ] ) ) {
				$ref = sanitize_text_field( $params[ $k ] );
				break;
			}
		}
		$payment = $ref ? KCS_Payments::get_by_reference( $ref ) : null;
		$status  = 'failed';
		if ( $payment && $payment->gateway === $req['gateway'] ) {
			$clean = array();
			foreach ( $params as $k => $v ) {
				if ( is_scalar( $v ) ) {
					$clean[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
				}
			}
			$status = KCS_Payments::verify_and_update( $payment, $clean );
		}
		$url = add_query_arg(
			array(
				'reference'  => rawurlencode( $ref ),
				'kcs_status' => $status,
			),
			kcs_page_url( 'thank_you' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * GET /payments/status?reference=.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function payment_status( $req ) {
		$payment = KCS_Payments::get_by_reference( sanitize_text_field( (string) $req['reference'] ) );
		if ( ! $payment ) {
			return new WP_Error( 'kcs_not_found', 'Not found', array( 'status' => 404 ) );
		}
		if ( 'pending' === $payment->status && 'bank_transfer' !== $payment->gateway ) {
			KCS_Payments::verify_and_update( $payment );
			$payment = KCS_Payments::get( $payment->id );
		}
		return rest_ensure_response(
			array(
				'reference' => $payment->reference,
				'status'    => $payment->status,
				'amount'    => (float) $payment->amount,
				'currency'  => $payment->currency,
			)
		);
	}

	/**
	 * POST /webhooks/{gateway}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function webhook( $req ) {
		$gateway = KCS_Payments::gateway( $req['gateway'] );
		if ( ! $gateway || ! $gateway->is_enabled() ) {
			return new WP_Error( 'kcs_gateway', 'Unknown gateway', array( 'status' => 404 ) );
		}
		$parsed = $gateway->parse_webhook( $req );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$payment = $parsed['reference'] ? KCS_Payments::get_by_reference( $parsed['reference'] ) : null;
		if ( ! $payment || $payment->gateway !== $gateway->id ) {
			return rest_ensure_response( array( 'received' => true, 'ignored' => true ) );
		}
		$status = KCS_Payments::verify_and_update( $payment, $parsed['params'] );
		do_action( 'kcs_webhook_processed', $gateway->id, $payment, $status );
		return rest_ensure_response( array( 'received' => true, 'status' => $status ) );
	}

	/**
	 * POST /subscribe.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function subscribe( $req ) {
		if ( ! empty( $req['website'] ) ) {
			return rest_ensure_response( array( 'status' => 'subscribed', 'message' => __( 'Thank you!', 'kingdom-church-suite' ) ) );
		}
		if ( ! kcs_rate_limit( 'subscribe', 10, 3600 ) ) {
			return new WP_Error( 'kcs_rate', __( 'Too many attempts.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
		}
		$status = KCS_Newsletter::subscribe( (string) $req['email'], (string) $req['name'], $req['source'] ? sanitize_key( $req['source'] ) : 'website' );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$messages = array(
			'pending'    => __( 'Almost there! Please check your inbox to confirm your subscription.', 'kingdom-church-suite' ),
			'subscribed' => __( 'Thank you for subscribing! 🎉', 'kingdom-church-suite' ),
			'exists'     => __( 'You are already subscribed. Thank you!', 'kingdom-church-suite' ),
		);
		return rest_ensure_response( array( 'status' => $status, 'message' => $messages[ $status ] ) );
	}

	/**
	 * POST /unsubscribe.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function unsubscribe( $req ) {
		$ok = KCS_Newsletter::unsubscribe( sanitize_text_field( (string) $req['token'] ) );
		return $ok ? rest_ensure_response( array( 'status' => 'unsubscribed' ) ) : new WP_Error( 'kcs_not_found', 'Invalid token', array( 'status' => 404 ) );
	}

	/**
	 * GET /live.
	 *
	 * @return WP_REST_Response
	 */
	public static function live() {
		$status = KCS_Livestream::status();
		$res    = rest_ensure_response( $status );
		$res->header( 'Cache-Control', 'public, max-age=60' );
		return $res;
	}

	/**
	 * GET /services/next.
	 *
	 * @return WP_REST_Response
	 */
	public static function next_service() {
		return rest_ensure_response( array( 'next' => KCS_Livestream::next_service(), 'schedule' => KCS_Livestream::schedule() ) );
	}

	/**
	 * GET /verse.
	 *
	 * @return WP_REST_Response
	 */
	public static function verse() {
		return rest_ensure_response( KCS_Features::verse_of_the_day() );
	}

	/**
	 * POST /prayer-requests.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_prayer( $req ) {
		if ( ! empty( $req['website'] ) ) {
			return rest_ensure_response( array( 'id' => 0 ) );
		}
		if ( ! kcs_rate_limit( 'prayer', 5, 3600 ) ) {
			return new WP_Error( 'kcs_rate', __( 'Too many requests. Please try again later.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
		}
		$id = KCS_Prayer::create( $req->get_params() );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return rest_ensure_response( array( 'id' => $id, 'message' => __( 'Thank you. Our prayer team is standing with you. 🙏', 'kingdom-church-suite' ) ) );
	}

	/**
	 * POST /prayer-requests/{id}/pray.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function pray( $req ) {
		if ( ! kcs_rate_limit( 'pray_' . (int) $req['id'], 1, DAY_IN_SECONDS ) ) {
			return new WP_Error( 'kcs_rate', __( 'Thank you for praying!', 'kingdom-church-suite' ), array( 'status' => 429 ) );
		}
		$count = KCS_Prayer::pray( (int) $req['id'] );
		return is_wp_error( $count ) ? $count : rest_ensure_response( array( 'count' => $count ) );
	}

	/**
	 * GET /prayer-wall.
	 *
	 * @return WP_REST_Response
	 */
	public static function prayer_wall() {
		return rest_ensure_response( KCS_Prayer::wall( 50 ) );
	}

	/* ---------------------------------------------------------------------
	 * Partner endpoints
	 * ------------------------------------------------------------------- */

	/**
	 * GET /me.
	 *
	 * @return WP_REST_Response
	 */
	public static function me() {
		return rest_ensure_response( KCS_Partners::to_array( KCS_Partners::get_by_user( get_current_user_id() ) ) );
	}

	/**
	 * PUT /me (limited fields).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function update_me( $req ) {
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		$allowed = array_intersect_key( $req->get_params(), array_flip( array( 'first_name', 'last_name', 'phone', 'country', 'city', 'address', 'email_opt_in' ) ) );
		KCS_Partners::update( $partner->id, $allowed );
		return rest_ensure_response( KCS_Partners::to_array( KCS_Partners::get( $partner->id ) ) );
	}

	/**
	 * GET /me/payments.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function my_payments( $req ) {
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		$result  = KCS_Payments::query( array( 'partner_id' => $partner->id, 'page' => (int) ( $req['page'] ? $req['page'] : 1 ), 'per_page' => (int) ( $req['per_page'] ? $req['per_page'] : 20 ) ) );
		return self::paged( $result, array( 'KCS_Payments', 'to_array' ), $req );
	}

	/* ---------------------------------------------------------------------
	 * Admin endpoints
	 * ------------------------------------------------------------------- */

	/**
	 * GET /stats.
	 *
	 * @return WP_REST_Response
	 */
	public static function stats() {
		return rest_ensure_response( KCS_Partners::stats() );
	}

	/**
	 * GET /partners.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function list_partners( $req ) {
		$result = KCS_Partners::query(
			array(
				'search'   => sanitize_text_field( (string) $req['search'] ),
				'status'   => sanitize_key( (string) $req['status'] ),
				'plan_id'  => (int) $req['plan_id'],
				'page'     => (int) ( $req['page'] ? $req['page'] : 1 ),
				'per_page' => max( 1, min( 100, (int) ( $req['per_page'] ? $req['per_page'] : 20 ) ) ),
				'orderby'  => sanitize_key( (string) $req['orderby'] ),
				'order'    => sanitize_key( (string) $req['order'] ),
			)
		);
		return self::paged( $result, array( 'KCS_Partners', 'to_array' ), $req );
	}

	/**
	 * POST /partners.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_partner( $req ) {
		$id = KCS_Partners::create( $req->get_params(), false !== $req['create_user'] && 'false' !== $req['create_user'] );
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );
			return $id;
		}
		$res = rest_ensure_response( KCS_Partners::to_array( KCS_Partners::get( $id ) ) );
		$res->set_status( 201 );
		return $res;
	}

	/**
	 * GET /partners/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_partner( $req ) {
		$p = KCS_Partners::get( (int) $req['id'] );
		return $p ? rest_ensure_response( KCS_Partners::to_array( $p ) ) : new WP_Error( 'kcs_not_found', 'Partner not found', array( 'status' => 404 ) );
	}

	/**
	 * PUT /partners/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_partner( $req ) {
		$id = (int) $req['id'];
		if ( ! KCS_Partners::get( $id ) ) {
			return new WP_Error( 'kcs_not_found', 'Partner not found', array( 'status' => 404 ) );
		}
		KCS_Partners::update( $id, $req->get_params() );
		return rest_ensure_response( KCS_Partners::to_array( KCS_Partners::get( $id ) ) );
	}

	/**
	 * DELETE /partners/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function delete_partner( $req ) {
		KCS_Partners::delete( (int) $req['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * GET /partners/{id}/payments.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function partner_payments( $req ) {
		$result = KCS_Payments::query( array( 'partner_id' => (int) $req['id'], 'per_page' => 100 ) );
		return self::paged( $result, array( 'KCS_Payments', 'to_array' ), $req );
	}

	/**
	 * GET /payments.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function list_payments( $req ) {
		$result = KCS_Payments::query(
			array(
				'search'    => sanitize_text_field( (string) $req['search'] ),
				'status'    => sanitize_key( (string) $req['status'] ),
				'gateway'   => sanitize_key( (string) $req['gateway'] ),
				'purpose'   => sanitize_key( (string) $req['purpose'] ),
				'date_from' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $req['date_from'] ) ? $req['date_from'] : '',
				'date_to'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $req['date_to'] ) ? $req['date_to'] : '',
				'page'      => (int) ( $req['page'] ? $req['page'] : 1 ),
				'per_page'  => max( 1, min( 100, (int) ( $req['per_page'] ? $req['per_page'] : 20 ) ) ),
			)
		);
		return self::paged( $result, array( 'KCS_Payments', 'to_array' ), $req );
	}

	/**
	 * GET /payments/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_payment( $req ) {
		$p = KCS_Payments::get( (int) $req['id'] );
		return $p ? rest_ensure_response( KCS_Payments::to_array( $p ) ) : new WP_Error( 'kcs_not_found', 'Payment not found', array( 'status' => 404 ) );
	}

	/**
	 * POST /payments/{id}/complete (manual confirmation, e.g. bank transfer).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function complete_payment( $req ) {
		$p = KCS_Payments::get( (int) $req['id'] );
		if ( ! $p ) {
			return new WP_Error( 'kcs_not_found', 'Payment not found', array( 'status' => 404 ) );
		}
		$status = KCS_Payments::complete( $p, sanitize_text_field( (string) $req['txn_id'] ), (float) $p->amount, $p->currency, true );
		return rest_ensure_response( array( 'status' => $status ) );
	}

	/**
	 * POST /payments/{id}/verify.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reverify_payment( $req ) {
		$p = KCS_Payments::get( (int) $req['id'] );
		if ( ! $p ) {
			return new WP_Error( 'kcs_not_found', 'Payment not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'status' => KCS_Payments::verify_and_update( $p ) ) );
	}

	/**
	 * POST /plans.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function create_plan( $req ) {
		$params = wp_parse_args( $req->get_params(), array( 'is_active' => 1 ) );
		$id     = KCS_Plans::save( $params );
		$res    = rest_ensure_response( KCS_Plans::to_array( KCS_Plans::get( $id ) ) );
		$res->set_status( 201 );
		return $res;
	}

	/**
	 * PUT /plans/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_plan( $req ) {
		$plan = KCS_Plans::get( (int) $req['id'] );
		if ( ! $plan ) {
			return new WP_Error( 'kcs_not_found', 'Plan not found', array( 'status' => 404 ) );
		}
		KCS_Plans::save( array_merge( (array) $plan, $req->get_params() ), $plan->id );
		return rest_ensure_response( KCS_Plans::to_array( KCS_Plans::get( $plan->id ) ) );
	}

	/**
	 * DELETE /plans/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function delete_plan( $req ) {
		KCS_Plans::delete( (int) $req['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * POST /notifications/email — email partners or any list of addresses.
	 *
	 * Body: subject, message (HTML, supports {name} {first_name} {unsubscribe_url}),
	 * and one of: audience (subscribers|partners|partners_active|partners_overdue|everyone),
	 * partner_ids[], emails[]. Optional send_now=true to send the first batch immediately.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function send_notification( $req ) {
		$subject = sanitize_text_field( (string) $req['subject'] );
		$message = wp_kses_post( (string) $req['message'] );
		if ( ! $subject || ! $message ) {
			return new WP_Error( 'kcs_invalid', __( 'Subject and message are required.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$count = 0;
		if ( is_array( $req['emails'] ) && $req['emails'] ) {
			foreach ( $req['emails'] as $email ) {
				$email = sanitize_email( $email );
				if ( is_email( $email ) ) {
					$body = KCS_Email::replace( $message, array( 'name' => '', 'first_name' => '', 'email' => $email, 'unsubscribe_url' => '' ) );
					KCS_Email::queue( $email, $subject, KCS_Email::wrap( $subject, $body ) );
					++$count;
				}
			}
		} else {
			$ids      = is_array( $req['partner_ids'] ) ? array_map( 'intval', $req['partner_ids'] ) : array();
			$audience = $req['audience'] ? sanitize_key( $req['audience'] ) : 'partners';
			if ( $ids ) {
				$audience = 'partners';
			}
			if ( ! array_key_exists( $audience, KCS_Newsletter::audiences() ) ) {
				return new WP_Error( 'kcs_invalid', 'Invalid audience', array( 'status' => 400 ) );
			}
			$count = KCS_Newsletter::queue_message( $audience, $subject, $message, '', 0, $ids );
		}
		if ( $req['send_now'] ) {
			KCS_Email::process_queue();
		} else {
			wp_schedule_single_event( time() + 5, 'kcs_process_email_queue' );
		}
		return rest_ensure_response( array( 'queued' => $count ) );
	}

	/**
	 * GET /subscribers.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function list_subscribers( $req ) {
		global $wpdb;
		$table  = kcs_table( 'subscribers' );
		$per    = max( 1, min( 100, (int) ( $req['per_page'] ? $req['per_page'] : 20 ) ) );
		$offset = ( max( 1, (int) $req['page'] ) - 1 ) * $per;
		$status = sanitize_key( (string) $req['status'] );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $status ) {
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT id, email, name, status, source, created_at, confirmed_at FROM $table WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $per, $offset ) );
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status ) );
		} else {
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT id, email, name, status, source, created_at, confirmed_at FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per, $offset ) );
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		}
		// phpcs:enable
		return self::paged( array( 'items' => $items, 'total' => $total ), function ( $r ) {
			return $r;
		}, $req );
	}

	/**
	 * POST /subscribers.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_subscriber( $req ) {
		$status = KCS_Newsletter::subscribe( (string) $req['email'], (string) $req['name'], 'api', ! $req['confirm'] );
		return is_wp_error( $status ) ? $status : rest_ensure_response( array( 'status' => $status ) );
	}

	/**
	 * DELETE /subscribers/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function delete_subscriber( $req ) {
		global $wpdb;
		$wpdb->delete( kcs_table( 'subscribers' ), array( 'id' => (int) $req['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * GET /campaigns.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_campaigns() {
		global $wpdb;
		return rest_ensure_response( $wpdb->get_results( 'SELECT id, subject, audience, status, total, sent_count, scheduled_at, created_at, sent_at FROM ' . kcs_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 100' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * POST /campaigns.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function create_campaign( $req ) {
		$id  = KCS_Newsletter::save_campaign( $req->get_params() );
		$res = rest_ensure_response( KCS_Newsletter::get_campaign( $id ) );
		$res->set_status( 201 );
		return $res;
	}

	/**
	 * GET /campaigns/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_campaign( $req ) {
		$c = KCS_Newsletter::get_campaign( (int) $req['id'] );
		return $c ? rest_ensure_response( $c ) : new WP_Error( 'kcs_not_found', 'Campaign not found', array( 'status' => 404 ) );
	}

	/**
	 * POST /campaigns/{id}/send.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function send_campaign( $req ) {
		$count = KCS_Newsletter::send_campaign( (int) $req['id'] );
		return is_wp_error( $count ) ? $count : rest_ensure_response( array( 'queued' => $count ) );
	}

	/**
	 * GET /prayer-requests (admin).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function list_prayers( $req ) {
		global $wpdb;
		$per   = max( 1, min( 100, (int) ( $req['per_page'] ? $req['per_page'] : 20 ) ) );
		$table = kcs_table( 'prayer_requests' );
		return rest_ensure_response( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $per ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * PUT /prayer-requests/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_prayer( $req ) {
		global $wpdb;
		$status = sanitize_key( (string) $req['status'] );
		if ( ! array_key_exists( $status, KCS_Prayer::statuses() ) ) {
			return new WP_Error( 'kcs_invalid', 'Invalid status', array( 'status' => 400 ) );
		}
		$wpdb->update( kcs_table( 'prayer_requests' ), array( 'status' => $status ), array( 'id' => (int) $req['id'] ) );
		return rest_ensure_response( array( 'updated' => true ) );
	}
}
