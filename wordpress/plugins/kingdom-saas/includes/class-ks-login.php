<?php
/**
 * One-time login links, so owners land signed in on their own subdomain or custom domain
 * (cookies are per domain, so a login on the platform does not carry over).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login links.
 */
class KS_Login {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'consume' ), 1 );
	}

	/**
	 * Create a single-use link that signs a user in on a site.
	 *
	 * @param int    $user_id  User.
	 * @param int    $blog_id  Site.
	 * @param string $redirect Path or URL on that site.
	 * @return string
	 */
	public static function link( $user_id, $blog_id, $redirect = '' ) {
		$token = wp_generate_password( 40, false, false );
		set_site_transient( 'ks_login_' . hash( 'sha256', $token ), array( 'user' => (int) $user_id, 'blog' => (int) $blog_id, 'to' => $redirect ), 10 * MINUTE_IN_SECONDS );
		return add_query_arg( 'ks_login', $token, trailingslashit( KS_Tenants::url( $blog_id ) ) );
	}

	/**
	 * Use a login link.
	 */
	public static function consume() {
		if ( empty( $_GET['ks_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['ks_login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key   = 'ks_login_' . hash( 'sha256', $token );
		$data  = get_site_transient( $key );
		delete_site_transient( $key );
		if ( ! $data || (int) $data['blog'] !== get_current_blog_id() || ! get_userdata( $data['user'] ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}
		if ( ! is_user_member_of_blog( $data['user'] ) && ! is_super_admin( $data['user'] ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}
		wp_set_current_user( $data['user'] );
		wp_set_auth_cookie( $data['user'], true, is_ssl() );
		$to = $data['to'] ? $data['to'] : ( function_exists( 'kcs_page_url' ) ? kcs_page_url( 'church_admin' ) : admin_url() );
		wp_safe_redirect( 0 === strpos( $to, 'http' ) ? $to : home_url( $to ) );
		exit;
	}
}
