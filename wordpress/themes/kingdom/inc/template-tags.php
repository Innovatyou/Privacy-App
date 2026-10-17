<?php
/**
 * Template helpers.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the companion plugin active?
 *
 * @return bool
 */
function kingdom_plugin_active() {
	return class_exists( 'KCS_Plugin' );
}

/**
 * Render a homepage/page section.
 *
 * @param string $name Section slug (template-parts/sections/{name}.php).
 * @param array  $args Arguments.
 */
function kingdom_section( $name, $args = array() ) {
	get_template_part( 'template-parts/sections/' . sanitize_key( $name ), null, $args );
}

/**
 * Available homepage sections (slug => label).
 *
 * @return array
 */
function kingdom_available_sections() {
	return array(
		'hero'          => __( 'Hero', 'kingdom' ),
		'service-times' => __( 'Service times bar', 'kingdom' ),
		'welcome'       => __( 'Welcome / pastor message', 'kingdom' ),
		'live'          => __( 'Live stream', 'kingdom' ),
		'countdown'     => __( 'Next service countdown', 'kingdom' ),
		'sermons'       => __( 'Latest sermons', 'kingdom' ),
		'events'        => __( 'Upcoming events', 'kingdom' ),
		'ministries'    => __( 'Ministries', 'kingdom' ),
		'stats'         => __( 'Stats counter', 'kingdom' ),
		'partnership'   => __( 'Partnership plans', 'kingdom' ),
		'give'          => __( 'Online giving', 'kingdom' ),
		'leadership'    => __( 'Leadership', 'kingdom' ),
		'testimonies'   => __( 'Testimonies', 'kingdom' ),
		'verse'         => __( 'Verse of the day', 'kingdom' ),
		'prayer'        => __( 'Prayer request', 'kingdom' ),
		'blog'          => __( 'Latest news', 'kingdom' ),
		'newsletter'    => __( 'Newsletter sign-up', 'kingdom' ),
		'visit'         => __( 'Plan your visit', 'kingdom' ),
		'contact'       => __( 'Contact & map', 'kingdom' ),
		'cta'           => __( 'Call to action', 'kingdom' ),
	);
}

/**
 * Run a plugin shortcode, or show a helpful notice to admins when the plugin is missing.
 *
 * @param string $shortcode Shortcode string.
 * @return string
 */
function kingdom_shortcode( $shortcode ) {
	if ( kingdom_plugin_active() ) {
		return do_shortcode( $shortcode );
	}
	if ( current_user_can( 'activate_plugins' ) ) {
		return '<div class="k-notice">' . esc_html__( 'Activate the Kingdom Church Suite plugin to show this feature.', 'kingdom' ) . '</div>';
	}
	return '';
}

/**
 * Weekly services (from the plugin schedule, or the Customizer fallback).
 *
 * @return array[] day, time (display), name.
 */
function kingdom_service_times() {
	$out = array();
	if ( kingdom_plugin_active() ) {
		foreach ( KCS_Livestream::schedule() as $s ) {
			$out[] = array(
				'day'  => date_i18n( 'l', strtotime( $s['day'] ) ),
				'time' => date_i18n( get_option( 'time_format' ), strtotime( $s['time'] ) ),
				'name' => $s['name'],
			);
		}
	}
	if ( ! $out ) {
		$raw = get_theme_mod( 'kingdom_service_times', "Sunday|8:00 AM|First Service\nSunday|10:30 AM|Celebration Service\nWednesday|6:00 PM|Bible Study" );
		foreach ( explode( "\n", $raw ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) );
			if ( count( $p ) >= 2 ) {
				$out[] = array( 'day' => $p[0], 'time' => $p[1], 'name' => $p[2] ?? '' );
			}
		}
	}
	return $out;
}

/**
 * Social profile URLs.
 *
 * @return array network => url.
 */
function kingdom_social_links() {
	$out = array();
	foreach ( array( 'facebook', 'instagram', 'youtube', 'x', 'tiktok', 'whatsapp' ) as $net ) {
		$url = get_theme_mod( 'kingdom_social_' . $net );
		if ( ! $url && kingdom_plugin_active() && 'whatsapp' !== $net ) {
			$url = kcs_get_option( 'social_' . $net );
		}
		if ( $url ) {
			$out[ $net ] = $url;
		}
	}
	return $out;
}

/**
 * Inline SVG icon.
 *
 * @param string $name Icon.
 * @return string
 */
