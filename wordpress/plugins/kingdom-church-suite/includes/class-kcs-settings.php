<?php
/**
 * Settings schema, defaults and sanitization.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class KCS_Settings {

	/**
	 * Tabs (key => label).
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'general'    => __( 'General', 'kingdom-church-suite' ),
			'payments'   => __( 'Payment Gateways', 'kingdom-church-suite' ),
			'email'      => __( 'Email & Notifications', 'kingdom-church-suite' ),
			'templates'  => __( 'Email Templates', 'kingdom-church-suite' ),
			'livestream' => __( 'Live Streaming', 'kingdom-church-suite' ),
			'seo'        => __( 'SEO', 'kingdom-church-suite' ),
			'api'        => __( 'REST API', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Field schema grouped by tab, then section.
	 *
	 * Each field: key => [label, type, default, options?, desc?].
	 *
	 * @return array
	 */
	public static function schema() {
		$pages = array( 0 => __( '— Select page —', 'kingdom-church-suite' ) );
		if ( did_action( 'init' ) ) {
			foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $p ) {
				$pages[ $p->ID ] = $p->post_title;
			}
		}

		$schema = array(
			'general'    => array(
				__( 'Church details', 'kingdom-church-suite' ) => array(
					'church_name'    => array( 'label' => __( 'Church name', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_email'   => array( 'label' => __( 'Church email', 'kingdom-church-suite' ), 'type' => 'email' ),
					'church_phone'   => array( 'label' => __( 'Phone', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_address' => array( 'label' => __( 'Street address', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_city'    => array( 'label' => __( 'City', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_region'  => array( 'label' => __( 'State / Region', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_postcode' => array( 'label' => __( 'Postal code', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_country' => array( 'label' => __( 'Country code (e.g. NG, US, GB)', 'kingdom-church-suite' ), 'type' => 'text' ),
					'church_logo'    => array( 'label' => __( 'Logo URL', 'kingdom-church-suite' ), 'type' => 'url' ),
				),
				__( 'Service schedule', 'kingdom-church-suite' ) => array(
					'service_schedule' => array(
						'label'   => __( 'Weekly services', 'kingdom-church-suite' ),
						'type'    => 'textarea',
						'default' => "Sunday|08:00|First Service\nSunday|10:30|Second Service\nWednesday|18:00|Midweek Bible Study\nFriday|21:00|Prayer Night",
						'desc'    => __( 'One per line: Day|HH:MM (24h)|Service name. Used by the countdown, live badge and Church schema.', 'kingdom-church-suite' ),
					),
				),
				__( 'Giving & partnership', 'kingdom-church-suite' ) => array(
					'currency'            => array( 'label' => __( 'Default currency', 'kingdom-church-suite' ), 'type' => 'select', 'options' => kcs_currencies(), 'default' => 'USD' ),
					'allowed_currencies'  => array( 'label' => __( 'Currencies donors can choose', 'kingdom-church-suite' ), 'type' => 'text', 'default' => 'USD,NGN,GBP,EUR', 'desc' => __( 'Comma separated codes.', 'kingdom-church-suite' ) ),
					'min_amount'          => array( 'label' => __( 'Minimum amount', 'kingdom-church-suite' ), 'type' => 'number', 'default' => '1' ),
					'giving_purposes'     => array( 'label' => __( 'Giving purposes', 'kingdom-church-suite' ), 'type' => 'textarea', 'default' => "Tithe\nOffering\nPartnership\nBuilding Project\nMissions\nWelfare", 'desc' => __( 'One per line.', 'kingdom-church-suite' ) ),
					'partner_auto_activate' => array( 'label' => __( 'Activate partners after first successful payment only', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'reminder_days'       => array( 'label' => __( 'Send payment reminder N days before due date', 'kingdom-church-suite' ), 'type' => 'number', 'default' => '3' ),
				),
				__( 'Pages', 'kingdom-church-suite' ) => array(
					'page_partnership'       => array( 'label' => __( 'Partnership page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_partner_login'     => array( 'label' => __( 'Partner login page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_partner_register'  => array( 'label' => __( 'Partner registration page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_partner_dashboard' => array( 'label' => __( 'Partner dashboard page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_give'              => array( 'label' => __( 'Give page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_thank_you'         => array( 'label' => __( 'Payment result page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_watch_live'        => array( 'label' => __( 'Watch live page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
					'page_prayer'            => array( 'label' => __( 'Prayer request page', 'kingdom-church-suite' ), 'type' => 'select', 'options' => $pages ),
				),
			),
			'payments'   => array(),
			'email'      => array(
				__( 'Sender', 'kingdom-church-suite' )        => array(
					'from_name'          => array( 'label' => __( 'From name', 'kingdom-church-suite' ), 'type' => 'text' ),
					'from_email'         => array( 'label' => __( 'From email', 'kingdom-church-suite' ), 'type' => 'email' ),
					'admin_notify_email' => array( 'label' => __( 'Admin notification email(s)', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'Comma separated.', 'kingdom-church-suite' ) ),
					'email_color'        => array( 'label' => __( 'Brand colour', 'kingdom-church-suite' ), 'type' => 'color', 'default' => '#5b2a86' ),
					'email_footer'       => array( 'label' => __( 'Email footer text', 'kingdom-church-suite' ), 'type' => 'textarea' ),
				),
				__( 'Notifications', 'kingdom-church-suite' ) => array(
					'notify_admin_partner' => array( 'label' => __( 'Email admin when a partner registers', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'notify_admin_payment' => array( 'label' => __( 'Email admin on every successful payment', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'notify_admin_prayer'  => array( 'label' => __( 'Email admin on new prayer requests', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'send_receipts'        => array( 'label' => __( 'Email receipts to givers', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'send_reminders'       => array( 'label' => __( 'Email partners before their due date', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
				),
				__( 'Newsletter', 'kingdom-church-suite' )    => array(
					'double_optin'  => array( 'label' => __( 'Require email confirmation (double opt-in)', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'batch_size'    => array( 'label' => __( 'Emails sent per batch (every 5 min)', 'kingdom-church-suite' ), 'type' => 'number', 'default' => '50' ),
					'auto_new_sermon' => array( 'label' => __( 'Auto-email subscribers when a new sermon is published', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '0' ),
				),
			),
			'templates'  => array(),
			'livestream' => array(
				__( 'YouTube', 'kingdom-church-suite' ) => array(
					'yt_channel_id'     => array( 'label' => __( 'YouTube channel ID', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'Starts with "UC…". Found under YouTube Studio → Settings → Channel → Advanced.', 'kingdom-church-suite' ) ),
					'yt_api_key'        => array( 'label' => __( 'YouTube Data API v3 key', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'Optional. Enables accurate live detection, titles and upcoming streams. Without it the channel live embed is used.', 'kingdom-church-suite' ) ),
					'yt_manual_video'   => array( 'label' => __( 'Force a video ID / URL', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'Override detection (e.g. for a specific stream). Leave empty for automatic.', 'kingdom-church-suite' ) ),
					'yt_fallback_video' => array( 'label' => __( 'Fallback video (shown when offline)', 'kingdom-church-suite' ), 'type' => 'text' ),
					'yt_cache_minutes'  => array( 'label' => __( 'Cache live status (minutes)', 'kingdom-church-suite' ), 'type' => 'number', 'default' => '3', 'desc' => __( 'Each check costs 100 API quota units; keep this ≥ 2.', 'kingdom-church-suite' ) ),
					'yt_only_service_times' => array( 'label' => __( 'Only query the API around service times', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1', 'desc' => __( 'Saves quota: checks from 30 min before to 3 h after each scheduled service.', 'kingdom-church-suite' ) ),
				),
				__( 'Other platforms', 'kingdom-church-suite' ) => array(
					'facebook_live_url' => array( 'label' => __( 'Facebook page URL (for Facebook Live embed)', 'kingdom-church-suite' ), 'type' => 'url' ),
					'custom_stream_embed' => array( 'label' => __( 'Custom player embed code (Vimeo, Boxcast, etc.)', 'kingdom-church-suite' ), 'type' => 'code' ),
				),
			),
			'seo'        => array(
				__( 'General SEO', 'kingdom-church-suite' ) => array(
					'seo_enabled'      => array( 'label' => __( 'Enable built-in SEO', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1', 'desc' => __( 'Automatically disabled when Yoast SEO, Rank Math or SEOPress is active.', 'kingdom-church-suite' ) ),
					'seo_separator'    => array( 'label' => __( 'Title separator', 'kingdom-church-suite' ), 'type' => 'select', 'options' => array( '|' => '|', '-' => '-', '–' => '–', '•' => '•', '·' => '·', '»' => '»' ), 'default' => '|' ),
					'seo_home_title'   => array( 'label' => __( 'Homepage title', 'kingdom-church-suite' ), 'type' => 'text' ),
					'seo_home_desc'    => array( 'label' => __( 'Homepage meta description', 'kingdom-church-suite' ), 'type' => 'textarea' ),
					'seo_default_image' => array( 'label' => __( 'Default social share image URL', 'kingdom-church-suite' ), 'type' => 'url' ),
					'seo_noindex_archives' => array( 'label' => __( 'Noindex date & author archives', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
					'seo_noindex_search'   => array( 'label' => __( 'Noindex search results', 'kingdom-church-suite' ), 'type' => 'checkbox', 'default' => '1' ),
				),
				__( 'Social & verification', 'kingdom-church-suite' ) => array(
					'social_facebook'  => array( 'label' => 'Facebook URL', 'type' => 'url' ),
					'social_instagram' => array( 'label' => 'Instagram URL', 'type' => 'url' ),
					'social_youtube'   => array( 'label' => 'YouTube URL', 'type' => 'url' ),
					'social_x'         => array( 'label' => 'X (Twitter) URL', 'type' => 'url' ),
					'social_tiktok'    => array( 'label' => 'TikTok URL', 'type' => 'url' ),
					'twitter_handle'   => array( 'label' => __( 'X handle (without @)', 'kingdom-church-suite' ), 'type' => 'text' ),
					'fb_app_id'        => array( 'label' => 'Facebook App ID', 'type' => 'text' ),
					'verify_google'    => array( 'label' => __( 'Google Search Console verification code', 'kingdom-church-suite' ), 'type' => 'text' ),
					'verify_bing'      => array( 'label' => __( 'Bing verification code', 'kingdom-church-suite' ), 'type' => 'text' ),
				),
				__( 'Redirects', 'kingdom-church-suite' ) => array(
					'seo_redirects' => array( 'label' => __( '301 redirects', 'kingdom-church-suite' ), 'type' => 'textarea', 'desc' => __( 'One per line: /old-path => https://example.com/new-path', 'kingdom-church-suite' ) ),
				),
				__( 'Local SEO', 'kingdom-church-suite' ) => array(
					'geo_lat'   => array( 'label' => __( 'Latitude', 'kingdom-church-suite' ), 'type' => 'text' ),
					'geo_lng'   => array( 'label' => __( 'Longitude', 'kingdom-church-suite' ), 'type' => 'text' ),
					'denomination' => array( 'label' => __( 'Denomination (optional)', 'kingdom-church-suite' ), 'type' => 'text' ),
				),
			),
			'api'        => array(
				__( 'Authentication', 'kingdom-church-suite' ) => array(
					'api_key'      => array( 'label' => __( 'API key', 'kingdom-church-suite' ), 'type' => 'password', 'desc' => __( 'Send as the X-KCS-API-Key header to call admin endpoints from external systems (CRM, mobile app, Zapier). Logged-in admins can also use cookies + nonce or Application Passwords.', 'kingdom-church-suite' ) ),
					'api_cors'     => array( 'label' => __( 'Allowed CORS origins', 'kingdom-church-suite' ), 'type' => 'text', 'desc' => __( 'Comma separated, e.g. https://app.example.com', 'kingdom-church-suite' ) ),
					'webhook_out_url' => array( 'label' => __( 'Outgoing webhook URL', 'kingdom-church-suite' ), 'type' => 'url', 'desc' => __( 'We POST JSON here on partner.created, payment.completed, subscriber.created, prayer.created (signed with X-KCS-Signature = HMAC-SHA256 of the body using the API key).', 'kingdom-church-suite' ) ),
				),
			),
		);

		foreach ( KCS_Payments::gateways() as $gateway ) {
			$schema['payments'][ $gateway->title ] = $gateway->settings_fields();
		}
		foreach ( KCS_Email::templates() as $key => $tpl ) {
			$schema['templates'][ $tpl['label'] ] = array(
				'tpl_' . $key . '_subject' => array( 'label' => __( 'Subject', 'kingdom-church-suite' ), 'type' => 'text', 'default' => $tpl['subject'] ),
				'tpl_' . $key . '_body'    => array( 'label' => __( 'Body', 'kingdom-church-suite' ), 'type' => 'editor', 'default' => $tpl['body'], 'desc' => __( 'Placeholders: ', 'kingdom-church-suite' ) . implode( ' ', $tpl['vars'] ) ),
			);
		}

		return apply_filters( 'kcs_settings_schema', $schema );
	}

	/**
	 * Flattened defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		static $defaults = null;
		static $building = false;
		if ( null !== $defaults ) {
			return $defaults;
		}
		if ( $building ) {
			return array(); // Guard against recursion while the schema is being built.
		}
		$building = true;
		$schema   = self::schema();
		$defaults = array();
		foreach ( $schema as $sections ) {
			foreach ( $sections as $fields ) {
				foreach ( $fields as $key => $field ) {
					$defaults[ $key ] = isset( $field['default'] ) ? $field['default'] : '';
				}
			}
		}
		return $defaults;
	}

	/**
	 * Sanitize values posted for a tab and merge into stored settings.
	 *
	 * @param string $tab   Tab key.
	 * @param array  $input Raw input.
	 */
	public static function save_tab( $tab, $input ) {
		$schema   = self::schema();
		$settings = get_option( 'kcs_settings', array() );
		if ( empty( $schema[ $tab ] ) ) {
			return;
		}
		foreach ( $schema[ $tab ] as $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( 'info' === $field['type'] ) {
					continue;
				}
				$raw              = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
				$settings[ $key ] = self::sanitize_field( $raw, $field );
			}
		}
		update_option( 'kcs_settings', $settings );
	}

	/**
	 * Sanitize one field.
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field definition.
	 * @return mixed
	 */
	public static function sanitize_field( $value, $field ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				return $value ? '1' : '0';
			case 'email':
				return sanitize_email( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'number':
				return '' === $value ? '' : (string) floatval( $value );
			case 'color':
				return sanitize_hex_color( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'editor':
				return wp_kses_post( $value );
			case 'code':
				return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
			case 'select':
				return array_key_exists( $value, $field['options'] ) ? (string) $value : '';
			default:
				return sanitize_text_field( $value );
		}
	}
}
