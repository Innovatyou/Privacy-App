<?php
/**
 * Plugin bootstrap.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main class.
 */
final class KCS_Plugin {

	/**
	 * Instance.
	 *
	 * @var KCS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return KCS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		load_plugin_textdomain( 'kingdom-church-suite', false, dirname( plugin_basename( KCS_FILE ) ) . '/languages' );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( 'init', array( 'KCS_Installer', 'maybe_upgrade' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		KCS_Post_Types::init();
		KCS_Email::init();
		KCS_Partners::init();
		KCS_Payments::init();
		KCS_Newsletter::init();
		KCS_Livestream::init();
		KCS_Prayer::init();
		KCS_SEO::init();
		KCS_Features::init();
		KCS_Shortcodes::init();
		KCS_Portal::init();
		KCS_REST_API::init();

		if ( is_admin() ) {
			KCS_Admin::init();
		}
	}

	/**
	 * Custom cron interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		$schedules['kcs_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes', 'kingdom-church-suite' ),
		);
		return $schedules;
	}

	/**
	 * Front-end assets.
	 */
	public function enqueue() {
		wp_enqueue_style( 'kcs-public', KCS_URL . 'assets/css/public.css', array(), KCS_VERSION );
		wp_enqueue_script( 'kcs-public', KCS_URL . 'assets/js/public.js', array(), KCS_VERSION, true );
		wp_localize_script(
			'kcs-public',
			'KCS',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'kcs/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'loggedIn' => is_user_logged_in(),
				'i18n'     => array(
					'processing' => __( 'Processing…', 'kingdom-church-suite' ),
					'error'      => __( 'Something went wrong. Please try again.', 'kingdom-church-suite' ),
					'live'       => __( 'LIVE', 'kingdom-church-suite' ),
					'prayed'     => __( 'Prayed 🙏', 'kingdom-church-suite' ),
					'days'       => __( 'Days', 'kingdom-church-suite' ),
					'hours'      => __( 'Hours', 'kingdom-church-suite' ),
					'minutes'    => __( 'Min', 'kingdom-church-suite' ),
					'seconds'    => __( 'Sec', 'kingdom-church-suite' ),
				),
			)
		);
	}
}
