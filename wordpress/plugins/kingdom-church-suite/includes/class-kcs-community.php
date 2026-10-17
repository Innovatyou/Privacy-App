<?php
/**
 * Partner access rules (plan tiers and lockout), pastors with WhatsApp
 * contacts, visit requests and plan-targeted announcements.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Partner access: plan tiers and the payment lock.
 */
class KCS_Access {

	/**
	 * Monthly-equivalent value of an amount, used to rank plans.
	 *
	 * @param float  $amount    Amount.
	 * @param string $frequency Frequency.
	 * @return float
	 */
	public static function monthly_value( $amount, $frequency ) {
		$per_month = array( 'weekly' => 52 / 12, 'monthly' => 1, 'quarterly' => 1 / 3, 'yearly' => 1 / 12, 'one_time' => 1 / 12 );
		return (float) $amount * ( $per_month[ $frequency ] ?? 1 );
	}

	/**
	 * Fixed-amount active plans from lowest to highest level.
	 *
	 * The level follows the plan order set by the church (Plans → order), because
	 * plans in different currencies cannot be compared by amount. Plans with the
	 * same order fall back to their monthly value. Custom-amount plans are not ranked.
	 *
	 * @return array
	 */
	public static function ranked_plans() {
		$plans = array_values(
			array_filter(
				KCS_Plans::all( true ),
				function ( $p ) {
					return ! $p->allow_custom_amount && $p->amount > 0;
				}
			)
		);
		usort(
			$plans,
			function ( $a, $b ) {
				return array( (int) $a->sort_order, self::monthly_value( $a->amount, $a->frequency ) ) <=> array( (int) $b->sort_order, self::monthly_value( $b->amount, $b->frequency ) );
			}
		);
		return $plans;
	}

	/**
	 * ID of the highest active plan.
	 *
	 * @return int
	 */
	public static function top_plan_id() {
		$plans = self::ranked_plans();
		$top   = end( $plans );
		return $top ? (int) $top->id : 0;
	}

	/**
	 * Tier number of a plan (1 = lowest). Custom plans get the tier their amount reaches.
	 *
	 * @param object $partner Partner.
	 * @return int
	 */
	public static function tier( $partner ) {
		$value = self::monthly_value( $partner->amount, $partner->frequency );
		$tier  = 1;
		foreach ( self::ranked_plans() as $i => $plan ) {
			if ( (int) $plan->id === (int) $partner->plan_id ) {
				return $i + 1;
			}
			// Custom-amount partners reach the level their giving matches (same currency only).
			if ( $plan->currency === $partner->currency && $value >= self::monthly_value( $plan->amount, $plan->frequency ) ) {
				$tier = max( $tier, $i + 1 );
			}
		}
		return $tier;
	}

	/**
	 * Why a partner's dashboard is locked, or '' when it is open.
	 *
	 * @param object $partner Partner.
	 * @return string pending|overdue|paused|inactive|''
	 */
	public static function lock_reason( $partner ) {
		if ( ! $partner ) {
			return 'inactive';
		}
		if ( in_array( $partner->status, array( 'pending', 'paused', 'inactive' ), true ) ) {
			return $partner->status;
		}
		if ( 'overdue' === $partner->status ) {
			return 'overdue';
		}
		if ( 'one_time' !== $partner->frequency && $partner->next_due_date ) {
			$grace = max( 0, (int) kcs_get_option( 'grace_days', 7 ) );
			if ( strtotime( $partner->next_due_date . ' +' . $grace . ' days' ) < strtotime( current_time( 'Y-m-d' ) ) ) {
				return 'overdue';
			}
		}
		return '';
	}

