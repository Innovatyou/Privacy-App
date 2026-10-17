<?php
/**
 * Customizer: colours, typography, header, hero, homepage sections, contact, social, footer.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register Customizer settings.
 *
 * @param WP_Customize_Manager $wp Manager.
 */
function kingdom_customize_register( $wp ) {
	$wp->add_panel( 'kingdom', array( 'title' => __( 'Kingdom Church Theme', 'kingdom' ), 'priority' => 20 ) );

	$add = function ( $section, $id, $label, $type = 'text', $default = '', $choices = array(), $desc = '' ) use ( $wp ) {
		$sanitize = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'url'      => 'esc_url_raw',
			'email'    => 'sanitize_email',
			'checkbox' => 'kingdom_sanitize_checkbox',
			'select'   => 'sanitize_text_field',
			'radio'    => 'sanitize_key',
			'color'    => 'sanitize_hex_color',
			'image'    => 'esc_url_raw',
			'number'   => 'absint',
		);
		$wp->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => $sanitize[ $type ],
				'transport'         => 'refresh',
			)
		);
		if ( 'color' === $type ) {
			$wp->add_control( new WP_Customize_Color_Control( $wp, $id, array( 'label' => $label, 'section' => $section ) ) );
		} elseif ( 'image' === $type ) {
			$wp->add_control( new WP_Customize_Image_Control( $wp, $id, array( 'label' => $label, 'section' => $section, 'description' => $desc ) ) );
		} else {
			$wp->add_control(
				$id,
				array(
					'label'       => $label,
					'section'     => $section,
					'type'        => $type,
					'choices'     => $choices,
					'description' => $desc,
				)
			);
		}
	};

	$sections = array(
		'kingdom_colors'   => __( 'Colours & Typography', 'kingdom' ),
		'kingdom_header'   => __( 'Header', 'kingdom' ),
		'kingdom_hero'     => __( 'Homepage Hero', 'kingdom' ),
		'kingdom_home'     => __( 'Homepage Sections', 'kingdom' ),
		'kingdom_welcome'  => __( 'Welcome / Pastor', 'kingdom' ),
		'kingdom_services' => __( 'Service Times', 'kingdom' ),
		'kingdom_stats'    => __( 'Stats Counter', 'kingdom' ),
		'kingdom_contact'  => __( 'Contact & Map', 'kingdom' ),
		'kingdom_social'   => __( 'Social Profiles', 'kingdom' ),
		'kingdom_footer'   => __( 'Footer', 'kingdom' ),
	);
	$p = 10;
	foreach ( $sections as $id => $title ) {
		$wp->add_section( $id, array( 'title' => $title, 'panel' => 'kingdom', 'priority' => $p += 10 ) );
	}

	// Colours & typography.
	$fonts = array_combine( array_keys( kingdom_fonts() ), array_keys( kingdom_fonts() ) );
	$add( 'kingdom_colors', 'kingdom_color_primary', __( 'Primary colour', 'kingdom' ), 'color', '#5b2a86' );
	$add( 'kingdom_colors', 'kingdom_color_accent', __( 'Accent colour', 'kingdom' ), 'color', '#f4a62a' );
	$add( 'kingdom_colors', 'kingdom_color_dark', __( 'Dark (footer) colour', 'kingdom' ), 'color', '#16121c' );
	$add( 'kingdom_colors', 'kingdom_color_scheme', __( 'Default colour scheme', 'kingdom' ), 'select', 'light', array( 'light' => __( 'Light', 'kingdom' ), 'dark' => __( 'Dark', 'kingdom' ), 'auto' => __( 'Follow device', 'kingdom' ) ) );
	$add( 'kingdom_colors', 'kingdom_dark_toggle', __( 'Show dark-mode toggle', 'kingdom' ), 'checkbox', true );
	$add( 'kingdom_colors', 'kingdom_font_heading', __( 'Heading font', 'kingdom' ), 'select', 'Playfair Display', $fonts );
	$add( 'kingdom_colors', 'kingdom_font_body', __( 'Body font', 'kingdom' ), 'select', 'Inter', $fonts );
	$add( 'kingdom_colors', 'kingdom_radius', __( 'Corner style', 'kingdom' ), 'select', 'rounded', array( 'sharp' => __( 'Sharp', 'kingdom' ), 'rounded' => __( 'Rounded', 'kingdom' ), 'pill' => __( 'Extra round', 'kingdom' ) ) );
	$add( 'kingdom_colors', 'kingdom_container', __( 'Container width (px)', 'kingdom' ), 'number', 1200 );

	// Header.
	$add( 'kingdom_header', 'kingdom_header_style', __( 'Header style', 'kingdom' ), 'select', 'transparent', array( 'classic' => __( 'Classic (logo left)', 'kingdom' ), 'centered' => __( 'Centered logo', 'kingdom' ), 'transparent' => __( 'Transparent over hero', 'kingdom' ) ) );
	$add( 'kingdom_header', 'kingdom_header_sticky', __( 'Sticky header', 'kingdom' ), 'checkbox', true );
	$add( 'kingdom_header', 'kingdom_topbar', __( 'Show top bar (service times, contact)', 'kingdom' ), 'checkbox', true );
	$add( 'kingdom_header', 'kingdom_live_badge', __( 'Show live badge', 'kingdom' ), 'checkbox', true );
	$add( 'kingdom_header', 'kingdom_header_cta_text', __( 'Header button text', 'kingdom' ), 'text', __( 'Give', 'kingdom' ) );
	$add( 'kingdom_header', 'kingdom_header_cta_url', __( 'Header button URL', 'kingdom' ), 'url', '' );
	$add( 'kingdom_header', 'kingdom_breadcrumbs', __( 'Show breadcrumbs', 'kingdom' ), 'checkbox', true );
	$add( 'kingdom_header', 'kingdom_page_header_image', __( 'Default page header image', 'kingdom' ), 'image', '' );

	// Hero.
	$add( 'kingdom_hero', 'kingdom_hero_eyebrow', __( 'Small label', 'kingdom' ), 'text', __( 'Welcome home', 'kingdom' ) );
	$add( 'kingdom_hero', 'kingdom_hero_title', __( 'Title', 'kingdom' ), 'text', __( 'A place to belong, believe and become', 'kingdom' ) );
	$add( 'kingdom_hero', 'kingdom_hero_text', __( 'Text', 'kingdom' ), 'textarea', __( 'Join us this Sunday — in person or online — and experience the love of Jesus with a family that cares.', 'kingdom' ) );
	$add( 'kingdom_hero', 'kingdom_hero_image', __( 'Background image', 'kingdom' ), 'image', '' );
	$add( 'kingdom_hero', 'kingdom_hero_video', __( 'Background video (MP4 URL)', 'kingdom' ), 'url', '', array(), __( 'Used by "Modern Video" layout. Keep it short and muted (under 10 MB).', 'kingdom' ) );
	$add( 'kingdom_hero', 'kingdom_hero_youtube', __( 'Watch video (YouTube URL for play button)', 'kingdom' ), 'url', '' );
	$add( 'kingdom_hero', 'kingdom_hero_btn1_text', __( 'Primary button text', 'kingdom' ), 'text', __( 'Plan your visit', 'kingdom' ) );
	$add( 'kingdom_hero', 'kingdom_hero_btn1_url', __( 'Primary button URL', 'kingdom' ), 'url', '' );
	$add( 'kingdom_hero', 'kingdom_hero_btn2_text', __( 'Secondary button text', 'kingdom' ), 'text', __( 'Watch online', 'kingdom' ) );
	$add( 'kingdom_hero', 'kingdom_hero_btn2_url', __( 'Secondary button URL', 'kingdom' ), 'url', '' );
	$add( 'kingdom_hero', 'kingdom_hero_overlay', __( 'Overlay darkness (0–90)', 'kingdom' ), 'number', 55 );

	// Homepage sections.
	$add( 'kingdom_home', 'kingdom_home_sections', __( 'Section order', 'kingdom' ), 'textarea', 'hero, service-times, welcome, live, sermons, ministries, events, stats, partnership, testimonies, give, newsletter', array(), __( 'Used when the front page is your latest posts or a page without a template. Available: ', 'kingdom' ) . implode( ', ', array_keys( kingdom_available_sections() ) ) );
	$add( 'kingdom_home', 'kingdom_sermons_title', __( 'Sermons title', 'kingdom' ), 'text', __( 'Latest messages', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_events_title', __( 'Events title', 'kingdom' ), 'text', __( 'Upcoming events', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_ministries_title', __( 'Ministries title', 'kingdom' ), 'text', __( 'Find your place', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_partnership_title', __( 'Partnership title', 'kingdom' ), 'text', __( 'Partner with the vision', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_partnership_text', __( 'Partnership text', 'kingdom' ), 'textarea', __( 'Your monthly partnership helps us reach the lost, care for families and raise disciples across the nations.', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_give_title', __( 'Giving title', 'kingdom' ), 'text', __( 'Give generously', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_give_text', __( 'Giving text', 'kingdom' ), 'textarea', __( 'Give your tithes and offerings securely online with card, bank transfer or mobile money.', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_cta_title', __( 'Call to action title', 'kingdom' ), 'text', __( 'New here? We saved you a seat.', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_cta_text', __( 'Call to action text', 'kingdom' ), 'textarea', __( 'Let us know you are coming and we will have someone ready to welcome you.', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_cta_btn_text', __( 'Call to action button', 'kingdom' ), 'text', __( 'Plan your visit', 'kingdom' ) );
	$add( 'kingdom_home', 'kingdom_cta_btn_url', __( 'Call to action URL', 'kingdom' ), 'url', '' );

	// Welcome.
	$add( 'kingdom_welcome', 'kingdom_welcome_eyebrow', __( 'Small label', 'kingdom' ), 'text', __( 'Welcome', 'kingdom' ) );
	$add( 'kingdom_welcome', 'kingdom_welcome_title', __( 'Title', 'kingdom' ), 'text', __( 'You are not here by accident', 'kingdom' ) );
	$add( 'kingdom_welcome', 'kingdom_welcome_text', __( 'Message', 'kingdom' ), 'textarea', __( "We are a family of believers passionate about Jesus, prayer and the Word. Whether you are exploring faith for the first time or looking for a church home, there is a place for you here.\n\nCome as you are — you will be loved, encouraged and equipped to live out your God-given purpose.", 'kingdom' ) );
	$add( 'kingdom_welcome', 'kingdom_welcome_image', __( 'Pastor photo', 'kingdom' ), 'image', '' );
	$add( 'kingdom_welcome', 'kingdom_pastor_name', __( 'Pastor name', 'kingdom' ), 'text', __( 'Pastor John & Mary Doe', 'kingdom' ) );
	$add( 'kingdom_welcome', 'kingdom_pastor_role', __( 'Pastor role', 'kingdom' ), 'text', __( 'Lead Pastors', 'kingdom' ) );
	$add( 'kingdom_welcome', 'kingdom_welcome_btn_text', __( 'Button text', 'kingdom' ), 'text', __( 'Our story', 'kingdom' ) );
	$add( 'kingdom_welcome', 'kingdom_welcome_btn_url', __( 'Button URL', 'kingdom' ), 'url', '' );

	// Services.
	$add( 'kingdom_services', 'kingdom_service_times', __( 'Service times (fallback)', 'kingdom' ), 'textarea', "Sunday|8:00 AM|First Service\nSunday|10:30 AM|Celebration Service\nWednesday|6:00 PM|Bible Study", array(), __( 'One per line: Day|Time|Name. When the Kingdom Church Suite plugin is active its service schedule is used instead.', 'kingdom' ) );

	// Stats.
	$add( 'kingdom_stats', 'kingdom_stats', __( 'Stats', 'kingdom' ), 'textarea', "25|Years of ministry\n3000|Members\n40|Ministries\n12|Nations reached", array(), __( 'One per line: number|label', 'kingdom' ) );
	$add( 'kingdom_stats', 'kingdom_stats_image', __( 'Background image', 'kingdom' ), 'image', '' );

	// Contact.
	$add( 'kingdom_contact', 'kingdom_address', __( 'Address', 'kingdom' ), 'textarea', '' );
	$add( 'kingdom_contact', 'kingdom_phone', __( 'Phone', 'kingdom' ), 'text', '' );
	$add( 'kingdom_contact', 'kingdom_email', __( 'Email', 'kingdom' ), 'email', '' );
	$add( 'kingdom_contact', 'kingdom_map', __( 'Google Maps embed URL', 'kingdom' ), 'url', '', array(), __( 'Google Maps → Share → Embed a map → copy only the src="…" URL.', 'kingdom' ) );
	$add( 'kingdom_contact', 'kingdom_contact_form', __( 'Contact form shortcode', 'kingdom' ), 'text', '', array(), __( 'e.g. a Contact Form 7, WPForms or Fluent Forms shortcode.', 'kingdom' ) );

	// Social.
	foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp (https://wa.me/number)' ) as $k => $l ) {
		$add( 'kingdom_social', 'kingdom_social_' . $k, $l, 'url', '' );
	}
	$add( 'kingdom_social', 'kingdom_whatsapp_float', __( 'Show floating WhatsApp button', 'kingdom' ), 'checkbox', false );

	// Footer.
	$add( 'kingdom_footer', 'kingdom_footer_about', __( 'About text', 'kingdom' ), 'textarea', __( 'Raising a generation of believers who love God, love people and transform their world.', 'kingdom' ) );
	$add( 'kingdom_footer', 'kingdom_footer_newsletter', __( 'Show newsletter in footer', 'kingdom' ), 'checkbox', true );
	$add( 'kingdom_footer', 'kingdom_copyright', __( 'Copyright text', 'kingdom' ), 'text', '' );
}
add_action( 'customize_register', 'kingdom_customize_register' );

/**
 * Checkbox sanitizer.
 *
 * @param mixed $v Value.
 * @return bool
 */
function kingdom_sanitize_checkbox( $v ) {
	return (bool) $v;
}

/**
 * Theme mod helper that falls back to a page URL by template.
 *
 * @param string $mod      Theme mod.
 * @param string $template Template file (page-templates/…).
 * @param string $fallback Fallback URL.
 * @return string
 */
function kingdom_url( $mod, $template = '', $fallback = '' ) {
	$url = get_theme_mod( $mod );
	if ( $url ) {
		return $url;
	}
	if ( $template ) {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => 1,
				'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'page-templates/' . $template, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
			)
		);
		if ( $pages ) {
			return get_permalink( $pages[0] );
		}
	}
	return $fallback ? $fallback : home_url( '/' );
}
