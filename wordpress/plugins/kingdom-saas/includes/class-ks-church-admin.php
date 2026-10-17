<?php
/**
 * "Subscription" and "Custom domain" screens inside each church's Church Admin dashboard.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Church-side billing screens.
 */
class KS_Church_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( is_main_site() ) {
			return;
		}
		add_filter( 'kcs_church_admin_views', array( __CLASS__, 'views' ) );
		add_action( 'kcs_church_admin_view_subscription', array( __CLASS__, 'view_subscription' ) );
		add_action( 'kcs_church_admin_view_domain', array( __CLASS__, 'view_domain' ) );
		add_action( 'kcs_church_admin_before_view', array( __CLASS__, 'banner' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
	}

	/**
	 * Add the views.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function views( $views ) {
		if ( ! KS_Tenants::current() ) {
			return $views;
		}
		$views['subscription'] = array( __( 'Subscription', 'kingdom-saas' ), '★' );
		$views['domain']       = array( __( 'Custom domain', 'kingdom-saas' ), '⌘' );
		return $views;
	}

	/**
	 * Styles for the screens.
	 */
	public static function assets() {
		if ( KS_Tenants::current() && wp_style_is( 'kcs-church-admin', 'enqueued' ) ) {
			wp_enqueue_style( 'ks-church', KS_URL . 'assets/css/church.css', array( 'kcs-church-admin' ), KS_VERSION );
			wp_enqueue_script( 'ks-saas', KS_URL . 'assets/js/saas.js', array(), KS_VERSION, true );
		}
	}

	/**
	 * Trial / payment-due banner on every Church Admin screen.
	 *
	 * @param string $view Current view.
	 */
	public static function banner( $view ) {
		$sub = KS_Tenants::current();
		if ( ! $sub || 'subscription' === $view ) {
			return;
		}
		$left = KS_Tenants::days_until( KS_Tenants::due_at( $sub ) );
		$url  = add_query_arg( 'view', 'subscription', kcs_page_url( 'church_admin' ) );
		if ( 'trialing' === $sub->status ) {
			/* translators: %d: days */
			$msg = sprintf( _n( 'Your free trial ends in %d day.', 'Your free trial ends in %d days.', max( 0, (int) $left ), 'kingdom-saas' ), max( 0, (int) $left ) );
		} elseif ( 'past_due' === $sub->status ) {
			$msg = __( 'Your payment is overdue. Pay now to keep your website online.', 'kingdom-saas' );
		} else {
			return;
		}
		echo '<div class="ks-banner ks-banner--' . esc_attr( $sub->status ) . '"><span>' . esc_html( $msg ) . '</span><a class="kcs-btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Choose a plan', 'kingdom-saas' ) . '</a></div>';
	}

	/**
	 * Handle the screens' forms.
	 */
	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ks_do'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
			return;
		}
		$sub = KS_Tenants::current();
		if ( ! $sub || ! current_user_can( 'manage_kcs' ) || ! isset( $_POST['_ks'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_ks'] ), 'ks_church' ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'kingdom-saas' ), 403 );
		}
		$d    = wp_unslash( $_POST );
		$do   = sanitize_key( $d['ks_do'] );
		$view = 'subscription';
		$msg  = '';
		$type = 'success';
		switch ( $do ) {
			case 'checkout':
				$url = KS_Billing::checkout( $sub->blog_id, sanitize_key( $d['plan'] ?? '' ), sanitize_key( $d['interval'] ?? 'month' ), sanitize_key( $d['region'] ?? 'international' ), sanitize_key( $d['gateway'] ?? '' ) );
				if ( ! is_wp_error( $url ) ) {
					add_filter( 'allowed_redirect_hosts', array( 'KS_REST', 'allow_site_host' ) );
					add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'allow_gateways' ) );
					wp_safe_redirect( $url );
					exit;
				}
				$msg  = $url->get_error_message();
				$type = 'error';
				break;
			case 'cancel':
				KS_Tenants::update( $sub->blog_id, array( 'cancel_at_period_end' => 1 ) );
				$msg = __( 'Your subscription will end at the close of the current period. You can resume any time before then.', 'kingdom-saas' );
				break;
			case 'resume':
				KS_Tenants::update( $sub->blog_id, array( 'cancel_at_period_end' => 0 ) );
				$msg = __( 'Your subscription will renew as normal.', 'kingdom-saas' );
				break;
			case 'domain_add':
				$view = 'domain';
				$r    = KS_Domains::add( $sub->blog_id, (string) ( $d['domain'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					$msg  = $r->get_error_message();
					$type = 'error';
				} else {
					$msg = __( 'Domain added. Add the DNS records below at your domain provider, then press “Check now”.', 'kingdom-saas' );
				}
				break;
			case 'domain_verify':
				$view = 'domain';
				$dom  = KS_Domains::get( absint( $d['id'] ?? 0 ) );
				if ( $dom && (int) $dom->blog_id === (int) $sub->blog_id ) {
					$r = KS_Domains::verify( $dom->id );
					if ( true === $r ) {
						// The site's address changed: continue on the new domain.
						wp_redirect( KS_Login::link( get_current_user_id(), $sub->blog_id, add_query_arg( array( 'view' => 'domain', 'ks_connected' => 1 ), '/' . trim( wp_parse_url( kcs_page_url( 'church_admin' ), PHP_URL_PATH ), '/' ) . '/' ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						exit;
					}
					$msg  = $r->get_error_message();
					$type = 'error';
				}
				break;
			case 'domain_remove':
				$view = 'domain';
				$dom  = KS_Domains::get( absint( $d['id'] ?? 0 ) );
				if ( $dom && (int) $dom->blog_id === (int) $sub->blog_id ) {
					$was = $dom->is_primary;
					KS_Domains::remove( $dom->id );
					if ( $was ) {
						wp_redirect( KS_Login::link( get_current_user_id(), $sub->blog_id, '/' . trim( wp_parse_url( kcs_page_url( 'church_admin' ), PHP_URL_PATH ), '/' ) . '/?view=domain' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						exit;
					}
					$msg = __( 'Domain removed.', 'kingdom-saas' );
				}
				break;
		}
		if ( $msg ) {
			set_transient( 'kcs_admin_flash_' . get_current_user_id(), array( $msg, $type ), 60 );
		}
		wp_safe_redirect( add_query_arg( 'view', $view, kcs_page_url( 'church_admin' ) ) );
		exit;
	}

	/**
	 * Allow redirects to payment pages.
	 *
	 * @param array $hosts Hosts.
	 * @return array
	 */
	public static function allow_gateways( $hosts ) {
		return array_merge( $hosts, array( 'checkout.paystack.com', 'checkout.stripe.com', wp_parse_url( network_home_url(), PHP_URL_HOST ) ) );
	}

	/**
	 * Hidden fields.
	 *
	 * @param string $do Action.
	 * @param int    $id ID.
	 * @return string
	 */
	public static function fields( $do, $id = 0 ) {
		return '<input type="hidden" name="ks_do" value="' . esc_attr( $do ) . '"><input type="hidden" name="id" value="' . (int) $id . '">' . wp_nonce_field( 'ks_church', '_ks', true, false );
	}

	/**
	 * Subscription screen.
	 */
	public static function view_subscription() {
		$sub = KS_Tenants::current();
		echo ks_template( 'church-subscription', array( 'sub' => $sub, 'fields' => array( __CLASS__, 'fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Custom domain screen.
	 */
	public static function view_domain() {
		$sub = KS_Tenants::current();
		echo ks_template( 'church-domain', array( 'sub' => $sub, 'domains' => KS_Domains::for_site( $sub->blog_id ), 'fields' => array( __CLASS__, 'fields' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
