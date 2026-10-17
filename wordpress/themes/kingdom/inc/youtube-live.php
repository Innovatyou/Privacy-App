<?php
/**
 * YouTube live: "always live" from the church's channel.
 *
 * The church pastes its channel link, @handle or channel ID once. The theme then:
 * - finds the channel ID automatically (no need to dig for "UC…");
 * - checks every minute or so whether the channel is live, about to go live, or offline
 *   (with a YouTube API key if one is set, otherwise from the channel's public /live page);
 * - shows the live video with chat, the scheduled stream's waiting room, or a replay;
 * - updates open pages by itself when the stream starts or ends (no refresh needed).
 *
 * One check is shared by all visitors (cached), so traffic never multiplies requests to YouTube.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seconds a live status is reused before YouTube is checked again.
 *
 * @param string $state live|upcoming|offline.
 * @return int
 */
function kingdom_ytlive_cache_ttl( $state ) {
	return (int) apply_filters( 'kingdom_ytlive_cache_ttl', 'offline' === $state ? 120 : 60, $state );
}

/**
 * Fetch a YouTube page as a regular browser would (skipping the EU consent screen).
 *
 * @param string $url URL.
 * @return string HTML ('' on failure).
 */
function kingdom_ytlive_fetch( $url ) {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'    => 8,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
			'headers'    => array(
				'Accept-Language' => 'en-US,en;q=0.9',
				'Cookie'          => 'CONSENT=YES+1; SOCS=CAI',
			),
		)
	);
	return is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
}

/**
 * The church's channel ID, worked out from whatever they typed: a UC… ID, a channel link,
 * an @handle or a /c/ or /user/ link. The result is remembered until the setting changes.
 *
 * @return string UC… ID or ''.
 */
function kingdom_ytlive_channel_id() {
	$raw = trim( (string) get_theme_mod( 'kingdom_ytlive_channel', '' ) );
	if ( '' === $raw && function_exists( 'kcs_get_option' ) ) {
		$raw = trim( (string) kcs_get_option( 'yt_channel_id' ) );
	}
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '~(UC[A-Za-z0-9_-]{22})~', $raw, $m ) ) {
		return $m[1];
	}
	$saved = get_option( 'kingdom_ytlive_resolved', array() );
	if ( is_array( $saved ) && ( $saved['raw'] ?? '' ) === $raw && ! empty( $saved['id'] ) ) {
		return $saved['id'];
	}
	if ( get_transient( 'kingdom_ytlive_resolve_failed' ) === $raw ) {
		return '';
	}
	if ( preg_match( '~^@?([A-Za-z0-9._-]{3,100})$~', $raw, $m ) ) {
		$url = 'https://www.youtube.com/@' . $m[1];
	} elseif ( preg_match( '~youtube\.com/((?:@|c/|user/)[^/?#]+)~i', $raw, $m ) ) {
		$url = 'https://www.youtube.com/' . $m[1];
	} else {
		return '';
	}
	$html = kingdom_ytlive_fetch( $url );
	$id   = '';
	foreach ( array( '~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', '~"externalId":"(UC[A-Za-z0-9_-]{22})"~', '~"channelId":"(UC[A-Za-z0-9_-]{22})"~', '~itemprop="identifier" content="(UC[A-Za-z0-9_-]{22})"~' ) as $re ) {
		if ( preg_match( $re, $html, $m ) ) {
			$id = $m[1];
			break;
		}
	}
	if ( $id ) {
		update_option( 'kingdom_ytlive_resolved', array( 'raw' => $raw, 'id' => $id ), false );
	} else {
		set_transient( 'kingdom_ytlive_resolve_failed', $raw, 10 * MINUTE_IN_SECONDS );
	}
	return $id;
}

/**
 * YouTube Data API key (theme setting, or the one saved in Church Suite).
 *
 * @return string
 */
function kingdom_ytlive_api_key() {
	$key = trim( (string) get_theme_mod( 'kingdom_ytlive_api_key', '' ) );
	if ( '' === $key && function_exists( 'kcs_get_option' ) ) {
		$key = trim( (string) kcs_get_option( 'yt_api_key' ) );
	}
	return $key;
}

/**
 * Ask YouTube what the channel is doing right now.
 *
 * @param string $channel Channel ID.
 * @return array state (live|upcoming|offline), video_id, title, starts_at (unix), source.
 */
