<?php
/**
 * Web Push notifications for the church app (Android, iPhone 16.4+ home-screen apps, desktop).
 *
 * Standard Web Push: VAPID (RFC 8292) to identify the church, payloads encrypted with
 * aes128gcm (RFC 8291) so only the subscriber's device can read them. No third-party service.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Push notifications.
 */
class KCS_Push {

	/**
	 * Push services a subscription may point at (prevents the subscribe endpoint being used
	 * to make the site send requests anywhere else).
	 *
	 * @var string[]
	 */
	const HOSTS = array( 'fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'web.push.apple.com', 'notify.windows.com', 'push.apple.com' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'kcs_push_batch', array( __CLASS__, 'process_queue' ) );
		add_action( 'kingdom_ytlive_went_live', array( __CLASS__, 'on_live' ) );
	}

	/**
	 * Base64url helpers.
	 *
	 * @param string $bin Binary.
	 * @return string
	 */
	public static function b64u( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decode base64url.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function unb64u( $s ) {
		return (string) base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * The church's VAPID key pair, created on first use.
	 *
	 * @return array{public: string, private: string} Public key (base64url, uncompressed point) and private key (PEM).
	 */
	public static function keys() {
		$keys = get_option( 'kcs_vapid' );
		if ( is_array( $keys ) && ! empty( $keys['public'] ) && ! empty( $keys['private'] ) ) {
			return $keys;
		}
		$key = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
		openssl_pkey_export( $key, $pem );
		$d    = openssl_pkey_get_details( $key );
		$keys = array(
			'public'  => self::b64u( "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT ) ),
			'private' => $pem,
		);
		update_option( 'kcs_vapid', $keys, false );
		return $keys;
	}

	/**
	 * PEM public key for a raw uncompressed P-256 point.
	 *
	 * @param string $point 65-byte point.
	 * @return string
	 */
	private static function point_to_pem( $point ) {
		$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $point;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Signed VAPID token for a push service.
	 *
	 * @param string $endpoint Subscription endpoint.
	 * @return string JWT.
	 */
	public static function vapid_jwt( $endpoint ) {
		$keys   = self::keys();
		$parts  = wp_parse_url( $endpoint );
		$claims = array(
			'aud' => $parts['scheme'] . '://' . $parts['host'],
			'exp' => time() + 12 * HOUR_IN_SECONDS,
			'sub' => 'mailto:' . get_option( 'admin_email' ),
		);
		$input  = self::b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) ) . '.' . self::b64u( wp_json_encode( $claims ) );
		openssl_sign( $input, $der, $keys['private'], OPENSSL_ALGO_SHA256 );
		return $input . '.' . self::b64u( self::der_to_raw( $der ) );
	}

	/**
	 * DER ECDSA signature → 64-byte r||s (what JWT ES256 expects).
	 *
	 * @param string $der DER signature.
	 * @return string
	 */
	private static function der_to_raw( $der ) {
		$pos = 2 + ( ord( $der[1] ) & 0x80 ? ( ord( $der[1] ) & 0x7f ) : 0 );
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$len  = ord( $der[ $pos + 1 ] );
			$int  = substr( $der, $pos + 2, $len );
			$out .= str_pad( ltrim( $int, "\0" ), 32, "\0", STR_PAD_LEFT );
			$pos += 2 + $len;
		}
		return $out;
	}

	/**
	 * Encrypt a payload for one subscription (RFC 8291, aes128gcm).
	 *
	 * @param string $payload Plain text.
	 * @param string $p256dh  Subscriber public key (base64url).
	 * @param string $auth    Subscriber auth secret (base64url).
	 * @return string|false Request body.
	 */
	public static function encrypt( $payload, $p256dh, $auth ) {
		$ua_pub = self::unb64u( $p256dh );
		$secret = self::unb64u( $auth );
		if ( 65 !== strlen( $ua_pub ) || 16 !== strlen( $secret ) ) {
			return false;
		}
		$eph    = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
		$d      = openssl_pkey_get_details( $eph );
		$as_pub = "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
		$shared = openssl_pkey_derive( openssl_pkey_get_public( self::point_to_pem( $ua_pub ) ), $eph, 32 );
		if ( false === $shared ) {
			return false;
		}
		$shared = str_pad( $shared, 32, "\0", STR_PAD_LEFT );
		$ikm    = hash_hmac( 'sha256', "WebPush: info\0" . $ua_pub . $as_pub . "\x01", hash_hmac( 'sha256', $shared, $secret, true ), true );
		$salt   = random_bytes( 16 );
		$prk    = hash_hmac( 'sha256', $ikm, $salt, true );
		$cek    = substr( hash_hmac( 'sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true ), 0, 16 );
		$nonce  = substr( hash_hmac( 'sha256', "Content-Encoding: nonce\0\x01", $prk, true ), 0, 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( false === $cipher ) {
			return false;
		}
		return $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_pub . $cipher . $tag;
	}

	/**
	 * Is this a push service endpoint we will talk to?
	 *
	 * @param string $endpoint URL.
	 * @return bool
	 */
	public static function valid_endpoint( $endpoint ) {
		$p = wp_parse_url( (string) $endpoint );
		if ( empty( $p['scheme'] ) || 'https' !== $p['scheme'] || empty( $p['host'] ) ) {
			return false;
		}
		$host = strtolower( $p['host'] );
		foreach ( (array) apply_filters( 'kcs_push_hosts', self::HOSTS ) as $allowed ) {
			if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build the encrypted HTTP request for one subscription.
	 *
	 * @param object $sub     Row with endpoint, p256dh, auth.
	 * @param array  $message title, body, url, tag, icon.
	 * @return array|null url, headers, data, type, options (for Requests::request_multiple).
	 */
	public static function request( $sub, $message ) {
		if ( ! self::valid_endpoint( $sub->endpoint ) ) {
			return null;
		}
		$body = self::encrypt( wp_json_encode( $message ), $sub->p256dh, $sub->auth );
		if ( false === $body ) {
			return null;
		}
		return array(
			'url'     => $sub->endpoint,
			'type'    => 'POST',
			'data'    => $body,
			'headers' => array(
				'TTL'              => '86400',
				'Urgency'          => 'high',
				'Content-Type'     => 'application/octet-stream',
				'Content-Encoding' => 'aes128gcm',
				'Authorization'    => 'vapid t=' . self::vapid_jwt( $sub->endpoint ) . ', k=' . self::keys()['public'],
			),
			'options' => array( 'timeout' => 10, 'follow_redirects' => false ),
		);
	}

	/**
	 * Send one notification to one subscription.
	 *
	 * @param object $sub     Row with endpoint, p256dh, auth.
	 * @param array  $message title, body, url, tag.
	 * @return int HTTP status (0 on failure to send).
	 */
	public static function send_one( $sub, $message ) {
		$req = self::request( $sub, $message );
		if ( ! $req ) {
			return 0;
		}
		$res = wp_remote_post( $req['url'], array( 'timeout' => 10, 'redirection' => 0, 'headers' => $req['headers'], 'body' => $req['data'] ) );
		return is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}

	/**
	 * Send many requests at once (parallel), returning HTTP status per key (0 = failed).
	 *
	 * @param array $requests key => request() result.
	 * @return int[]
	 */
	private static function send_parallel( $requests ) {
		$codes = array();
		if ( ! $requests ) {
			return $codes;
		}
		$class = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( 'Requests' ) ? 'Requests' : '' );
		if ( ! $class ) {
			foreach ( $requests as $k => $r ) {
				$res         = wp_remote_post( $r['url'], array( 'timeout' => 10, 'redirection' => 0, 'headers' => $r['headers'], 'body' => $r['data'] ) );
				$codes[ $k ] = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			}
			return $codes;
		}
		$responses = call_user_func( array( $class, 'request_multiple' ), $requests, array( 'verify' => ABSPATH . WPINC . '/certificates/ca-bundle.crt' ) );
		foreach ( $requests as $k => $r ) {
			$res         = $responses[ $k ] ?? null;
			$codes[ $k ] = is_object( $res ) && isset( $res->status_code ) && ! ( $res instanceof Exception ) ? (int) $res->status_code : 0;
		}
		return $codes;
	}

	/**
	 * Save a subscription.
	 *
	 * @param array $data endpoint, keys.p256dh, keys.auth.
	 * @return true|WP_Error
	 */
	public static function subscribe( $data ) {
		global $wpdb;
		$endpoint = esc_url_raw( (string) ( $data['endpoint'] ?? '' ), array( 'https' ) );
		$p256dh   = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $data['keys']['p256dh'] ?? '' ) );
		$auth     = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $data['keys']['auth'] ?? '' ) );
		if ( ! self::valid_endpoint( $endpoint ) || 65 !== strlen( self::unb64u( $p256dh ) ) || 16 !== strlen( self::unb64u( $auth ) ) ) {
			return new WP_Error( 'kcs_push', __( 'That subscription is not valid.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$hash = hash( 'sha256', $endpoint );
		$row  = array(
			'endpoint_hash' => $hash,
			'endpoint'      => $endpoint,
			'p256dh'        => $p256dh,
			'auth'          => $auth,
			'user_id'       => get_current_user_id(),
			'ua'            => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 190 ),
			'updated_at'    => current_time( 'mysql' ),
		);
		$table = kcs_table( 'push' );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE endpoint_hash = %s", $hash ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update( $table, $row, array( 'endpoint_hash' => $hash ) );
		} else {
			$row['created_at'] = $row['updated_at'];
			$wpdb->insert( $table, $row );
		}
		return true;
	}

	/**
	 * Remove a subscription.
	 *
	 * @param string $endpoint Endpoint.
	 */
	public static function unsubscribe( $endpoint ) {
		global $wpdb;
		$wpdb->delete( kcs_table( 'push' ), array( 'endpoint_hash' => hash( 'sha256', (string) $endpoint ) ) );
	}

	/**
	 * Number of devices with notifications on.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'push' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Queue a notification for every device (or only some users' devices); it is sent in the background.
	 *
	 * @param string     $title    Title.
	 * @param string     $body     Message.
	 * @param string     $url      Link opened when tapped.
	 * @param int[]|null $user_ids Only these users (null = everyone).
	 * @return int Devices queued.
	 */
	public static function send_all( $title, $body, $url = '', $user_ids = null ) {
		global $wpdb;
		$table = kcs_table( 'push' );
		if ( is_array( $user_ids ) ) {
			$user_ids = array_filter( array_map( 'absint', $user_ids ) );
			if ( ! $user_ids ) {
				return 0;
			}
			$ids = $wpdb->get_col( "SELECT id FROM $table WHERE user_id IN (" . implode( ',', $user_ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$ids = $wpdb->get_col( "SELECT id FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( ! $ids ) {
			return 0;
		}
		$job   = array(
			'id'      => wp_generate_password( 8, false, false ),
			'message' => array(
				'title' => wp_strip_all_tags( $title ),
				'body'  => wp_strip_all_tags( $body ),
				'url'   => $url ? esc_url_raw( $url ) : home_url( '/' ),
				'tag'   => 'kcs-' . time(),
				'icon'  => class_exists( 'KCS_PWA' ) ? KCS_PWA::url( 'icon', array( 'size' => 192, 'v' => KCS_PWA::version() ) ) : '',
			),
			'ids'     => array_map( 'intval', $ids ),
			'sent'    => 0,
			'failed'  => 0,
			'date'    => current_time( 'mysql' ),
		);
		$queue   = (array) get_option( 'kcs_push_queue', array() );
		$queue[] = $job;
		update_option( 'kcs_push_queue', $queue, false );
		// Send in the background so the page (or the visitor who triggered "we're live") never waits.
		if ( ! wp_next_scheduled( 'kcs_push_batch' ) ) {
			wp_schedule_single_event( time(), 'kcs_push_batch' );
		}
		spawn_cron();
		return count( $ids );
	}

	/**
	 * Send up to 100 queued notifications; schedule the rest.
	 */
	public static function process_queue() {
		global $wpdb;
		$queue  = (array) get_option( 'kcs_push_queue', array() );
		$budget = 500;
		$table  = kcs_table( 'push' );
		foreach ( $queue as $i => &$job ) {
			while ( $budget > 0 && $job['ids'] ) {
				// 50 phones at a time, sent in parallel.
				$chunk   = array_splice( $job['ids'], 0, min( 50, $budget ) );
				$budget -= count( $chunk );
				$subs    = $wpdb->get_results( 'SELECT * FROM ' . $table . ' WHERE id IN (' . implode( ',', array_map( 'intval', $chunk ) ) . ')', OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$reqs    = array();
				foreach ( $subs as $id => $sub ) {
					$r = self::request( $sub, $job['message'] );
					if ( $r ) {
						$reqs[ $id ] = $r;
					} else {
						++$job['failed'];
					}
				}
				foreach ( self::send_parallel( $reqs ) as $id => $code ) {
					if ( $code >= 200 && $code < 300 ) {
						++$job['sent'];
					} else {
						++$job['failed'];
						if ( in_array( $code, array( 404, 410 ), true ) ) {
							$wpdb->delete( $table, array( 'id' => $id ) ); // Device unsubscribed or app removed.
						}
					}
				}
			}
			if ( ! $job['ids'] ) {
				self::log( $job );
				unset( $queue[ $i ] );
			}
		}
		unset( $job );
		update_option( 'kcs_push_queue', array_values( $queue ), false );
		if ( $queue && ! wp_next_scheduled( 'kcs_push_batch' ) ) {
			wp_schedule_single_event( time() + 30, 'kcs_push_batch' );
		}
	}

	/**
	 * Remember a finished notification for the history list.
	 *
	 * @param array $job Job.
	 */
	private static function log( $job ) {
		$log = (array) get_option( 'kcs_push_log', array() );
		array_unshift( $log, array( 'title' => $job['message']['title'], 'body' => $job['message']['body'], 'sent' => $job['sent'], 'failed' => $job['failed'], 'date' => $job['date'] ) );
		update_option( 'kcs_push_log', array_slice( $log, 0, 20 ), false );
	}

	/**
	 * "We're live" notification when the theme sees the YouTube stream start.
	 *
	 * @param array $status Live status.
	 */
	public static function on_live( $status ) {
		if ( '1' !== (string) kcs_get_option( 'pwa_live_push', '1' ) ) {
			return;
		}
		$church = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		/* translators: %s: church name */
		self::send_all( sprintf( __( '%s is live now', 'kingdom-church-suite' ), $church ), ! empty( $status['title'] ) ? $status['title'] : __( 'Join the service from wherever you are.', 'kingdom-church-suite' ), home_url( '/#live' ) );
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			'kcs/v1',
			'/push/subscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( $req ) {
					if ( ! kcs_rate_limit( 'push_sub', 20, HOUR_IN_SECONDS ) ) {
						return new WP_Error( 'kcs_rate', __( 'Too many requests.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
					}
					$r = self::subscribe( (array) $req->get_json_params() );
					return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'ok' => true ) );
				},
			)
		);
		register_rest_route(
			'kcs/v1',
			'/push/unsubscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( $req ) {
					self::unsubscribe( (string) ( $req->get_json_params()['endpoint'] ?? '' ) );
					return rest_ensure_response( array( 'ok' => true ) );
				},
			)
		);
	}
}
