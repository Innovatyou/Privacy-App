<?php
/**
 * Modern church digital features: sermon podcast feed, "add to calendar"
 * ICS files, installable web-app manifest and verse of the day.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Features.
 */
class KCS_Features {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_feed' ) );
		add_action( 'template_redirect', array( __CLASS__, 'ics' ) );
		add_action( 'template_redirect', array( __CLASS__, 'manifest' ) );
		add_action( 'wp_head', array( __CLASS__, 'head_links' ), 2 );
	}

	/**
	 * Podcast feed at /feed/sermon-podcast/.
	 */
	public static function register_feed() {
		add_feed( 'sermon-podcast', array( __CLASS__, 'render_podcast' ) );
	}

	/**
	 * Head links: podcast feed, manifest, theme colour.
	 */
	public static function head_links() {
		echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr( get_bloginfo( 'name' ) . ' — ' . __( 'Sermon Podcast', 'kingdom-church-suite' ) ) . '" href="' . esc_url( get_feed_link( 'sermon-podcast' ) ) . '">' . "\n";
		echo '<link rel="manifest" href="' . esc_url( add_query_arg( 'kcs_manifest', 1, home_url( '/' ) ) ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( kcs_get_option( 'email_color', '#5b2a86' ) ) . '">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	}

	/**
	 * iTunes/Spotify compatible RSS feed of sermons with audio.
	 */
	public static function render_podcast() {
		$sermons = get_posts(
			array(
				'post_type'      => 'kcs_sermon',
				'posts_per_page' => 100,
				'meta_query'     => array( array( 'key' => '_kcs_audio_url', 'value' => '', 'compare' => '!=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$church  = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		$image   = kcs_get_option( 'seo_default_image', kcs_get_option( 'church_logo' ) );
		header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
		?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo esc_html( $church . ' — ' . __( 'Sermons', 'kingdom-church-suite' ) ); ?></title>
	<link><?php echo esc_url( get_post_type_archive_link( 'kcs_sermon' ) ); ?></link>
	<atom:link href="<?php echo esc_url( get_feed_link( 'sermon-podcast' ) ); ?>" rel="self" type="application/rss+xml" />
	<language><?php echo esc_html( get_bloginfo( 'language' ) ); ?></language>
	<description><?php echo esc_html( get_bloginfo( 'description' ) ); ?></description>
	<itunes:author><?php echo esc_html( $church ); ?></itunes:author>
	<itunes:owner><itunes:name><?php echo esc_html( $church ); ?></itunes:name><itunes:email><?php echo esc_html( kcs_get_option( 'church_email', get_option( 'admin_email' ) ) ); ?></itunes:email></itunes:owner>
	<itunes:explicit>false</itunes:explicit>
	<itunes:category text="Religion &amp; Spirituality"><itunes:category text="Christianity" /></itunes:category>
		<?php if ( $image ) : ?>
	<itunes:image href="<?php echo esc_url( $image ); ?>" />
	<?php endif; ?>
		<?php
		foreach ( $sermons as $s ) :
			$audio    = get_post_meta( $s->ID, '_kcs_audio_url', true );
			$speakers = wp_get_post_terms( $s->ID, 'kcs_speaker', array( 'fields' => 'names' ) );
			?>
	<item>
		<title><?php echo esc_html( get_the_title( $s ) ); ?></title>
		<link><?php echo esc_url( get_permalink( $s ) ); ?></link>
		<guid isPermaLink="false"><?php echo esc_html( $s->guid ); ?></guid>
		<pubDate><?php echo esc_html( get_post_time( 'r', true, $s ) ); ?></pubDate>
		<description><?php echo esc_html( wp_trim_words( $s->post_excerpt ? $s->post_excerpt : wp_strip_all_tags( $s->post_content ), 60 ) ); ?></description>
		<enclosure url="<?php echo esc_url( $audio ); ?>" type="audio/mpeg" length="0" />
		<itunes:author><?php echo esc_html( $speakers && ! is_wp_error( $speakers ) ? $speakers[0] : $church ); ?></itunes:author>
			<?php if ( get_post_meta( $s->ID, '_kcs_duration', true ) ) : ?>
		<itunes:duration><?php echo esc_html( get_post_meta( $s->ID, '_kcs_duration', true ) ); ?></itunes:duration>
		<?php endif; ?>
			<?php if ( has_post_thumbnail( $s ) ) : ?>
		<itunes:image href="<?php echo esc_url( get_the_post_thumbnail_url( $s, 'large' ) ); ?>" />
		<?php endif; ?>
	</item>
		<?php endforeach; ?>
</channel>
</rss>
		<?php
	}

	/**
	 * ICS download: ?kcs_ics=EVENT_ID.
	 */
	public static function ics() {
		if ( empty( $_GET['kcs_ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$post = get_post( absint( $_GET['kcs_ics'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post || 'kcs_event' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$tz    = wp_timezone();
		$start = get_post_meta( $post->ID, '_kcs_start', true );
		$end   = get_post_meta( $post->ID, '_kcs_end', true );
		if ( ! $start ) {
			return;
		}
		$utc   = new DateTimeZone( 'UTC' );
		$s     = ( new DateTimeImmutable( $start, $tz ) )->setTimezone( $utc );
		$e     = $end ? ( new DateTimeImmutable( $end, $tz ) )->setTimezone( $utc ) : $s->modify( '+2 hours' );
		$esc   = function ( $text ) {
			return addcslashes( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ), ',;\\' );
		};
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Kingdom Church Suite//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT',
			'UID:' . $post->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . $s->format( 'Ymd\THis\Z' ),
			'DTEND:' . $e->format( 'Ymd\THis\Z' ),
			'SUMMARY:' . $esc( get_the_title( $post ) ),
			'DESCRIPTION:' . $esc( wp_trim_words( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 50 ) . ' ' . get_permalink( $post ) ),
			'LOCATION:' . $esc( trim( get_post_meta( $post->ID, '_kcs_location', true ) . ' ' . get_post_meta( $post->ID, '_kcs_address', true ) ) ),
			'URL:' . get_permalink( $post ),
			'END:VEVENT',
			'END:VCALENDAR',
		);
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $post->post_name ) . '.ics"' );
		echo implode( "\r\n", $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Google Calendar link for an event.
	 *
	 * @param int $post_id Event.
	 * @return string
	 */
	public static function google_calendar_url( $post_id ) {
		$tz    = wp_timezone();
		$start = get_post_meta( $post_id, '_kcs_start', true );
		if ( ! $start ) {
			return '';
		}
		$end = get_post_meta( $post_id, '_kcs_end', true );
		$utc = new DateTimeZone( 'UTC' );
		$s   = ( new DateTimeImmutable( $start, $tz ) )->setTimezone( $utc );
		$e   = $end ? ( new DateTimeImmutable( $end, $tz ) )->setTimezone( $utc ) : $s->modify( '+2 hours' );
		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => rawurlencode( get_the_title( $post_id ) ),
				'dates'    => $s->format( 'Ymd\THis\Z' ) . '/' . $e->format( 'Ymd\THis\Z' ),
				'details'  => rawurlencode( get_permalink( $post_id ) ),
				'location' => rawurlencode( trim( get_post_meta( $post_id, '_kcs_location', true ) . ' ' . get_post_meta( $post_id, '_kcs_address', true ) ) ),
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * Web app manifest (Add to home screen).
	 */
	public static function manifest() {
		if ( empty( $_GET['kcs_manifest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$icon_id = get_option( 'site_icon' );
		$icons   = array();
		foreach ( array( 192, 512 ) as $size ) {
			$url = $icon_id ? wp_get_attachment_image_url( $icon_id, array( $size, $size ) ) : '';
			if ( $url ) {
				$icons[] = array( 'src' => $url, 'sizes' => $size . 'x' . $size, 'type' => 'image/png' );
			}
		}
		$name = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		wp_send_json(
			array(
				'name'             => $name,
				'short_name'       => mb_substr( $name, 0, 12 ),
				'start_url'        => home_url( '/' ),
				'display'          => 'standalone',
				'background_color' => '#ffffff',
				'theme_color'      => kcs_get_option( 'email_color', '#5b2a86' ),
				'icons'            => $icons,
			)
		);
	}

	/**
	 * Verse of the day (rotates daily; filterable).
	 *
	 * @return array{text:string,ref:string}
	 */
	public static function verse_of_the_day() {
		$verses = apply_filters(
			'kcs_verses',
			array(
				array( 'For I know the plans I have for you, declares the LORD, plans to prosper you and not to harm you, plans to give you hope and a future.', 'Jeremiah 29:11' ),
				array( 'I can do all this through him who gives me strength.', 'Philippians 4:13' ),
				array( 'Trust in the LORD with all your heart and lean not on your own understanding.', 'Proverbs 3:5' ),
				array( 'The LORD is my shepherd, I lack nothing.', 'Psalm 23:1' ),
				array( 'And we know that in all things God works for the good of those who love him.', 'Romans 8:28' ),
				array( 'Be strong and courageous. Do not be afraid; do not be discouraged, for the LORD your God will be with you wherever you go.', 'Joshua 1:9' ),
				array( 'Come to me, all you who are weary and burdened, and I will give you rest.', 'Matthew 11:28' ),
				array( 'But those who hope in the LORD will renew their strength. They will soar on wings like eagles.', 'Isaiah 40:31' ),
				array( 'For God so loved the world that he gave his one and only Son, that whoever believes in him shall not perish but have eternal life.', 'John 3:16' ),
				array( 'Do not be anxious about anything, but in every situation, by prayer and petition, with thanksgiving, present your requests to God.', 'Philippians 4:6' ),
				array( 'The joy of the LORD is your strength.', 'Nehemiah 8:10' ),
				array( 'Your word is a lamp for my feet, a light on my path.', 'Psalm 119:105' ),
			)
		);
		$i = (int) current_time( 'z' ) % count( $verses );
		return array( 'text' => $verses[ $i ][0], 'ref' => $verses[ $i ][1] );
	}
}