function kingdom_icon( $name ) {
	$paths = array(
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v8h4v-8h3l1-4h-4V8a0 0 0 0 1 0 0z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
		'youtube'   => '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .4-3.8 31 31 0 0 0-.4-3.8z"/><path d="m10 15 5-3-5-3z" fill="currentColor"/>',
		'x'         => '<path d="M4 4l16 16M20 4 4 20"/>',
		'tiktok'    => '<path d="M15 3v10.5a3.5 3.5 0 1 1-3.5-3.5M15 3c.5 2.5 2.5 4 5 4"/>',
		'whatsapp'  => '<path d="M3 21l1.6-4.6A8.5 8.5 0 1 1 8 19.6z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1.2-1.2-1.7-1-1 .7a4 4 0 0 1-2-2l.7-1-1-1.7z"/>',
		'play'      => '<path d="M8 5v14l11-7z" fill="currentColor"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'pin'       => '<path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
		'phone'     => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
		'heart'     => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11z"/>',
		'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'      => '<path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/>',
		'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'headphones' => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><rect x="3" y="14" width="4" height="7" rx="1"/><rect x="17" y="14" width="4" height="7" rx="1"/>',
		'book'      => '<path d="M4 4h6a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H4zM20 4h-6a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h7z"/>',
		'download'  => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
		'users'     => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 9M22 21a7 7 0 0 0-4-6.3"/>',
		'hands'     => '<path d="M12 21V11l-3-6a1.5 1.5 0 0 0-2.7 1.3L8 11l-3 4v6M12 21V11l3-6a1.5 1.5 0 0 1 2.7 1.3L16 11l3 4v6"/>',
		'car'       => '<path d="M5 17H3v-5l2-5h14l2 5v5h-2M5 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0M15 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0M9 17h6M3 12h18"/>',
		'child'     => '<circle cx="12" cy="5" r="3"/><path d="M12 8v7M8 11h8M10 21l2-6 2 6"/>',
		'coffee'    => '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5zM17 9h1a3 3 0 0 1 0 6h-1M8 2v3M12 2v3"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="k-icon k-icon--' . esc_attr( $name ) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Section heading.
 *
 * @param string $eyebrow Small label.
 * @param string $title   Title.
 * @param string $text    Intro.
 * @param string $align   center|left.
 */
function kingdom_section_heading( $eyebrow, $title, $text = '', $align = 'center' ) {
	echo '<header class="k-section__head k-section__head--' . esc_attr( $align ) . '" data-reveal>';
	if ( $eyebrow ) {
		echo '<span class="k-eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	if ( $title ) {
		echo '<h2 class="k-section__title">' . esc_html( $title ) . '</h2>';
	}
	if ( $text ) {
		echo '<p class="k-section__text">' . esc_html( $text ) . '</p>';
	}
	echo '</header>';
}

/**
 * Inner page header with title & breadcrumbs.
 *
 * @param string $title    Title.
 * @param string $subtitle Subtitle.
 * @param string $image    Background image URL.
 */
function kingdom_page_header( $title = '', $subtitle = '', $image = '' ) {
	if ( ! $title ) {
		if ( is_singular() ) {
			$title = get_the_title();
		} elseif ( is_search() ) {
			/* translators: %s: query */
			$title = sprintf( __( 'Search results for “%s”', 'kingdom' ), get_search_query() );
		} elseif ( is_404() ) {
			$title = __( 'Page not found', 'kingdom' );
		} elseif ( is_home() ) {
			$title = get_the_title( get_option( 'page_for_posts' ) ) ? get_the_title( get_option( 'page_for_posts' ) ) : __( 'News', 'kingdom' );
		} else {
			$title = wp_strip_all_tags( get_the_archive_title() );
		}
	}
	if ( ! $subtitle && is_singular() && has_excerpt() && is_page() ) {
		$subtitle = get_the_excerpt();
	}
	if ( ! $subtitle && ( is_archive() ) ) {
		$subtitle = wp_strip_all_tags( get_the_archive_description() );
	}
	if ( ! $image && is_singular() && has_post_thumbnail() && is_page() ) {
		$image = get_the_post_thumbnail_url( null, 'kingdom-hero' );
	}
	if ( ! $image ) {
		$image = get_theme_mod( 'kingdom_page_header_image', '' );
	}
	?>
	<section class="k-page-header<?php echo $image ? ' has-image' : ''; ?>"<?php echo $image ? ' style="--k-img:url(' . esc_url( $image ) . ')"' : ''; ?>>
		<div class="k-container">
			<?php kingdom_breadcrumbs(); ?>
			<h1 class="k-page-header__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $subtitle ) : ?><p class="k-page-header__subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Breadcrumbs (plugin SEO breadcrumbs when available).
 */
function kingdom_breadcrumbs() {
	if ( is_front_page() || ! get_theme_mod( 'kingdom_breadcrumbs', true ) ) {
		return;
	}
	if ( function_exists( 'kcs_breadcrumbs' ) ) {
		kcs_breadcrumbs();
		return;
	}
	echo '<nav class="kcs-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'kingdom' ) . '"><ol><li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'kingdom' ) . '</a></li><li><span aria-current="page">' . esc_html( wp_strip_all_tags( is_singular() ? get_the_title() : get_the_archive_title() ) ) . '</span></li></ol></nav>';
}

/**
 * Post meta line.
 */
function kingdom_posted_on() {
	echo '<div class="k-meta"><time datetime="' . esc_attr( get_the_date( DATE_ATOM ) ) . '">' . esc_html( get_the_date() ) . '</time>';
	echo '<span>' . esc_html( get_the_author() ) . '</span>';
	$minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) );
	/* translators: %d: minutes */
	echo '<span>' . esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'kingdom' ), $minutes ) ) . '</span></div>';
}

