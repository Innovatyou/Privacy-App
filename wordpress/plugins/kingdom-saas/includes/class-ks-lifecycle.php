<?php
/**
 * Trial and renewal lifecycle, reminders and suspension.
 *
 * trialing → (trial ends) past_due → (grace days) suspended → (payment) active.
 * active → (period ends unpaid) past_due → suspended. Cancelled at period end → cancelled.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle.
 */
class KS_Lifecycle {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'ks_daily', array( __CLASS__, 'run' ) );
		add_action( 'template_redirect', array( __CLASS__, 'gate_front' ), 0 );
		add_action( 'admin_init', array( __CLASS__, 'gate_admin' ), 0 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'gate_rest' ), 99 );
	}

	/**
	 * Advance every subscription. Safe to run often.
	 *
	 * @return array Counts of changes.
	 */
	public static function run() {
		$grace    = (int) ks_get( 'grace_days' );
		$remind   = (int) ks_get( 'reminder_days' );
		$changes  = array( 'past_due' => 0, 'suspended' => 0, 'cancelled' => 0, 'reminded' => 0 );
		foreach ( KS_Tenants::all() as $sub ) {
			$due  = KS_Tenants::due_at( $sub );
			$left = KS_Tenants::days_until( $due );
			$late = $due && strtotime( $due . ' UTC' ) <= time();
			$link = array( __( 'Choose a plan & pay', 'kingdom-saas' ), KS_Tenants::admin_url( $sub->blog_id, 'subscription' ) );

			if ( in_array( $sub->status, array( 'trialing', 'active' ), true ) && $late ) {
				if ( 'active' === $sub->status && $sub->cancel_at_period_end ) {
					KS_Tenants::update( $sub->blog_id, array( 'status' => 'cancelled' ) );
					KS_Email::owner( $sub, __( 'Your subscription has ended', 'kingdom-saas' ), '<p>' . esc_html__( 'Your website is now offline. Your content is kept safe — choose a plan any time to bring it back.', 'kingdom-saas' ) . '</p>', $link );
					++$changes['cancelled'];
					continue;
				}
				KS_Tenants::update( $sub->blog_id, array( 'status' => 'past_due', 'last_notice' => 'past_due' ) );
				KS_Email::owner(
					$sub,
					'trialing' === $sub->status ? __( 'Your free trial has ended', 'kingdom-saas' ) : __( 'Your subscription payment is due', 'kingdom-saas' ),
					/* translators: %d: days */
					'<p>' . esc_html( sprintf( _n( 'Your website stays online for %d more day. Choose a plan to keep it running.', 'Your website stays online for %d more days. Choose a plan to keep it running.', $grace, 'kingdom-saas' ), $grace ) ) . '</p>',
					$link
				);
				++$changes['past_due'];
				continue;
			}

			if ( 'past_due' === $sub->status && $due && strtotime( $due . ' UTC' ) + $grace * DAY_IN_SECONDS <= time() ) {
				KS_Tenants::update( $sub->blog_id, array( 'status' => 'suspended', 'last_notice' => 'suspended' ) );
				KS_Email::owner( $sub, __( 'Your website has been paused', 'kingdom-saas' ), '<p>' . esc_html__( 'We have not received payment, so your website is paused for visitors. Your content is safe. Pay now and it comes straight back online.', 'kingdom-saas' ) . '</p>', $link );
				++$changes['suspended'];
				continue;
			}

			$key = 'remind-' . substr( (string) $due, 0, 10 );
			if ( in_array( $sub->status, array( 'trialing', 'active' ), true ) && null !== $left && $left < $remind && $left >= 0 && $sub->last_notice !== $key && ! $sub->cancel_at_period_end ) {
				KS_Tenants::update( $sub->blog_id, array( 'last_notice' => $key ) );
				KS_Email::owner(
					$sub,
					'trialing' === $sub->status ? __( 'Your free trial ends soon', 'kingdom-saas' ) : __( 'Your subscription renews soon', 'kingdom-saas' ),
					/* translators: %s: date */
					'<p>' . esc_html( sprintf( __( 'Please make your payment by %s to keep your website online without interruption.', 'kingdom-saas' ), date_i18n( get_option( 'date_format' ), strtotime( $due . ' UTC' ) ) ) ) . '</p>',
					$link
				);
				++$changes['reminded'];
			}
		}
		return $changes;
	}

	/**
	 * Is the site paused for this request?
	 *
	 * @return object|false Subscription when paused.
	 */
	private static function paused() {
		$sub = KS_Tenants::current();
		if ( ! $sub || KS_Tenants::is_live( $sub ) || is_super_admin() ) {
			return false;
		}
		return $sub;
	}

	/**
	 * Visitors see a "paused" page; church staff can only reach the Subscription screen.
	 */
	public static function gate_front() {
		$sub = self::paused();
		if ( ! $sub ) {
			return;
		}
		if ( current_user_can( 'manage_kcs' ) ) {
			$admin = function_exists( 'kcs_page_url' ) ? kcs_page_url( 'church_admin' ) : '';
			$here  = strtok( home_url( add_query_arg( array() ) ), '?' );
			$view  = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $admin && ( untrailingslashit( $here ) !== untrailingslashit( $admin ) || 'subscription' !== $view ) && empty( $_POST['ks_do'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_safe_redirect( add_query_arg( 'view', 'subscription', $admin ) );
				exit;
			}
			return;
		}
		status_header( 503 );
		header( 'Retry-After: 86400' );
		nocache_headers();
		echo ks_template( 'suspended', array( 'sub' => $sub ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Keep staff of a paused site out of wp-admin (except their profile).
	 */
	public static function gate_admin() {
		if ( wp_doing_ajax() || ! self::paused() ) {
			return;
		}
		global $pagenow;
		if ( 'profile.php' === $pagenow ) {
			return;
		}
		$admin = function_exists( 'kcs_page_url' ) ? kcs_page_url( 'church_admin' ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'view', 'subscription', $admin ) );
		exit;
	}

	/**
	 * Block the church's public REST API while paused.
	 *
	 * @param mixed $result Existing result.
	 * @return mixed
	 */
	public static function gate_rest( $result ) {
		if ( self::paused() && ! current_user_can( 'manage_kcs' ) ) {
			return new WP_Error( 'ks_paused', __( 'This website is temporarily unavailable.', 'kingdom-saas' ), array( 'status' => 503 ) );
		}
		return $result;
	}
}
