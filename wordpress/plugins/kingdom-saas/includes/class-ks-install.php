<?php
/**
 * Network tables, default settings and cron.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class KS_Install {

	/**
	 * Network activation.
	 */
	public static function activate() {
		self::create_tables();
		if ( ! get_site_option( 'ks_plans' ) ) {
			update_site_option( 'ks_plans', KS_Plans::defaults() );
		}
		if ( ! get_site_option( 'ks_settings' ) ) {
			update_site_option( 'ks_settings', array() );
		}
		update_site_option( 'ks_db_version', KS_DB_VERSION );
		if ( ! wp_next_scheduled( 'ks_daily' ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', 'ks_daily' );
		}
		update_site_option( 'ks_flush_rewrites', 1 );
	}

	/**
	 * Upgrade when the schema version changes.
	 */
	public static function maybe_upgrade() {
		if ( get_site_option( 'ks_db_version' ) !== KS_DB_VERSION ) {
			self::activate();
		}
	}

	/**
	 * Create network tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . ks_table( 'subscriptions' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL,
			church_name varchar(191) NOT NULL DEFAULT '',
			subdomain varchar(191) NOT NULL DEFAULT '',
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_email varchar(191) NOT NULL DEFAULT '',
			plan varchar(40) NOT NULL DEFAULT '',
			billing_interval varchar(10) NOT NULL DEFAULT 'month',
			region varchar(20) NOT NULL DEFAULT 'international',
			status varchar(20) NOT NULL DEFAULT 'trialing',
			trial_ends_at datetime NULL,
			current_period_end datetime NULL,
			cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
			last_notice varchar(40) NOT NULL DEFAULT '',
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY blog_id (blog_id),
			KEY status (status),
			KEY subdomain (subdomain)
		) $charset;"
		);

		dbDelta(
			'CREATE TABLE ' . ks_table( 'invoices' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(64) NOT NULL,
			blog_id bigint(20) unsigned NOT NULL,
			plan varchar(40) NOT NULL DEFAULT '',
			billing_interval varchar(10) NOT NULL DEFAULT 'month',
			amount decimal(15,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'USD',
			gateway varchar(30) NOT NULL DEFAULT '',
			gateway_ref varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			paid_at datetime NULL,
			period_end datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY blog_id (blog_id),
			KEY status (status)
		) $charset;"
		);

		dbDelta(
			'CREATE TABLE ' . ks_table( 'domains' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL,
			domain varchar(191) NOT NULL,
			token varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			is_primary tinyint(1) NOT NULL DEFAULT 0,
			last_error varchar(255) NOT NULL DEFAULT '',
			checked_at datetime NULL,
			verified_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY domain (domain),
			KEY blog_id (blog_id)
		) $charset;"
		);
	}
}