/**
 * YouTube ID from a URL.
 *
 * @param string $url URL.
 * @return string
 */
function kingdom_youtube_id( $url ) {
	if ( class_exists( 'KCS_Post_Types' ) ) {
		return KCS_Post_Types::youtube_id( $url );
	}
	return preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|live/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ? $m[1] : '';
}

/**
 * Sermon thumbnail URL (featured image or YouTube still).
 *
 * @param int $post_id Sermon.
 * @return string
 */
function kingdom_sermon_thumb( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, 'kingdom-card' );
	}
	$yt = kingdom_youtube_id( get_post_meta( $post_id, '_kcs_video_url', true ) );
	return $yt ? 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' : '';
}

/**
 * Event date badge.
 *
 * @param int $post_id Event.
 */
function kingdom_event_badge( $post_id = 0 ) {
	$start = get_post_meta( $post_id ? $post_id : get_the_ID(), '_kcs_start', true );
	if ( ! $start ) {
		return;
	}
	$ts = strtotime( $start );
	echo '<span class="k-date-badge"><b>' . esc_html( date_i18n( 'd', $ts ) ) . '</b><small>' . esc_html( date_i18n( 'M', $ts ) ) . '</small></span>';
}

/**
 * Event date string.
 *
 * @param int $post_id Event.
 * @return string
 */
function kingdom_event_date( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( class_exists( 'KCS_Post_Types' ) ) {
		return KCS_Post_Types::format_event_date( $post_id );
	}
	$start = get_post_meta( $post_id, '_kcs_start', true );
	return $start ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) ) : '';
}

/**
 * Pagination.
 */
function kingdom_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => '←',
			'next_text' => '→',
			'class'     => 'k-pagination',
		)
	);
}

/**
 * Share links for the current post.
 */
function kingdom_share_links() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	$links = array(
		'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
		'x'        => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
		'whatsapp' => 'https://wa.me/?text=' . $title . '%20' . $url,
	);
	echo '<div class="k-share"><span>' . esc_html__( 'Share', 'kingdom' ) . '</span>';
	foreach ( $links as $net => $href ) {
		echo '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( ucfirst( $net ) ) . '">' . kingdom_icon( $net ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '<button type="button" class="k-share__copy" data-kcs-share aria-label="' . esc_attr__( 'Copy link', 'kingdom' ) . '">🔗</button></div>';
}

/**
 * Fallback menu when none is assigned.
 */
function kingdom_fallback_menu() {
	echo '<ul class="k-menu">';
	wp_list_pages( array( 'title_li' => '', 'depth' => 2 ) );
	echo '</ul>';
}

/**
 * Does the current page's content already contain a shortcode?
 *
 * Plugin-created pages (Give, Partnership, Watch Live…) already hold the
 * shortcode, so templates use this to avoid rendering a feature twice.
 *
 * @param string $tag Shortcode tag.
 * @return bool
 */
function kingdom_content_has( $tag ) {
	$post = get_post();
	return $post && has_shortcode( $post->post_content, $tag );
}

/**
 * Render a feature shortcode unless the page content already includes it.
 *
 * @param string $tag       Shortcode tag.
 * @param string $shortcode Full shortcode.
 */
function kingdom_feature( $tag, $shortcode ) {
	if ( ! kingdom_content_has( $tag ) ) {
		echo kingdom_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
