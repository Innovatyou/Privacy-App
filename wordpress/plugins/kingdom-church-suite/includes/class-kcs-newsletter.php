<?php
/**
 * Newsletter: subscribers, double opt-in and campaigns.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newsletter.
 */
class KCS_Newsletter {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_links' ) );
		add_action( 'kcs_send_scheduled_campaign', array( __CLASS__, 'send_campaign' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_announce_sermon' ), 10, 3 );
	}

	/**
	 * Audiences.
	 *
	 * @return array
	 */
	public static function audiences() {
		return array(
			'subscribers'      => __( 'Newsletter subscribers', 'kingdom-church-suite' ),
			'partners'         => __( 'All partners', 'kingdom-church-suite' ),
			'partners_active'  => __( 'Active partners', 'kingdom-church-suite' ),
			'partners_overdue' => __( 'Overdue partners', 'kingdom-church-suite' ),
			'everyone'         => __( 'Subscribers + partners', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Get subscriber by email.
	 *
	 * @param string $email Email.
	 * @return object|null
	 */
	public static function get_by_email( $email ) {
		global $wpdb;
		$table = kcs_table( 'subscribers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get subscriber by token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		$table = kcs_table( 'subscribers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Subscribe an email.
	 *
	 * @param string $email        Email.
	 * @param string $name         Name.
	 * @param string $source       Source.
	 * @param bool   $skip_confirm Skip double opt-in (e.g. partners, admin imports).
	 * @return string|WP_Error Status: subscribed|pending|exists.
	 */
	public static function subscribe( $email, $name = '', $source = 'website', $skip_confirm = false ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'kcs_email', __( 'Please enter a valid email address.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$name     = sanitize_text_field( $name );
		$existing = self::get_by_email( $email );
		$double   = ! $skip_confirm && '1' === kcs_get_option( 'double_optin', '1' );
		$status   = $double ? 'pending' : 'subscribed';

		if ( $existing ) {
			if ( 'subscribed' === $existing->status ) {
				return 'exists';
			}
			$wpdb->update(
				kcs_table( 'subscribers' ),
				array(
					'status'       => $status,
					'name'         => $name ? $name : $existing->name,
					'confirmed_at' => $double ? null : current_time( 'mysql' ),
				),
				array( 'id' => $existing->id )
			);
			$token = $existing->token;
		} else {
			$token = wp_generate_password( 32, false, false );
			$wpdb->insert(
				kcs_table( 'subscribers' ),
				array(
					'email'        => $email,
					'name'         => $name,
					'status'       => $status,
					'token'        => $token,
					'source'       => sanitize_key( $source ),
					'created_at'   => current_time( 'mysql' ),
					'confirmed_at' => $double ? null : current_time( 'mysql' ),
				)
			);
		}

		if ( $double ) {
			KCS_Email::send_template(
				$email,
				'subscribe_confirm',
				array(
					'name'        => $name ? $name : __( 'friend', 'kingdom-church-suite' ),
					'confirm_url' => self::link( 'confirm', $token ),
				)
			);
		} elseif ( 'partner' !== $source ) {
			self::welcome( $email, $name, $token );
		}
		do_action( 'kcs_subscriber_created', $email, $status );
		KCS_Email::webhook( 'subscriber.created', array( 'email' => $email, 'name' => $name, 'status' => $status, 'source' => $source ) );
		return $status;
	}

	/**
	 * Send welcome email.
	 *
	 * @param string $email Email.
	 * @param string $name  Name.
	 * @param string $token Token.
	 */
	private static function welcome( $email, $name, $token ) {
		$t = KCS_Email::render_template( 'subscribe_welcome', array( 'name' => $name ? $name : __( 'friend', 'kingdom-church-suite' ) ) );
		KCS_Email::send( $email, $t['subject'], $t['body'], array( 'unsubscribe_url' => self::link( 'unsubscribe', $token ) ) );
	}

	/**
	 * Unsubscribe.
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	public static function unsubscribe( $token ) {
		global $wpdb;
		$sub = self::get_by_token( $token );
		if ( ! $sub ) {
			return false;
		}
		$wpdb->update( kcs_table( 'subscribers' ), array( 'status' => 'unsubscribed' ), array( 'id' => $sub->id ) );
		$wpdb->update( kcs_table( 'partners' ), array( 'email_opt_in' => 0 ), array( 'email' => $sub->email ) );
		do_action( 'kcs_subscriber_unsubscribed', $sub->email );
		return true;
	}

	/**
	 * Confirm/unsubscribe link.
	 *
	 * @param string $action confirm|unsubscribe.
	 * @param string $token  Token.
	 * @return string
	 */
	public static function link( $action, $token ) {
		return add_query_arg(
			array(
				'kcs_newsletter' => $action,
				'token'          => $token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Handle confirm/unsubscribe links.
	 */
	public static function handle_links() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- token based links from email.
		if ( empty( $_GET['kcs_newsletter'] ) || empty( $_GET['token'] ) ) {
			return;
		}
		$action = sanitize_key( $_GET['kcs_newsletter'] );
		$token  = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		// phpcs:enable
		$church = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		$sub    = self::get_by_token( $token );

		if ( ! $sub ) {
			wp_die( esc_html__( 'This link is invalid or has expired.', 'kingdom-church-suite' ), esc_html( $church ), array( 'response' => 404, 'link_url' => esc_url( home_url( '/' ) ), 'link_text' => esc_html__( 'Go to homepage', 'kingdom-church-suite' ) ) );
		}
		global $wpdb;
		if ( 'confirm' === $action ) {
			if ( 'subscribed' !== $sub->status ) {
				$wpdb->update( kcs_table( 'subscribers' ), array( 'status' => 'subscribed', 'confirmed_at' => current_time( 'mysql' ) ), array( 'id' => $sub->id ) );
				self::welcome( $sub->email, $sub->name, $sub->token );
			}
			$message = __( 'Thank you! Your subscription is confirmed. 🎉', 'kingdom-church-suite' );
		} elseif ( 'unsubscribe' === $action ) {
			self::unsubscribe( $token );
			$message = __( 'You have been unsubscribed. We are sorry to see you go — you are always welcome back.', 'kingdom-church-suite' );
		} else {
			return;
		}
		wp_die( '<h1>' . esc_html( $church ) . '</h1><p>' . esc_html( $message ) . '</p>', esc_html( $church ), array( 'response' => 200, 'link_url' => esc_url( home_url( '/' ) ), 'link_text' => esc_html__( 'Go to homepage', 'kingdom-church-suite' ) ) );
	}

	/**
	 * Get campaign.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get_campaign( $id ) {
		global $wpdb;
		$table = kcs_table( 'campaigns' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Save a campaign.
	 *
	 * @param array $data subject, preheader, content, audience, scheduled_at.
	 * @param int   $id   ID.
	 * @return int
	 */
	public static function save_campaign( $data, $id = 0 ) {
		global $wpdb;
		$row = array(
			'subject'      => sanitize_text_field( $data['subject'] ?? '' ),
			'preheader'    => sanitize_text_field( $data['preheader'] ?? '' ),
			'content'      => wp_kses_post( $data['content'] ?? '' ),
			'audience'     => array_key_exists( $data['audience'] ?? '', self::audiences() ) ? $data['audience'] : 'subscribers',
			'scheduled_at' => ! empty( $data['scheduled_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['scheduled_at'] ) ) : null,
		);
		if ( $id ) {
			$wpdb->update( kcs_table( 'campaigns' ), $row, array( 'id' => $id ) );
			return (int) $id;
		}
		$row['status']     = 'draft';
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( kcs_table( 'campaigns' ), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Recipients for an audience.
	 *
	 * @param string $audience Audience.
	 * @param array  $ids      Optional partner IDs to restrict to.
	 * @return array email => [name, unsubscribe_url]
	 */
	public static function recipients( $audience, $ids = array() ) {
		global $wpdb;
		$out = array();
		// phpcs:disable WordPress.DB.PreparedSQL
		if ( in_array( $audience, array( 'subscribers', 'everyone' ), true ) ) {
			$rows = $wpdb->get_results( 'SELECT email, name, token FROM ' . kcs_table( 'subscribers' ) . " WHERE status = 'subscribed'" );
			foreach ( $rows as $r ) {
				$out[ strtolower( $r->email ) ] = array( $r->name, self::link( 'unsubscribe', $r->token ) );
			}
		}
		if ( 0 === strpos( $audience, 'partners' ) || 'everyone' === $audience ) {
			$sql = 'SELECT email, first_name, last_name FROM ' . kcs_table( 'partners' ) . ' WHERE 1=1';
			if ( 'partners_active' === $audience ) {
				$sql .= " AND status = 'active'";
			} elseif ( 'partners_overdue' === $audience ) {
				$sql .= " AND status = 'overdue'";
			}
			if ( $ids ) {
				$sql .= ' AND id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
			}
			foreach ( $wpdb->get_results( $sql ) as $r ) {
				$key = strtolower( $r->email );
				if ( ! isset( $out[ $key ] ) ) {
					$sub         = self::get_by_email( $r->email );
					$out[ $key ] = array( trim( $r->first_name . ' ' . $r->last_name ), $sub ? self::link( 'unsubscribe', $sub->token ) : '' );
				}
			}
		}
		// phpcs:enable
		return apply_filters( 'kcs_campaign_recipients', $out, $audience );
	}

	/**
	 * Queue a campaign for sending (in batches via cron).
	 *
	 * @param int $id Campaign ID.
	 * @return int|WP_Error Number queued.
	 */
	public static function send_campaign( $id ) {
		global $wpdb;
		$c = self::get_campaign( $id );
		if ( ! $c ) {
			return new WP_Error( 'kcs_campaign', __( 'Campaign not found.', 'kingdom-church-suite' ), array( 'status' => 404 ) );
		}
		if ( in_array( $c->status, array( 'sending', 'sent' ), true ) ) {
			return new WP_Error( 'kcs_campaign', __( 'Campaign already sent.', 'kingdom-church-suite' ), array( 'status' => 409 ) );
		}
		$count = self::queue_message( $c->audience, $c->subject, $c->content, $c->preheader, (int) $c->id );
		$wpdb->update(
			kcs_table( 'campaigns' ),
			array(
				'status'  => $count ? 'sending' : 'sent',
				'total'   => $count,
				'sent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $c->id )
		);
		// Kick off the first batch right away.
		wp_schedule_single_event( time() + 5, 'kcs_process_email_queue' );
		return $count;
	}

	/**
	 * Personalise and queue a message for an audience.
	 *
	 * @param string $audience    Audience key.
	 * @param string $subject     Subject.
	 * @param string $content     HTML.
	 * @param string $preheader   Preheader.
	 * @param int    $campaign_id Campaign.
	 * @param array  $partner_ids Restrict partners.
	 * @return int
	 */
	public static function queue_message( $audience, $subject, $content, $preheader = '', $campaign_id = 0, $partner_ids = array() ) {
		$count = 0;
		foreach ( self::recipients( $audience, $partner_ids ) as $email => $info ) {
			$vars = array(
				'name'            => $info[0] ? $info[0] : __( 'friend', 'kingdom-church-suite' ),
				'first_name'      => $info[0] ? strtok( $info[0], ' ' ) : __( 'friend', 'kingdom-church-suite' ),
				'email'           => $email,
				'unsubscribe_url' => $info[1],
				'church_name'     => kcs_get_option( 'church_name', get_bloginfo( 'name' ) ),
			);
			$body = KCS_Email::replace( $content, $vars, true );
			$subj = KCS_Email::replace( $subject, $vars, false );
			$html = KCS_Email::wrap( $subj, $body, array( 'preheader' => $preheader, 'unsubscribe_url' => $info[1] ) );
			KCS_Email::queue( $email, $subj, $html, $campaign_id );
			++$count;
		}
		return $count;
	}

	/**
	 * Recalculate campaign counters.
	 *
	 * @param int $id Campaign ID.
	 */
	public static function refresh_campaign_stats( $id ) {
		global $wpdb;
		$q       = kcs_table( 'email_queue' );
		$sent    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $q WHERE campaign_id = %d AND status = 'sent'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $q WHERE campaign_id = %d AND status = 'queued'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row     = array( 'sent_count' => $sent );
		if ( 0 === $pending ) {
			$row['status'] = 'sent';
		}
		$wpdb->update( kcs_table( 'campaigns' ), $row, array( 'id' => $id ) );
	}

	/**
	 * Optionally email subscribers when a sermon is first published.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function maybe_announce_sermon( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old || 'kcs_sermon' !== $post->post_type ) {
			return;
		}
		if ( '1' !== kcs_get_option( 'auto_new_sermon', '0' ) || get_post_meta( $post->ID, '_kcs_announced', true ) ) {
			return;
		}
		update_post_meta( $post->ID, '_kcs_announced', 1 );
		$img     = get_the_post_thumbnail_url( $post, 'large' );
		$content = ( $img ? '<p><img src="' . esc_url( $img ) . '" style="max-width:100%;border-radius:8px" alt=""></p>' : '' )
			. '<p>Hi {first_name},</p><p>A new message is now available: <strong>' . esc_html( get_the_title( $post ) ) . '</strong></p>'
			. '<p>' . esc_html( wp_trim_words( $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( $post->post_content ), 40 ) ) . '</p>'
			. '<p><a class="btn" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Watch / listen now', 'kingdom-church-suite' ) . '</a></p>';
		$id      = self::save_campaign(
			array(
				'subject'  => sprintf( /* translators: %s: sermon title */ __( 'New sermon: %s', 'kingdom-church-suite' ), get_the_title( $post ) ),
				'content'  => $content,
				'audience' => 'subscribers',
			)
		);
		self::send_campaign( $id );
	}
}
