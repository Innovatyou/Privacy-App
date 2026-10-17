<?php
/**
 * Plan limits and branding on church sites.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limits.
 */
class KS_Limits {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'kcs_can_create_partner', array( __CLASS__, 'partner_limit' ), 10, 2 );
		add_filter( 'kingdom_footer_credit', array( __CLASS__, 'credit' ) );
	}

	/**
	 * Stop new partners once the plan's limit is reached.
	 *
	 * @param true|WP_Error $allowed Allowed.
	 * @return true|WP_Error
	 */
	public static function partner_limit( $allowed ) {
		$sub = KS_Tenants::current();
		if ( ! $sub || is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$max = (int) KS_Plans::feature( $sub->plan, 'max_partners' );
		if ( $max > 0 && KS_Tenants::partner_count( $sub->blog_id ) >= $max ) {
			if ( get_site_transient( 'ks_limit_mail_' . $sub->blog_id ) === false ) {
				set_site_transient( 'ks_limit_mail_' . $sub->blog_id, 1, DAY_IN_SECONDS );
				/* translators: %d: limit */
				KS_Email::owner( $sub, __( 'You have reached your partner limit', 'kingdom-saas' ), '<p>' . esc_html( sprintf( __( 'Your plan allows %d partners and someone just tried to join. Upgrade your plan so new partners can sign up.', 'kingdom-saas' ), $max ) ) . '</p>', array( __( 'Upgrade plan', 'kingdom-saas' ), KS_Tenants::admin_url( $sub->blog_id, 'subscription' ) ) );
			}
			return current_user_can( 'manage_kcs' )
				/* translators: %d: limit */
				? new WP_Error( 'ks_limit', sprintf( __( 'Your plan allows up to %d partners. Upgrade under Church Admin → Subscription to add more.', 'kingdom-saas' ), $max ) )
				: new WP_Error( 'ks_limit', __( 'Partner sign-up is temporarily unavailable. Please contact the church office.', 'kingdom-saas' ) );
		}
		return $allowed;
	}

	/**
	 * "Powered by" footer credit unless the plan is white-label.
	 *
	 * @param string $credit Credit.
	 * @return string
	 */
	public static function credit( $credit ) {
		$sub = KS_Tenants::current();
		if ( ! $sub || KS_Plans::feature( $sub->plan, 'white_label' ) ) {
			return $credit;
		}
		/* translators: %s: platform name with link */
		return $credit . ' · ' . sprintf( __( 'Powered by %s', 'kingdom-saas' ), '<a href="' . esc_url( ks_platform_url() ) . '" rel="noopener">' . esc_html( ks_get( 'platform_name' ) ) . '</a>' );
	}
}
