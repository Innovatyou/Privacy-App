<?php
/**
 * Creates a ready-to-use church site: WordPress site, theme, church plugin, starter pages,
 * owner account and free-trial subscription.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provisioner.
 */
class KS_Provisioner {

	const KCS = 'kingdom-church-suite/kingdom-church-suite.php';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'finish_setup' ), 30 );
		add_action( 'wp_delete_site', array( __CLASS__, 'on_delete_site' ) );
	}

	/**
	 * Check a requested subdomain.
	 *
	 * @param string $name Requested name.
	 * @return string|WP_Error Full domain.
	 */
	public static function validate_subdomain( $name ) {
		$name = strtolower( trim( (string) $name ) );
		if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/', $name ) ) {
			return new WP_Error( 'ks_subdomain', __( 'Use 3–40 letters, numbers or hyphens (not at the start or end).', 'kingdom-saas' ) );
		}
		$reserved = array_map( 'trim', explode( ',', strtolower( ks_get( 'reserved' ) ) ) );
		if ( in_array( $name, $reserved, true ) ) {
			return new WP_Error( 'ks_subdomain', __( 'That address is reserved. Please choose another.', 'kingdom-saas' ) );
		}
		$domain = $name . '.' . ks_get( 'base_domain' );
		if ( domain_exists( $domain, '/', get_current_network_id() ) ) {
			return new WP_Error( 'ks_subdomain', __( 'That address is already taken.', 'kingdom-saas' ) );
		}
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ks_table( 'subscriptions' ) . ' WHERE subdomain = %s', $domain ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new WP_Error( 'ks_subdomain', __( 'That address is already taken.', 'kingdom-saas' ) );
		}
		return $domain;
	}

	/**
	 * Suggest a subdomain from a church name.
	 *
	 * @param string $church Church name.
	 * @return string
	 */
	public static function suggest( $church ) {
		$base = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $church ) ) ), '-' );
		$base = substr( $base ? $base : 'church', 0, 34 );
		$try  = $base;
		for ( $i = 2; $i < 50 && is_wp_error( self::validate_subdomain( $try ) ); $i++ ) {
			$try = $base . '-' . $i;
		}
		return $try;
	}

	/**
	 * Create a church.
	 *
	 * @param array $a church_name, subdomain, name, email, password, plan, interval, region, palette, status, trial_days.
	 * @return array|WP_Error array( blog_id, user_id, login_url ).
	 */
	public static function create_church( $a ) {
		$church = sanitize_text_field( $a['church_name'] ?? '' );
		$email  = sanitize_email( $a['email'] ?? '' );
		$plan   = sanitize_key( $a['plan'] ?? 'growth' );
		if ( strlen( $church ) < 2 ) {
			return new WP_Error( 'ks_church', __( 'Please enter your church name.', 'kingdom-saas' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'ks_email', __( 'Please enter a valid email address.', 'kingdom-saas' ) );
		}
		if ( ! KS_Plans::get( $plan ) ) {
			return new WP_Error( 'ks_plan', __( 'Please choose a plan.', 'kingdom-saas' ) );
		}
		$domain = self::validate_subdomain( $a['subdomain'] ?? '' );
		if ( is_wp_error( $domain ) ) {
			return $domain;
		}

		// Owner account (network-wide).
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$may_reuse = ! empty( $a['by_admin'] ) || get_current_user_id() === $user->ID || ( ! empty( $a['password'] ) && wp_check_password( $a['password'], $user->user_pass, $user->ID ) );
			if ( ! $may_reuse ) {
				return new WP_Error( 'ks_email_taken', __( 'This email already has an account. Enter that account’s password to add another church to it.', 'kingdom-saas' ) );
			}
			$user_id = $user->ID;
		} else {
			$password = (string) ( $a['password'] ?? '' );
			if ( empty( $a['by_admin'] ) && strlen( $password ) < 8 ) {
				return new WP_Error( 'ks_password', __( 'Please choose a password of at least 8 characters.', 'kingdom-saas' ) );
			}
			$login = sanitize_user( current( explode( '@', $email ) ), true );
			$login = $login ? $login : 'church';
			$base  = $login;
			for ( $i = 2; username_exists( $login ); $i++ ) {
				$login = $base . $i;
			}
			$user_id = wpmu_create_user( $login, $password ? $password : wp_generate_password( 16 ), $email );
			if ( ! $user_id ) {
				return new WP_Error( 'ks_user', __( 'We could not create your account. Please try again.', 'kingdom-saas' ) );
			}
			$name = sanitize_text_field( $a['name'] ?? '' );
			if ( $name ) {
				$parts = explode( ' ', $name, 2 );
				wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '' ) );
			}
		}

		// The site.
		// wpmu_create_blog() switches WordPress into "installing" mode and leaves it on, which makes the rest of
		// this request keep transients (such as the login link) in memory only. Put it back afterwards.
		$installing = wp_installing();
		$blog_id    = wpmu_create_blog( $domain, '/', $church, $user_id, array( 'public' => 1 ), get_current_network_id() );
		wp_installing( $installing );
		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}

		switch_to_blog( $blog_id );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'blogdescription', __( 'Welcome home', 'kingdom-saas' ) );
		update_option( 'admin_email', $email );
		if ( wp_get_theme( 'kingdom' )->exists() ) {
			switch_theme( 'kingdom' );
		}
		if ( file_exists( WP_PLUGIN_DIR . '/' . self::KCS ) ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( is_plugin_active_for_network( self::KCS ) && class_exists( 'KCS_Installer' ) ) {
				KCS_Installer::activate();
			} else {
				activate_plugin( self::KCS );
			}
			if ( function_exists( 'kcs_update_option' ) ) {
				kcs_update_option( 'church_name', $church );
				kcs_update_option( 'church_email', $email );
				kcs_update_option( 'local_currency', ks_get( 'local_currency' ) ? ks_get( 'local_currency' ) : 'none' );
				kcs_update_option( 'local_label', ks_get( 'local_label' ) );
			}
		}
		update_option( 'ks_palette', sanitize_key( $a['palette'] ?? '' ) );
		update_option( 'ks_setup_pending', 1 );
		restore_current_blog();

		// Subscription (free trial).
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$days  = isset( $a['trial_days'] ) ? (int) $a['trial_days'] : (int) ks_get( 'trial_days' );
		$trial = gmdate( 'Y-m-d H:i:s', time() + max( 0, $days ) * DAY_IN_SECONDS );
		$wpdb->insert(
			ks_table( 'subscriptions' ),
			array(
				'blog_id'          => $blog_id,
				'church_name'      => $church,
				'subdomain'        => $domain,
				'owner_id'         => $user_id,
				'owner_email'      => $email,
				'plan'             => $plan,
				'billing_interval' => 'year' === ( $a['interval'] ?? '' ) ? 'year' : 'month',
				'region'           => 'local' === ( $a['region'] ?? '' ) ? 'local' : 'international',
				'status'           => in_array( $a['status'] ?? '', array( 'active', 'trialing' ), true ) ? $a['status'] : 'trialing',
				'trial_ends_at'    => $trial,
				'current_period_end' => 'active' === ( $a['status'] ?? '' ) ? gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . ( 'year' === ( $a['interval'] ?? '' ) ? 'year' : 'month' ) ) ) : null,
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);

		// Build the pages now (on the new site itself, so its theme is loaded).
		wp_remote_get( add_query_arg( 'ks_setup', 1, trailingslashit( KS_Tenants::url( $blog_id ) ) ), array( 'timeout' => 30, 'sslverify' => false ) );

		$sub = KS_Tenants::get( $blog_id );
		KS_Email::owner(
			$sub,
			/* translators: %s: church */
			sprintf( __( 'Your new website for %s is ready', 'kingdom-saas' ), $church ),
			'<p>' . esc_html__( 'Your church website, partner portal and Church Admin dashboard are live.', 'kingdom-saas' ) . '</p>'
			. '<p><strong>' . esc_html__( 'Website:', 'kingdom-saas' ) . '</strong> ' . esc_html( KS_Tenants::url( $blog_id ) ) . '<br><strong>' . esc_html__( 'Log in:', 'kingdom-saas' ) . '</strong> ' . esc_html( get_site_url( $blog_id, 'wp-login.php' ) ) . '<br><strong>' . esc_html__( 'Email:', 'kingdom-saas' ) . '</strong> ' . esc_html( $email ) . '</p>'
			/* translators: 1: days, 2: date */
			. ( 'trialing' === $sub->status ? '<p>' . esc_html( sprintf( __( 'Your free trial lasts %1$d days, until %2$s. Choose a plan any time from Church Admin → Subscription.', 'kingdom-saas' ), $days, date_i18n( get_option( 'date_format' ), strtotime( $trial . ' UTC' ) ) ) ) . '</p>' : '' ),
			array( __( 'Open Church Admin', 'kingdom-saas' ), KS_Tenants::admin_url( $blog_id ) )
		);
		do_action( 'ks_church_created', $blog_id, $sub );

		return array(
			'blog_id'   => $blog_id,
			'user_id'   => $user_id,
			'login_url' => KS_Login::link( $user_id, $blog_id ),
		);
	}

	/**
	 * Finish a new site on its first request: starter pages, menus, colours.
	 */
	public static function finish_setup() {
		if ( is_main_site() || ! get_option( 'ks_setup_pending' ) ) {
			return;
		}
		delete_option( 'ks_setup_pending' );
		if ( function_exists( 'kingdom_build_starter_site' ) ) {
			kingdom_build_starter_site( 'template-home-classic.php' );
		}
		$palette = get_option( 'ks_palette' );
		if ( $palette && function_exists( 'kingdom_palettes' ) && isset( kingdom_palettes()[ $palette ] ) ) {
			set_theme_mod( 'kingdom_palette', $palette );
		}
		flush_rewrite_rules( false );
		if ( ! empty( $_GET['ks_setup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_die( 'ok', '', array( 'response' => 200 ) );
		}
	}

	/**
	 * Clean up when a site is deleted.
	 *
	 * @param WP_Site $site Site.
	 */
	public static function on_delete_site( $site ) {
		global $wpdb;
		foreach ( array( 'subscriptions', 'domains' ) as $t ) {
			$wpdb->delete( ks_table( $t ), array( 'blog_id' => (int) $site->blog_id ) );
		}
	}
}
