<?php
/**
 * Prayer requests & prayer wall.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prayer.
 */
class KCS_Prayer {

	/**
	 * Hooks.
	 */
	public static function init() {}

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'      => __( 'New', 'kingdom-church-suite' ),
			'praying'  => __( 'Praying', 'kingdom-church-suite' ),
			'approved' => __( 'On prayer wall', 'kingdom-church-suite' ),
			'answered' => __( 'Answered 🙌', 'kingdom-church-suite' ),
			'archived' => __( 'Archived', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Create a request.
	 *
	 * @param array $data name,email,phone,request,is_private.
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		global $wpdb;
		$request = sanitize_textarea_field( $data['request'] ?? '' );
		if ( strlen( $request ) < 5 ) {
			return new WP_Error( 'kcs_prayer', __( 'Please share your prayer request.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$email = sanitize_email( $data['email'] ?? '' );
		$wpdb->insert(
			kcs_table( 'prayer_requests' ),
			array(
				'name'       => sanitize_text_field( $data['name'] ?? '' ),
				'email'      => is_email( $email ) ? $email : '',
				'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
				'request'    => $request,
				'is_private' => empty( $data['is_private'] ) ? 0 : 1,
				'status'     => 'new',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$id   = (int) $wpdb->insert_id;
		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( is_email( $email ) ) {
			KCS_Email::send_template( $email, 'prayer_received', array( 'name' => $name ? $name : __( 'friend', 'kingdom-church-suite' ) ) );
		}
		if ( '1' === kcs_get_option( 'notify_admin_prayer', '1' ) ) {
			KCS_Email::send(
				KCS_Email::admin_recipients(),
				/* translators: %s: name */
				sprintf( __( 'New prayer request from %s', 'kingdom-church-suite' ), $name ? $name : __( 'Anonymous', 'kingdom-church-suite' ) ),
				'<p>' . nl2br( esc_html( $request ) ) . '</p><p>' . esc_html( $email ) . ' ' . esc_html( $data['phone'] ?? '' ) . '</p><p><a class="btn" href="' . esc_url( admin_url( 'admin.php?page=kcs-prayer' ) ) . '">' . esc_html__( 'View requests', 'kingdom-church-suite' ) . '</a></p>'
			);
		}
		do_action( 'kcs_prayer_created', $id );
		KCS_Email::webhook( 'prayer.created', array( 'id' => $id, 'name' => $name, 'is_private' => ! empty( $data['is_private'] ) ) );
		return $id;
	}

	/**
	 * Public prayer wall items.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function wall( $limit = 20 ) {
		global $wpdb;
		$table = kcs_table( 'prayer_requests' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, request, prayer_count, status, created_at FROM $table WHERE is_private = 0 AND status IN ('approved','answered') ORDER BY id DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Increment "I prayed" counter.
	 *
	 * @param int $id ID.
	 * @return int|WP_Error New count.
	 */
	public static function pray( $id ) {
		global $wpdb;
		$table = kcs_table( 'prayer_requests' );
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET prayer_count = prayer_count + 1 WHERE id = %d AND is_private = 0 AND status IN ('approved','answered')", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT prayer_count FROM $table WHERE id = %d AND is_private = 0", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return null === $count ? new WP_Error( 'kcs_not_found', 'Not found', array( 'status' => 404 ) ) : (int) $count;
	}
}