	/**
	 * Days until (positive) or since (negative) the due date.
	 *
	 * @param object $partner Partner.
	 * @return int|null
	 */
	public static function days_to_due( $partner ) {
		if ( ! $partner->next_due_date || 'one_time' === $partner->frequency ) {
			return null;
		}
		return (int) floor( ( strtotime( $partner->next_due_date ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
	}
}

/**
 * Pastors reachable on WhatsApp, assigned to partnership plans.
 */
class KCS_Pastors {

	/**
	 * All pastors.
	 *
	 * @return array[] id, name, role, phone, photo, plan_ids[], active.
	 */
	public static function all() {
		$list = get_option( 'kcs_pastors', array() );
		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Save one pastor (insert or update).
	 *
	 * @param array $data Data.
	 * @return string Pastor ID.
	 */
	public static function save( $data ) {
		$list = self::all();
		$id   = ! empty( $data['id'] ) ? sanitize_key( $data['id'] ) : 'p' . strtolower( wp_generate_password( 8, false, false ) );
		$row  = array(
			'id'       => $id,
			'name'     => sanitize_text_field( $data['name'] ?? '' ),
			'role'     => sanitize_text_field( $data['role'] ?? '' ),
			'phone'    => self::normalise_phone( $data['phone'] ?? '' ),
			'photo'    => esc_url_raw( $data['photo'] ?? '' ),
			'plan_ids' => array_values( array_filter( array_map( 'absint', (array) ( $data['plan_ids'] ?? array() ) ) ) ),
			'active'   => empty( $data['active'] ) ? 0 : 1,
		);
		$found = false;
		foreach ( $list as $i => $p ) {
			if ( $p['id'] === $id ) {
				$list[ $i ] = $row;
				$found      = true;
			}
		}
		if ( ! $found ) {
			$list[] = $row;
		}
		update_option( 'kcs_pastors', $list, false );
		return $id;
	}

	/**
	 * Delete a pastor.
	 *
	 * @param string $id Pastor ID.
	 */
	public static function delete( $id ) {
		update_option(
			'kcs_pastors',
			array_values(
				array_filter(
					self::all(),
					function ( $p ) use ( $id ) {
						return $p['id'] !== $id;
					}
				)
			),
			false
		);
	}

	/**
	 * Digits-only international number for wa.me (e.g. 2348012345678).
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function normalise_phone( $phone ) {
		return preg_replace( '/\D+/', '', (string) $phone );
	}

	/**
	 * Pastors a partner can chat with. The highest plan always gets every active pastor.
	 *
	 * @param object $partner Partner.
	 * @return array[]
	 */
	public static function for_partner( $partner ) {
		$active = array_filter(
			self::all(),
			function ( $p ) {
				return ! empty( $p['active'] ) && ! empty( $p['phone'] );
			}
		);
		$tier   = KCS_Access::tier( $partner );
		$ranked = KCS_Access::ranked_plans();
		if ( $ranked && ( (int) $partner->plan_id === KCS_Access::top_plan_id() || $tier >= count( $ranked ) ) ) {
			return array_values( $active );
		}
		// A partner also reaches pastors of every lower plan.
		$reach = array( (int) $partner->plan_id );
		foreach ( $ranked as $i => $plan ) {
			if ( $i + 1 <= $tier ) {
				$reach[] = (int) $plan->id;
			}
		}
		return array_values(
			array_filter(
				$active,
				function ( $p ) use ( $reach ) {
					return (bool) array_intersect( $reach, (array) $p['plan_ids'] );
				}
			)
		);
	}

	/**
	 * WhatsApp click-to-chat link with a friendly opening message.
	 *
	 * @param array  $pastor  Pastor.
	 * @param object $partner Partner.
	 * @return string
	 */
	public static function whatsapp_link( $pastor, $partner ) {
		/* translators: 1: pastor name, 2: partner name, 3: partner ID */
		$text = sprintf( __( 'Hello %1$s, this is %2$s (partner ID %3$s). ', 'kingdom-church-suite' ), $pastor['name'], trim( $partner->first_name . ' ' . $partner->last_name ), $partner->partner_code );
		return 'https://wa.me/' . rawurlencode( $pastor['phone'] ) . '?text=' . rawurlencode( $text );
	}
}

/**
 * Visit requests ("Plan your visit").
 */
class KCS_Visits {

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'       => __( 'New', 'kingdom-church-suite' ),
			'confirmed' => __( 'Confirmed', 'kingdom-church-suite' ),
			'visited'   => __( 'Visited', 'kingdom-church-suite' ),
			'no_show'   => __( 'Did not come', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Create a visit request.
	 *
	 * @param array $data name,email,phone,visit_date,service,adults,kids,notes.
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		global $wpdb;
		$name  = sanitize_text_field( $data['name'] ?? '' );
		$email = sanitize_email( $data['email'] ?? '' );
		$phone = sanitize_text_field( $data['phone'] ?? '' );
		if ( ! $name || ( ! is_email( $email ) && ! $phone ) ) {
			return new WP_Error( 'kcs_visit', __( 'Please tell us your name and an email or phone number.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $data['visit_date'] ?? '' ) ) ? $data['visit_date'] : null;
		$wpdb->insert(
			kcs_table( 'visits' ),
			array(
				'name'       => $name,
				'email'      => is_email( $email ) ? $email : '',
				'phone'      => $phone,
				'visit_date' => $date,
				'service'    => sanitize_text_field( $data['service'] ?? '' ),
				'adults'     => max( 1, min( 50, (int) ( $data['adults'] ?? 1 ) ) ),
				'kids'       => max( 0, min( 50, (int) ( $data['kids'] ?? 0 ) ) ),
				'notes'      => sanitize_textarea_field( $data['notes'] ?? '' ),
				'status'     => 'new',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$id   = (int) $wpdb->insert_id;
		$when = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : __( 'soon', 'kingdom-church-suite' );
		if ( is_email( $email ) ) {
			KCS_Email::send(
				$email,
				/* translators: %s: church */
				sprintf( __( 'We can’t wait to welcome you to %s', 'kingdom-church-suite' ), kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ),
				'<p>' . esc_html( sprintf( /* translators: 1: name, 2: date */ __( 'Hi %1$s, thank you for planning your visit on %2$s. Our welcome team will meet you at the entrance.', 'kingdom-church-suite' ), $name, $when ) ) . '</p><p>' . esc_html( kcs_get_option( 'church_address' ) ) . '</p>'
			);
		}
		KCS_Email::send(
			KCS_Email::admin_recipients(),
			/* translators: 1: name, 2: date */
			sprintf( __( 'New visit: %1$s on %2$s', 'kingdom-church-suite' ), $name, $when ),
			'<p>' . esc_html( $name ) . ' · ' . esc_html( $email ) . ' · ' . esc_html( $phone ) . '</p><p>' . esc_html( sanitize_text_field( $data['service'] ?? '' ) ) . '</p><p><a class="btn" href="' . esc_url( add_query_arg( 'view', 'visits', kcs_page_url( 'church_admin' ) ) ) . '">' . esc_html__( 'Open visits', 'kingdom-church-suite' ) . '</a></p>'
		);
		do_action( 'kcs_visit_created', $id );
		KCS_Email::webhook( 'visit.created', array( 'id' => $id, 'name' => $name, 'visit_date' => $date ) );
		return $id;
	}
}

/**
 * Announcements and resources shown on partner dashboards, targeted by plan.
 */
class KCS_Announcements {

	/**
	 * Create an announcement.
	 *
	 * @param array $data title, body, kind (news|resource|event), link, plan_ids[].
	 * @return int
	 */
	public static function create( $data ) {
		global $wpdb;
		$plans = array_filter( array_map( 'absint', (array) ( $data['plan_ids'] ?? array() ) ) );
		$wpdb->insert(
			kcs_table( 'announcements' ),
			array(
				'title'      => sanitize_text_field( $data['title'] ?? '' ),
				'body'       => wp_kses_post( $data['body'] ?? '' ),
				'kind'       => in_array( $data['kind'] ?? '', array( 'news', 'resource', 'event' ), true ) ? $data['kind'] : 'news',
				'link'       => esc_url_raw( $data['link'] ?? '' ),
				'plan_ids'   => implode( ',', $plans ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete.
	 *
	 * @param int $id ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( kcs_table( 'announcements' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Recent announcements.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function recent( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'announcements' ) . ' ORDER BY id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Announcements a partner can see: those for everyone plus those for plans at or below their tier.
	 *
	 * @param object $partner Partner.
	 * @param int    $limit   Limit.
	 * @return array
	 */
	public static function for_partner( $partner, $limit = 20 ) {
		$tier   = KCS_Access::tier( $partner );
		$reach  = array( (int) $partner->plan_id );
		foreach ( KCS_Access::ranked_plans() as $i => $plan ) {
			if ( $i + 1 <= $tier ) {
				$reach[] = (int) $plan->id;
			}
		}
		$out = array();
		foreach ( self::recent( 200 ) as $a ) {
			$targets = array_filter( array_map( 'intval', explode( ',', $a->plan_ids ) ) );
			if ( ! $targets || array_intersect( $targets, $reach ) ) {
				$out[] = $a;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
}
