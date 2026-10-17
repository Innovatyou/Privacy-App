<?php
/**
 * Activation, database tables, roles and default content.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class KCS_Installer {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		$settings = (array) get_option( 'kcs_settings', array() );
		$settings = array_merge(
			array(
				'api_key'      => wp_generate_password( 40, false, false ),
				'church_name'  => get_bloginfo( 'name' ),
				'church_email' => get_option( 'admin_email' ),
			),
			array_filter( $settings, 'strlen' )
		);
		update_option( 'kcs_settings', $settings );

		self::create_tables();
		self::create_roles();
		self::create_pages();
		self::default_plans();
		KCS_Post_Types::register();
		flush_rewrite_rules();
		self::schedule_events();
		update_option( 'kcs_db_version', KCS_DB_VERSION );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'kcs_process_email_queue' );
		wp_clear_scheduled_hook( 'kcs_daily_tasks' );
		flush_rewrite_rules();
	}

	/**
	 * Upgrade the schema when the version changes.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'kcs_db_version' ) !== KCS_DB_VERSION ) {
			self::create_tables();
			self::create_roles();
			self::create_pages();
			update_option( 'kcs_db_version', KCS_DB_VERSION );
		}
		self::schedule_events();
	}

	/**
	 * Schedule cron events.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'kcs_process_email_queue' ) ) {
			wp_schedule_event( time() + 60, 'kcs_five_minutes', 'kcs_process_email_queue' );
		}
		if ( ! wp_next_scheduled( 'kcs_daily_tasks' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 08:00' ), 'daily', 'kcs_daily_tasks' );
		}
	}

	/**
	 * Create/upgrade custom tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = 'CREATE TABLE ' . kcs_table( 'plans' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			description text NULL,
			benefits text NULL,
			amount decimal(15,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'USD',
			frequency varchar(20) NOT NULL DEFAULT 'monthly',
			allow_custom_amount tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			is_featured tinyint(1) NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'partners' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			partner_code varchar(32) NOT NULL,
			plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL,
			phone varchar(50) NOT NULL DEFAULT '',
			country varchar(100) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			address text NULL,
			amount decimal(15,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'USD',
			frequency varchar(20) NOT NULL DEFAULT 'monthly',
			status varchar(20) NOT NULL DEFAULT 'pending',
			next_due_date date NULL,
			last_payment_at datetime NULL,
			total_given decimal(15,2) NOT NULL DEFAULT 0,
			email_opt_in tinyint(1) NOT NULL DEFAULT 1,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY partner_code (partner_code),
			KEY user_id (user_id),
			KEY email (email),
			KEY status (status)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'payments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(64) NOT NULL,
			partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			amount decimal(15,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'USD',
			gateway varchar(50) NOT NULL DEFAULT '',
			gateway_txn_id varchar(191) NOT NULL DEFAULT '',
			purpose varchar(100) NOT NULL DEFAULT 'donation',
			status varchar(20) NOT NULL DEFAULT 'pending',
			note text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			paid_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY partner_id (partner_id),
			KEY status (status),
			KEY gateway (gateway)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'subscribers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(191) NOT NULL,
			name varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'subscribed',
			token varchar(64) NOT NULL,
			source varchar(50) NOT NULL DEFAULT 'website',
			created_at datetime NOT NULL,
			confirmed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY token (token)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'campaigns' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subject varchar(255) NOT NULL,
			preheader varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			audience varchar(30) NOT NULL DEFAULT 'subscribers',
			status varchar(20) NOT NULL DEFAULT 'draft',
			total int(11) NOT NULL DEFAULT 0,
			sent_count int(11) NOT NULL DEFAULT 0,
			scheduled_at datetime NULL,
			created_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'email_queue' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(191) NOT NULL,
			subject varchar(255) NOT NULL,
			body longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			attempts tinyint(3) NOT NULL DEFAULT 0,
			error text NULL,
			created_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY campaign_id (campaign_id)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'prayer_requests' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			request text NOT NULL,
			is_private tinyint(1) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'new',
			prayer_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'visits' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			visit_date date NULL,
			service varchar(191) NOT NULL DEFAULT '',
			adults int(11) NOT NULL DEFAULT 1,
			kids int(11) NOT NULL DEFAULT 0,
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset;";

		$sql[] = 'CREATE TABLE ' . kcs_table( 'announcements' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			body longtext NOT NULL,
			kind varchar(20) NOT NULL DEFAULT 'news',
			link varchar(255) NOT NULL DEFAULT '',
			plan_ids varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Roles & capabilities.
	 */
	public static function create_roles() {
		add_role(
			'kcs_partner',
			__( 'Church Partner', 'kingdom-church-suite' ),
			array( 'read' => true )
		);
		add_role(
			'kcs_church_admin',
			__( 'Church Admin', 'kingdom-church-suite' ),
			array(
				'read'         => true,
				'manage_kcs'   => true,
				'upload_files' => true,
			)
		);
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_kcs' );
		}
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( 'manage_kcs' );
		}
	}

	/**
	 * Create the portal pages once.
	 */
	public static function create_pages() {
		$pages = array(
			'partnership'       => array( __( 'Partnership', 'kingdom-church-suite' ), '[kcs_partnership_plans]' ),
			'partner_login'     => array( __( 'Partner Login', 'kingdom-church-suite' ), '[kcs_partner_login]' ),
			'partner_register'  => array( __( 'Become a Partner', 'kingdom-church-suite' ), '[kcs_partner_register]' ),
			'partner_dashboard' => array( __( 'Partner Dashboard', 'kingdom-church-suite' ), '[kcs_partner_dashboard]' ),
			'give'              => array( __( 'Give Online', 'kingdom-church-suite' ), '[kcs_give]' ),
			'thank_you'         => array( __( 'Thank You', 'kingdom-church-suite' ), '[kcs_payment_status]' ),
			'watch_live'        => array( __( 'Watch Live', 'kingdom-church-suite' ), '[kcs_livestream chat="yes"]' ),
			'prayer'            => array( __( 'Prayer Request', 'kingdom-church-suite' ), '[kcs_prayer_form][kcs_prayer_wall]' ),
			'visit'             => array( __( 'Plan Your Visit', 'kingdom-church-suite' ), '[kcs_visit_form]' ),
			'church_admin'      => array( __( 'Church Admin', 'kingdom-church-suite' ), '[kcs_church_admin]' ),
		);
		$settings = get_option( 'kcs_settings', array() );
		foreach ( $pages as $key => $page ) {
			$opt = 'page_' . $key;
			if ( ! empty( $settings[ $opt ] ) && get_post_status( (int) $settings[ $opt ] ) ) {
				continue;
			}
			// Reuse an existing page that already holds the shortcode.
			$tag      = preg_match( '/^\[(\w+)/', $page[1], $m ) ? $m[1] : '';
			$existing = $tag ? get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private' ),
					's'              => '[' . $tag,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			) : array();
			if ( $existing ) {
				$settings[ $opt ] = (int) $existing[0];
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_title'   => $page[0],
					'post_content' => $page[1],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$settings[ $opt ] = $id;
			}
		}
		update_option( 'kcs_settings', $settings );
	}

	/**
	 * Default partnership plans.
	 */
	public static function default_plans() {
		global $wpdb;
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'plans' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $count > 0 ) {
			return;
		}
		$currency = 'USD';
		$plans    = array(
			array( 'Seed Partner', 'Sow a monthly seed into the vision.', "Monthly partner newsletter\nPrayer covering\nPartner-only updates", 25, 0, 0 ),
			array( 'Kingdom Builder', 'Help us reach more souls every month.', "Everything in Seed\nQuarterly partner meeting\nExclusive teaching resources", 100, 1, 1 ),
			array( 'Covenant Partner', 'Stand with us at the highest level.', "Everything in Kingdom Builder\nAnnual partners' banquet\nDirect line to the pastoral team", 500, 0, 2 ),
			array( 'Custom Partnership', 'Choose your own amount and frequency.', 'Give as the Lord leads you', 0, 0, 3 ),
		);
		foreach ( $plans as $p ) {
			$wpdb->insert(
				kcs_table( 'plans' ),
				array(
					'name'                => $p[0],
					'description'         => $p[1],
					'benefits'            => $p[2],
					'amount'              => $p[3],
					'currency'            => $currency,
					'frequency'           => 'monthly',
					'allow_custom_amount' => 0 === $p[3] ? 1 : 0,
					'is_featured'         => $p[4],
					'sort_order'          => $p[5],
					'is_active'           => 1,
					'created_at'          => current_time( 'mysql' ),
				)
			);
		}
	}
}
