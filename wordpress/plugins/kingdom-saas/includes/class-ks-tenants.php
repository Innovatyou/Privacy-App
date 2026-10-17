<?php
/**
 * Church subscriptions (one per site).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tenants repository.
 */
class KS_Tenants {

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array( 'trialing', 'active', 'past_due', 'suspended', 'cancelled' );
	}

	/**
	 * Subscription for a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return object|null
	 */
	public static function get( $blog_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'subscriptions' ) . ' WHERE blog_id = %d', $blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Subscription for the current site.
	 *
	 * @return object|null
	 */
	public static function current() {
		static $cache = array();
		$id = get_current_blog_id();
		if ( ! array_key_exists( $id, $cache ) ) {
			$cache[ $id ] = is_main_site( $id ) ? null : self::get( $id );
		}
		return $cache[ $id ];
	}

	/**
	 * All subscriptions.
	 *
	 * @param array $args status, search.
	 * @return array
	 */
	public static function all( $args = array() ) {
		global $wpdb;
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(church_name LIKE %s OR owner_email LIKE %s OR subdomain LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		$sql = 'SELECT * FROM ' . ks_table( 'subscriptions' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC';
		return $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Update a subscription.
	 *
	 * @param int   $blog_id Site ID.
	 * @param array $data    Fields.
	 */
	public static function update( $blog_id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( ks_table( 'subscriptions' ), $data, array( 'blog_id' => (int) $blog_id ) );
		do_action( 'ks_subscription_updated', (int) $blog_id, $data );
	}

	/**
	 * Can visitors use the site? (Trial, active, or within the grace period.)
	 *
	 * @param object|null $sub Subscription.
	 * @return bool
	 */
	public static function is_live( $sub ) {
		return ! $sub || in_array( $sub->status, array( 'trialing', 'active', 'past_due' ), true );
	}

	/**
	 * Feature of the site's plan.
	 *
	 * @param string   $feature Feature.
	 * @param int|null $blog_id Site (current by default).
	 * @return mixed
	 */
	public static function feature( $feature, $blog_id = null ) {
		$sub = $blog_id ? self::get( $blog_id ) : self::current();
		return $sub ? KS_Plans::feature( $sub->plan, $feature ) : null;
	}

	/**
	 * Whole days until a GMT datetime (negative when past).
	 *
	 * @param string|null $gmt Datetime.
	 * @return int|null
	 */
	public static function days_until( $gmt ) {
		if ( ! $gmt ) {
			return null;
		}
		$diff = ( strtotime( $gmt . ' UTC' ) - time() ) / DAY_IN_SECONDS;
		return (int) ( $diff >= 0 ? ceil( $diff ) : floor( $diff ) );
	}

	/**
	 * Date the site must be paid by.
	 *
	 * @param object $sub Subscription.
	 * @return string|null GMT datetime.
	 */
	public static function due_at( $sub ) {
		return 'trialing' === $sub->status || ! $sub->current_period_end ? $sub->trial_ends_at : $sub->current_period_end;
	}

	/**
	 * Primary URL of a church site.
	 *
	 * @param int $blog_id Site ID.
	 * @return string
	 */
	public static function url( $blog_id ) {
		return untrailingslashit( get_home_url( $blog_id ) );
	}

	/**
	 * Church Admin URL of a site.
	 *
	 * @param int    $blog_id Site ID.
	 * @param string $view    View.
	 * @return string
	 */
	public static function admin_url( $blog_id, $view = '' ) {
		switch_to_blog( $blog_id );
		$settings = (array) get_option( 'kcs_settings', array() );
		$page_id  = (int) ( $settings['page_church_admin'] ?? 0 );
		$url      = $page_id && get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/church-admin/' );
		restore_current_blog();
		return $view ? add_query_arg( 'view', $view, $url ) : $url;
	}

	/**
	 * Number of partners on a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return int
	 */
	public static function partner_count( $blog_id ) {
		global $wpdb;
		$table = $wpdb->get_blog_prefix( $blog_id ) . 'kcs_partners';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
