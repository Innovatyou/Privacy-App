<?php
/**
 * YouTube live streaming.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Livestream.
 */
class KCS_Livestream {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'update_option_kcs_settings', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Clear cached status.
	 */
	public static function flush() {
		delete_transient( 'kcs_live_status' );
	}

	/**
	 * Parsed weekly service schedule.
	 *
	 * @return array[] Each: day (Sunday…), time (H:i), name.
	 */
	public static function schedule() {
		$out = array();
		foreach ( explode( "\n", (string) kcs_get_option( 'service_schedule' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) >= 2 && preg_match( '/^\d{1,2}:\d{2}$/', $parts[1] ) ) {
				$out[] = array(
					'day'  => ucfirst( strtolower( $parts[0] ) ),
					'time' => $parts[1],
					'name' => isset( $parts[2] ) ? $parts[2] : __( 'Service', 'kingdom-church-suite' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Next service occurrence.
	 *
	 * @return array|null ['name','timestamp' (UTC), 'iso', 'day', 'time'].
	 */
	public static function next_service() {
		$tz   = wp_timezone();
		$now  = new DateTimeImmutable( 'now', $tz );
		$best = null;
		foreach ( self::schedule() as $s ) {
			try {
				$candidate = new DateTimeImmutable( 'this ' . $s['day'] . ' ' . $s['time'], $tz );
			} catch ( Exception $e ) {
				continue;
			}
			if ( $candidate < $now ) {
				$candidate = $candidate->modify( '+1 week' );
			}
			if ( ! $best || $candidate < $best['dt'] ) {
				$best = array_merge( $s, array( 'dt' => $candidate ) );
			}
		}
		if ( ! $best ) {
			return null;
		}
		return array(
			'name'      => $best['name'],
			'day'       => $best['day'],
			'time'      => $best['time'],
			'timestamp' => $best['dt']->getTimestamp(),
			'iso'       => $best['dt']->format( DATE_ATOM ),
		);
	}

	/**
	 * Is now within a service window (30 min before to 3 h after)?
	 *
	 * @return bool
	 */
	public static function in_service_window() {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );
		foreach ( self::schedule() as $s ) {
			try {
				$start = new DateTimeImmutable( 'today ' . $s['time'], $tz );
			} catch ( Exception $e ) {
				continue;
			}
			if ( $now->format( 'l' ) !== $s['day'] ) {
				continue;
			}
			if ( $now >= $start->modify( '-30 minutes' ) && $now <= $start->modify( '+3 hours' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Current live status.
	 *
	 * @param bool $force Bypass cache.
	 * @return array is_live, video_id, title, thumbnail, upcoming[], source, channel_id, next_service.
	 */
	public static function status( $force = false ) {
		$cached = get_transient( 'kcs_live_status' );
		if ( ! $force && is_array( $cached ) ) {
			return $cached;
		}
		$channel = trim( (string) kcs_get_option( 'yt_channel_id' ) );
		$key     = trim( (string) kcs_get_option( 'yt_api_key' ) );
		$manual  = KCS_Post_Types::youtube_id( kcs_get_option( 'yt_manual_video' ) );

		$status = array(
			'is_live'      => false,
			'video_id'     => '',
			'title'        => '',
			'thumbnail'    => '',
			'upcoming'     => array(),
			'source'       => 'none',
			'channel_id'   => $channel,
			'next_service' => self::next_service(),
		);

		if ( $manual ) {
			$status['video_id'] = $manual;
			$status['is_live']  = true;
			$status['source']   = 'manual';
		} elseif ( $channel && $key && ( '1' !== kcs_get_option( 'yt_only_service_times', '1' ) || self::in_service_window() || $force ) ) {
			$live = self::search( $channel, $key, 'live' );
			if ( $live ) {
				$status = array_merge( $status, $live, array( 'is_live' => true, 'source' => 'api' ) );
			} else {
				$status['upcoming'] = self::search( $channel, $key, 'upcoming', 3 );
				$status['source']   = 'api';
			}
		} elseif ( $channel ) {
			$status['source'] = 'channel_embed';
		}

		if ( ! $status['video_id'] && ! $status['is_live'] ) {
			$fallback = KCS_Post_Types::youtube_id( kcs_get_option( 'yt_fallback_video' ) );
			if ( $fallback ) {
				$status['fallback_video_id'] = $fallback;
			}
		}

		$status = apply_filters( 'kcs_live_status', $status );
		set_transient( 'kcs_live_status', $status, max( 1, (int) kcs_get_option( 'yt_cache_minutes', 3 ) ) * MINUTE_IN_SECONDS );
		return $status;
	}

	/**
	 * YouTube search for live/upcoming broadcasts.
	 *
	 * @param string $channel Channel ID.
	 * @param string $key     API key.
	 * @param string $type    live|upcoming.
	 * @param int    $max     Max results.
	 * @return array
	 */
	private static function search( $channel, $key, $type, $max = 1 ) {
		$url      = add_query_arg(
			array(
				'part'       => 'snippet',
				'channelId'  => $channel,
				'eventType'  => $type,
				'type'       => 'video',
				'maxResults' => $max,
				'key'        => $key,
			),
			'https://www.googleapis.com/youtube/v3/search'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data  = json_decode( wp_remote_retrieve_body( $response ), true );
		$items = isset( $data['items'] ) ? $data['items'] : array();
		$out   = array();
		foreach ( $items as $item ) {
			$out[] = array(
				'video_id'  => kcs_array_get( $item, 'id.videoId' ),
				'title'     => html_entity_decode( (string) kcs_array_get( $item, 'snippet.title' ), ENT_QUOTES ),
				'thumbnail' => kcs_array_get( $item, 'snippet.thumbnails.high.url' ),
			);
		}
		if ( 'live' === $type ) {
			return $out ? $out[0] : array();
		}
		return $out;
	}

	/**
	 * Player HTML.
	 *
	 * @param array $atts chat (yes/no), autoplay (yes/no), platform (youtube|facebook|custom).
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts   = shortcode_atts(
			array(
				'chat'     => 'no',
				'autoplay' => 'yes',
				'platform' => 'youtube',
				'title'    => '',
			),
			$atts,
			'kcs_livestream'
		);
		$status = self::status();
		$embed  = '';

		if ( 'custom' === $atts['platform'] && kcs_get_option( 'custom_stream_embed' ) ) {
			$embed = kcs_get_option( 'custom_stream_embed' );
		} elseif ( 'facebook' === $atts['platform'] && kcs_get_option( 'facebook_live_url' ) ) {
			$src   = add_query_arg(
				array(
					'href'           => rawurlencode( kcs_get_option( 'facebook_live_url' ) ),
					'tabs'           => 'timeline',
					'show_text'      => 'false',
					'adapt_container_width' => 'true',
				),
				'https://www.facebook.com/plugins/page.php'
			);
			$embed = '<iframe src="' . esc_url( $src ) . '" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
		} else {
			$auto = 'yes' === $atts['autoplay'] ? 1 : 0;
			if ( $status['video_id'] ) {
				$src = 'https://www.youtube.com/embed/' . rawurlencode( $status['video_id'] ) . '?autoplay=' . $auto . '&rel=0&modestbranding=1';
			} elseif ( $status['channel_id'] && in_array( $status['source'], array( 'channel_embed' ), true ) ) {
				$src = 'https://www.youtube.com/embed/live_stream?channel=' . rawurlencode( $status['channel_id'] ) . '&autoplay=' . $auto;
			} elseif ( ! empty( $status['fallback_video_id'] ) ) {
				$src = 'https://www.youtube.com/embed/' . rawurlencode( $status['fallback_video_id'] ) . '?rel=0';
			} elseif ( $status['channel_id'] ) {
				$src = 'https://www.youtube.com/embed/live_stream?channel=' . rawurlencode( $status['channel_id'] );
			} else {
				$src = '';
			}
			if ( $src ) {
				$embed = '<iframe src="' . esc_url( $src ) . '" title="' . esc_attr__( 'Live stream', 'kingdom-church-suite' ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
			}
		}

		return kcs_get_template(
			'livestream',
			array(
				'status' => $status,
				'embed'  => $embed,
				'chat'   => 'yes' === $atts['chat'] && $status['is_live'] && $status['video_id'] && 'youtube' === $atts['platform'],
				'title'  => $atts['title'],
			)
		);
	}
}