function kingdom_ytlive_check( $channel ) {
	$key = kingdom_ytlive_api_key();
	if ( $key ) {
		foreach ( array( 'live', 'upcoming' ) as $type ) {
			$res = wp_remote_get(
				add_query_arg(
					array(
						'part'       => 'snippet',
						'channelId'  => $channel,
						'eventType'  => $type,
						'type'       => 'video',
						'order'      => 'date',
						'maxResults' => 1,
						'key'        => $key,
					),
					'https://www.googleapis.com/youtube/v3/search'
				),
				array( 'timeout' => 8 )
			);
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				break; // Quota or key problem: fall back to the public page below.
			}
			$item = json_decode( wp_remote_retrieve_body( $res ), true )['items'][0] ?? null;
			if ( $item && ! empty( $item['id']['videoId'] ) ) {
				return array(
					'state'     => $type,
					'video_id'  => $item['id']['videoId'],
					'title'     => html_entity_decode( (string) ( $item['snippet']['title'] ?? '' ), ENT_QUOTES ),
					'starts_at' => 0,
					'source'    => 'api',
				);
			}
			if ( 'upcoming' === $type ) {
				return array( 'state' => 'offline', 'video_id' => '', 'title' => '', 'starts_at' => 0, 'source' => 'api' );
			}
		}
	}

	// No key: the channel's public /live page points at the current (or next scheduled) stream.
	$html = kingdom_ytlive_fetch( 'https://www.youtube.com/channel/' . rawurlencode( $channel ) . '/live' );
	$out  = array( 'state' => 'offline', 'video_id' => '', 'title' => '', 'starts_at' => 0, 'source' => 'page' );
	if ( '' === $html ) {
		$out['source'] = 'error';
		return $out;
	}
	if ( ! preg_match( '~<link rel="canonical" href="https://www\.youtube\.com/watch\?v=([A-Za-z0-9_-]{11})"~', $html, $m ) ) {
		return $out;
	}
	$out['video_id'] = $m[1];
	if ( preg_match( '~<meta name="title" content="([^"]*)"~', $html, $t ) ) {
		$out['title'] = html_entity_decode( $t[1], ENT_QUOTES );
	}
	if ( false !== strpos( $html, '"isLiveNow":true' ) ) {
		$out['state'] = 'live';
	} elseif ( false !== strpos( $html, '"isUpcoming":true' ) || false !== strpos( $html, '"upcomingEventData"' ) ) {
		$out['state'] = 'upcoming';
		if ( preg_match( '~"upcomingEventData":\{"startTime":"(\d+)"~', $html, $s ) || preg_match( '~"scheduledStartTime":"(\d+)"~', $html, $s ) ) {
			$out['starts_at'] = (int) $s[1];
		}
	} else {
		$out['video_id'] = ''; // A finished stream: treat as offline.
		$out['title']    = '';
	}
	return $out;
}

/**
 * Everything the YouTube live section needs, cached and shared by all visitors.
 *
 * @param bool $force Check YouTube now instead of using the cached answer.
 * @return array|null mode (channel|video), state, video_id, title, starts_at, embed, chat, watch, label, next_service, checked_at.
 */
function kingdom_youtube_live( $force = false ) {
	$channel  = kingdom_ytlive_channel_id();
	$raw      = trim( (string) get_theme_mod( 'kingdom_ytlive_url', 'xzoaPmLegBE' ) );
	$replay   = preg_match( '/^[A-Za-z0-9_-]{11}$/', $raw ) ? $raw : kingdom_youtube_id( $raw );
	$autoplay = (bool) get_theme_mod( 'kingdom_ytlive_autoplay', false );

	if ( $channel ) {
		$status = $force ? false : get_transient( 'kingdom_ytlive_status' );
		if ( ! is_array( $status ) || ( $status['channel'] ?? '' ) !== $channel ) {
			$status = array_merge( kingdom_ytlive_check( $channel ), array( 'channel' => $channel, 'checked_at' => time() ) );
			set_transient( 'kingdom_ytlive_status', $status, kingdom_ytlive_cache_ttl( $status['state'] ) );
		}
		$video = $status['video_id'] ? $status['video_id'] : $replay;
		$out   = array(
			'mode'       => 'channel',
			'state'      => $status['state'],
			'video_id'   => $video,
			'title'      => $status['title'],
			'starts_at'  => (int) $status['starts_at'],
			'source'     => $status['source'],
			'checked_at' => (int) $status['checked_at'],
			'watch'      => $status['video_id'] ? 'https://www.youtube.com/watch?v=' . $status['video_id'] : 'https://www.youtube.com/channel/' . $channel . '/live',
		);
	} elseif ( $replay ) {
		$out = array( 'mode' => 'video', 'state' => 'video', 'video_id' => $replay, 'title' => '', 'starts_at' => 0, 'source' => 'setting', 'checked_at' => time(), 'watch' => 'https://www.youtube.com/watch?v=' . $replay );
	} else {
		return null;
	}

	$play         = $autoplay && 'offline' !== $out['state'];
	$out['embed'] = $out['video_id'] ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $out['video_id'] ) . '?rel=0&modestbranding=1&playsinline=1' . ( $play ? '&autoplay=1&mute=1' : '' ) : '';
	$out['chat']  = get_theme_mod( 'kingdom_ytlive_chat', false ) && in_array( $out['state'], array( 'live', 'upcoming', 'video' ), true ) && $out['video_id']
		? 'https://www.youtube.com/live_chat?v=' . rawurlencode( $out['video_id'] ) . '&embed_domain=' . rawurlencode( (string) wp_parse_url( home_url(), PHP_URL_HOST ) )
		: '';
	$next                = class_exists( 'KCS_Livestream' ) ? KCS_Livestream::next_service() : null;
	$out['next_service'] = $next ? array( 'name' => $next['name'], 'timestamp' => (int) $next['timestamp'], 'label' => wp_date( 'l g:i a', $next['timestamp'] ) ) : null;
	$out['label']        = kingdom_ytlive_label( $out );
	return $out;
}

