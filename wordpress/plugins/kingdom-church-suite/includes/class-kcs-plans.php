<?php
/**
 * Partnership plans.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plans repository.
 */
class KCS_Plans {

	/**
	 * All plans.
	 *
	 * @param bool $active_only Only active plans.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		global $wpdb;
		$table = kcs_table( 'plans' );
		$where = $active_only ? 'WHERE is_active = 1' : '';
		return $wpdb->get_results( "SELECT * FROM $table $where ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Single plan.
	 *
	 * @param int $id Plan ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = kcs_table( 'plans' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create or update a plan.
	 *
	 * @param array $data Data.
	 * @param int   $id   Plan ID for updates.
	 * @return int Plan ID.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$row = array(
			'name'                => sanitize_text_field( $data['name'] ?? '' ),
			'description'         => sanitize_textarea_field( $data['description'] ?? '' ),
			'benefits'            => sanitize_textarea_field( $data['benefits'] ?? '' ),
			'amount'              => max( 0, (float) ( $data['amount'] ?? 0 ) ),
			'currency'            => array_key_exists( $data['currency'] ?? '', kcs_currencies() ) ? $data['currency'] : kcs_get_option( 'currency', 'USD' ),
			'frequency'           => array_key_exists( $data['frequency'] ?? '', kcs_frequencies() ) ? $data['frequency'] : 'monthly',
			'allow_custom_amount' => empty( $data['allow_custom_amount'] ) ? 0 : 1,
			'is_active'           => empty( $data['is_active'] ) ? 0 : 1,
			'is_featured'         => empty( $data['is_featured'] ) ? 0 : 1,
			'sort_order'          => (int) ( $data['sort_order'] ?? 0 ),
		);
		if ( $id ) {
			$wpdb->update( kcs_table( 'plans' ), $row, array( 'id' => $id ) );
			return (int) $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( kcs_table( 'plans' ), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete plan.
	 *
	 * @param int $id Plan ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( kcs_table( 'plans' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Public representation.
	 *
	 * @param object $plan Plan.
	 * @return array
	 */
	public static function to_array( $plan ) {
		return array(
			'id'                  => (int) $plan->id,
			'name'                => $plan->name,
			'description'         => $plan->description,
			'benefits'            => array_values( array_filter( array_map( 'trim', explode( "\n", (string) $plan->benefits ) ) ) ),
			'amount'              => (float) $plan->amount,
			'currency'            => $plan->currency,
			'frequency'           => $plan->frequency,
			'allow_custom_amount' => (bool) $plan->allow_custom_amount,
			'is_featured'         => (bool) $plan->is_featured,
			'is_active'           => (bool) $plan->is_active,
		);
	}
}