/**
 * Words for the status badge.
 *
 * @param array $s Status.
 * @return string
 */
function kingdom_ytlive_label( $s ) {
	switch ( $s['state'] ) {
		case 'live':
			return __( 'Live now', 'kingdom' );
		case 'upcoming':
			return $s['starts_at'] ? kingdom_ytlive_when( $s['starts_at'] ) : __( 'Starting soon', 'kingdom' );
		case 'offline':
			if ( ! empty( $s['next_service'] ) ) {
				/* translators: 1: service name, 2: day and time */
				return sprintf( __( 'Not live right now · Next: %1$s, %2$s', 'kingdom' ), $s['next_service']['name'], $s['next_service']['label'] );
			}
			return $s['video_id'] ? __( 'Not live right now · Watch our latest service', 'kingdom' ) : __( 'Not live right now', 'kingdom' );
	}
	return '';
}

/**
 * "Starts today at 9:00 am" / "Starts tomorrow at …" / "Starts Sunday at …".
 *
 * @param int $ts Unix time.
 * @return string
 */
function kingdom_ytlive_when( $ts ) {
	$day  = wp_date( 'Y-m-d', $ts );
	$time = wp_date( get_option( 'time_format' ), $ts );
	if ( wp_date( 'Y-m-d' ) === $day ) {
		/* translators: %s: time */
		return sprintf( __( 'Starts today at %s', 'kingdom' ), $time );
	}
	if ( wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) === $day ) {
		/* translators: %s: time */
		return sprintf( __( 'Starts tomorrow at %s', 'kingdom' ), $time );
	}
	/* translators: 1: weekday, 2: time */
	return sprintf( __( 'Starts %1$s at %2$s', 'kingdom' ), wp_date( 'l', $ts ), $time );
}

/**
 * Public status endpoint the section polls to switch to the stream as soon as it starts.
 */
function kingdom_ytlive_rest() {
	register_rest_route(
		'kingdom/v1',
		'/live',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$s = kingdom_youtube_live();
				$r = rest_ensure_response( $s ? array_intersect_key( $s, array_flip( array( 'state', 'video_id', 'title', 'starts_at', 'embed', 'chat', 'watch', 'label' ) ) ) : array( 'state' => 'none' ) );
				$r->header( 'Cache-Control', 'public, max-age=30' );
				return $r;
			},
		)
	);
}
add_action( 'rest_api_init', 'kingdom_ytlive_rest' );

/**
 * Forget the cached channel lookup and status when the settings change.
 */
function kingdom_ytlive_reset() {
	delete_transient( 'kingdom_ytlive_status' );
	delete_transient( 'kingdom_ytlive_resolve_failed' );
}
add_action( 'customize_save_after', 'kingdom_ytlive_reset' );
add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), 'kingdom_ytlive_reset' );

/**
 * Status line for church staff under the live stream settings (Church Admin → Website).
 *
 * @param string $group Settings group.
 */
function kingdom_ytlive_admin_status( $group ) {
	if ( 'media' !== $group ) {
		return;
	}
	$force = ! empty( $_GET['ytlive_check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw   = trim( (string) get_theme_mod( 'kingdom_ytlive_channel', '' ) );
	$s     = kingdom_youtube_live( $force );
	if ( $raw && ! kingdom_ytlive_channel_id() ) {
		$msg  = __( 'We could not find that YouTube channel. Paste the link from your channel page, for example https://www.youtube.com/@yourchurch.', 'kingdom' );
		$type = 'error';
	} elseif ( $s && 'channel' === $s['mode'] ) {
		/* translators: 1: status, 2: time */
		$msg  = sprintf( __( 'Channel connected · %1$s (checked %2$s)', 'kingdom' ), $s['label'], wp_date( get_option( 'time_format' ), $s['checked_at'] ) );
		$type = 'error' === $s['source'] ? 'error' : 'success';
		if ( 'error' === $s['source'] ) {
			$msg = __( 'Channel connected, but YouTube did not answer just now. We will keep trying.', 'kingdom' );
		}
	} else {
		$msg  = __( 'Showing one video. Add your channel to switch to your live stream automatically.', 'kingdom' );
		$type = 'info';
	}
	echo '<p class="kcs-alert kcs-alert--' . esc_attr( $type ) . '">' . esc_html( $msg ) . ' <a href="' . esc_url( add_query_arg( 'ytlive_check', 1 ) ) . '">' . esc_html__( 'Check now', 'kingdom' ) . '</a></p>';
}
add_action( 'kcs_website_group_intro', 'kingdom_ytlive_admin_status' );
