<?php
/**
 * Front-end church admin dashboard ([kcs_church_admin]).
 *
 * Church staff manage partners, payments, prayer requests, visits, testimonies,
 * partner notifications and pastors' WhatsApp contacts without wp-admin.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Church admin dashboard.
 */
class KCS_Church_Admin {

	/**
	 * Flash message.
	 *
	 * @var array|null
	 */
	private static $flash = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'kcs_church_admin', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'admin_init', array( __CLASS__, 'keep_out_of_wp_admin' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Views (slug => [label, icon]).
	 *
	 * @return array
	 */
	public static function views() {
		return array(
			'overview'    => array( __( 'Overview', 'kingdom-church-suite' ), '◎' ),
			'partners'    => array( __( 'Partners', 'kingdom-church-suite' ), '♥' ),
			'plans'       => array( __( 'Plans & currency', 'kingdom-church-suite' ), '◈' ),
			'payments'    => array( __( 'Payments', 'kingdom-church-suite' ), '₦' ),
			'prayers'     => array( __( 'Prayer requests', 'kingdom-church-suite' ), '✦' ),
			'visits'      => array( __( 'Visits', 'kingdom-church-suite' ), '⌂' ),
			'testimonies' => array( __( 'Testimonies', 'kingdom-church-suite' ), '❝' ),
			'notify'      => array( __( 'Notify partners', 'kingdom-church-suite' ), '✉' ),
			'pastors'     => array( __( 'Pastors & WhatsApp', 'kingdom-church-suite' ), '☎' ),
			'website'     => array( __( 'Website', 'kingdom-church-suite' ), '▣' ),
		);
	}

	/**
	 * Is the current user church staff?
	 *
	 * @return bool
	 */
	public static function can() {
		return current_user_can( 'manage_kcs' );
	}

	/**
	 * Church Admin users never see wp-admin.
	 */
	public static function keep_out_of_wp_admin() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$user = wp_get_current_user();
		if ( in_array( 'kcs_church_admin', (array) $user->roles, true ) && ! current_user_can( 'edit_posts' ) && ! isset( $_GET['kcs_media'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( kcs_page_url( 'church_admin' ) );
			exit;
		}
	}

	/**
	 * Send church staff to their dashboard after login.
	 *
	 * @param string           $redirect  Redirect.
	 * @param string           $requested Requested.
	 * @param WP_User|WP_Error $user      User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'kcs_church_admin', (array) $user->roles, true ) ) {
			return kcs_page_url( 'church_admin' );
		}
		return $redirect;
	}

	/**
	 * Hide the toolbar for church admins.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		$user = wp_get_current_user();
		return in_array( 'kcs_church_admin', (array) $user->roles, true ) && ! current_user_can( 'edit_posts' ) ? false : $show;
	}

	/**
	 * Styles on the dashboard page.
	 */
	public static function assets() {
		if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'kcs_church_admin' ) ) {
			wp_enqueue_style( 'kcs-church-admin', KCS_URL . 'assets/css/church-admin.css', array( 'kcs-public' ), KCS_VERSION );
		}
	}

	/**
	 * URL of a view.
	 *
	 * @param string $view View.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $view = 'overview', $args = array() ) {
		return add_query_arg( array_merge( array( 'view' => $view ), $args ), kcs_page_url( 'church_admin' ) );
	}

	/* =====================================================================
	 * Actions
	 * =================================================================== */

	/**
	 * Handle POST actions (PRG pattern).
	 */
	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['kcs_admin_do'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		if ( ! self::can() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-church-suite' ), 403 );
		}
		if ( ! isset( $_POST['_kcs_admin'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kcs_admin'] ), 'kcs_church_admin' ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'kingdom-church-suite' ), 403 );
		}
		global $wpdb;
		$d    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$do   = sanitize_key( $d['kcs_admin_do'] );
		$view = sanitize_key( $d['view'] ?? 'overview' );
		$id   = absint( $d['id'] ?? 0 );
		$msg  = __( 'Saved.', 'kingdom-church-suite' );
		$type = 'success';

		switch ( $do ) {
			case 'partner_save':
				$data    = array_intersect_key( $d, array_flip( array( 'first_name', 'last_name', 'email', 'phone', 'country', 'city', 'status', 'next_due_date', 'notes' ) ) );
				$partner = KCS_Partners::get( $id );
				$moving  = $partner && ! empty( $d['plan_id'] ) && (int) $d['plan_id'] !== (int) $partner->plan_id;
				$region  = ! empty( $d['region'] ) ? kcs_sanitize_region( $d['region'] ) : '';
				$switch  = $partner && $region && kcs_currency_region( $partner->currency ) !== $region;
				$cur     = $partner && $partner->plan_id ? KCS_Plans::get( $partner->plan_id ) : null;
				// A fixed-price plan is re-priced in the new currency; otherwise only the currency changes.
				if ( $switch && ! $moving && ( ! $cur || KCS_Plans::is_custom( $cur ) ) ) {
					$data['currency'] = kcs_region_currency( $region );
					$switch           = false;
				}
				if ( ! $moving && ! $switch && isset( $d['amount'] ) ) {
					$data['amount'] = $d['amount'];
				}
				KCS_Partners::update( $id, $data );
				if ( $moving || $switch ) {
					$r = KCS_Partners::change_plan( KCS_Partners::get( $id ), $moving ? (int) $d['plan_id'] : (int) $partner->plan_id, (float) ( $d['amount'] ?? $partner->amount ), sanitize_key( $d['frequency'] ?? '' ), $region );
					if ( is_wp_error( $r ) ) {
						$msg  = $r->get_error_message();
						$type = 'error';
					}
				}
				$view = 'partners';
				break;

			case 'partner_status':
				KCS_Partners::update( $id, array( 'status' => sanitize_key( $d['status'] ?? '' ) ) );
				$msg = __( 'Partner status updated.', 'kingdom-church-suite' );
				break;

			case 'payment_complete':
				$p = KCS_Payments::get( $id );
				if ( $p ) {
					KCS_Payments::complete( $p, sanitize_text_field( $d['txn'] ?? '' ), (float) $p->amount, $p->currency, true );
					$msg = __( 'Payment marked as received. Receipt sent and partner unlocked.', 'kingdom-church-suite' );
				}
				break;

			case 'payment_verify':
				$p = KCS_Payments::get( $id );
				if ( $p ) {
					/* translators: %s: status */
					$msg = sprintf( __( 'Checked with the gateway: %s.', 'kingdom-church-suite' ), KCS_Payments::verify_and_update( $p ) );
				}
				break;

			case 'prayer_status':
				$status = sanitize_key( $d['status'] ?? '' );
				if ( array_key_exists( $status, KCS_Prayer::statuses() ) ) {
					$wpdb->update( kcs_table( 'prayer_requests' ), array( 'status' => $status ), array( 'id' => $id ) );
					$msg = __( 'Prayer request updated.', 'kingdom-church-suite' );
				}
				break;

			case 'visit_status':
				$status = sanitize_key( $d['status'] ?? '' );
				if ( array_key_exists( $status, KCS_Visits::statuses() ) ) {
					$wpdb->update( kcs_table( 'visits' ), array( 'status' => $status ), array( 'id' => $id ) );
					$msg = __( 'Visit updated.', 'kingdom-church-suite' );
				}
				break;

			case 'testimony':
				$post = get_post( $id );
				if ( $post && 'kcs_testimony' === $post->post_type ) {
					if ( 'publish' === ( $d['decision'] ?? '' ) ) {
						wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
						$msg = __( 'Testimony published.', 'kingdom-church-suite' );
					} else {
						wp_trash_post( $id );
						$msg = __( 'Testimony removed.', 'kingdom-church-suite' );
					}
				}
				break;

			case 'notify':
				$subject  = sanitize_text_field( $d['subject'] ?? '' );
				$message  = wp_kses_post( $d['message'] ?? '' );
				$plan_ids = array_filter( array_map( 'absint', (array) ( $d['plan_ids'] ?? array() ) ) );
				$status   = sanitize_key( $d['audience_status'] ?? '' );
				if ( ! $subject || ! $message ) {
					$msg  = __( 'Add a subject and a message.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$count = 0;
				if ( ! empty( $d['send_email'] ) ) {
					$ids = array();
					foreach ( KCS_Partners::query( array( 'per_page' => 100000, 'status' => $status ) )['items'] as $p ) {
						if ( ! $plan_ids || in_array( (int) $p->plan_id, $plan_ids, true ) ) {
							$ids[] = (int) $p->id;
						}
					}
					if ( $ids ) {
						$count = KCS_Newsletter::queue_message( 'partners', $subject, $message, '', 0, $ids );
						wp_schedule_single_event( time() + 5, 'kcs_process_email_queue' );
					}
				}
				if ( ! empty( $d['post_dashboard'] ) ) {
					KCS_Announcements::create(
						array(
							'title'    => $subject,
							'body'     => $message,
							'kind'     => sanitize_key( $d['kind'] ?? 'news' ),
							'link'     => $d['link'] ?? '',
							'plan_ids' => $plan_ids,
						)
					);
				}
				/* translators: %d: number of emails */
				$msg = sprintf( __( 'Notification sent. %d email(s) queued.', 'kingdom-church-suite' ), $count );
				break;

			case 'announcement_delete':
				KCS_Announcements::delete( $id );
				$msg = __( 'Announcement removed from dashboards.', 'kingdom-church-suite' );
				break;

			case 'pastor_save':
				if ( empty( $d['name'] ) || empty( $d['phone'] ) ) {
					$msg  = __( 'A pastor needs a name and a WhatsApp number.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				KCS_Pastors::save( $d );
				$msg = __( 'Pastor saved.', 'kingdom-church-suite' );
				break;

			case 'wa_chat_save':
				KCS_WhatsApp_Chat::save( $d );
				$msg  = __( 'Website WhatsApp chat saved.', 'kingdom-church-suite' );
				$view = 'pastors';
				break;

			case 'currency_save':
				$settings = get_option( 'kcs_settings', array() );
				$intl     = strtoupper( sanitize_text_field( $d['currency'] ?? 'USD' ) );
				$local    = strtoupper( sanitize_text_field( $d['local_currency'] ?? '' ) );
				$settings['currency']              = array_key_exists( $intl, kcs_currencies() ) ? $intl : 'USD';
				$settings['local_currency']        = array_key_exists( $local, kcs_currencies() ) ? $local : 'none';
				$settings['international_label']   = sanitize_text_field( $d['international_label'] ?? '' );
				$settings['local_label']           = sanitize_text_field( $d['local_label'] ?? '' );
				$settings['amounts_international'] = preg_replace( '/[^0-9.,]/', '', (string) ( $d['amounts_international'] ?? '' ) );
				$settings['amounts_local']         = preg_replace( '/[^0-9.,]/', '', (string) ( $d['amounts_local'] ?? '' ) );
				update_option( 'kcs_settings', $settings );
				$msg  = __( 'Currency settings saved.', 'kingdom-church-suite' );
				$view = 'plans';
				break;

			case 'plan_save':
				$plan = $id ? KCS_Plans::get( $id ) : null;
				$row  = array_merge(
					$plan ? (array) $plan : array( 'is_active' => 1 ),
					array_intersect_key( $d, array_flip( array( 'name', 'description', 'benefits', 'amount', 'local_amount', 'frequency', 'sort_order' ) ) ),
					array(
						'currency'            => kcs_get_option( 'currency', 'USD' ),
						'allow_custom_amount' => ! empty( $d['allow_custom_amount'] ),
						'is_featured'         => ! empty( $d['is_featured'] ),
						'is_active'           => ! empty( $d['is_active'] ),
					)
				);
				if ( '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
					$msg  = __( 'Please give the plan a name.', 'kingdom-church-suite' );
					$type = 'error';
				} else {
					KCS_Plans::save( $row, $plan ? (int) $plan->id : 0 );
					$msg = __( 'Plan saved.', 'kingdom-church-suite' );
				}
				$view = 'plans';
				break;

			case 'website_save':
				foreach ( (array) apply_filters( 'kcs_website_settings', array() ) as $key => $f ) {
					$raw = $d[ $key ] ?? '';
					switch ( $f[1] ) {
						case 'checkbox':
							$value = ! empty( $raw );
							break;
						case 'url':
							$value = esc_url_raw( $raw );
							break;
						case 'number':
							$value = min( 90, absint( $raw ) );
							break;
						case 'select':
							$value = array_key_exists( $raw, (array) ( $f[4] ?? array() ) ) ? $raw : $f[3];
							break;
						default:
							$value = sanitize_text_field( $raw );
					}
					set_theme_mod( $key, $value );
				}
				$msg  = __( 'Website updated.', 'kingdom-church-suite' );
				$view = 'website';
				break;

			case 'pastor_delete':
				KCS_Pastors::delete( sanitize_key( $d['pastor_id'] ?? '' ) );
				$msg = __( 'Pastor removed.', 'kingdom-church-suite' );
				break;
		}

		set_transient( 'kcs_admin_flash_' . get_current_user_id(), array( $msg, $type ), 60 );
		$back = self::url( $view );
		if ( ! empty( $d['return'] ) ) {
			$back = wp_validate_redirect( esc_url_raw( $d['return'] ), $back );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/* =====================================================================
	 * Rendering
	 * =================================================================== */

	/**
	 * Hidden fields for an action form.
	 *
	 * @param string $do   Action.
	 * @param string $view View to return to.
	 * @param int    $id   Record ID.
	 * @return string
	 */
	private static function fields( $do, $view, $id = 0 ) {
		return '<input type="hidden" name="kcs_admin_do" value="' . esc_attr( $do ) . '"><input type="hidden" name="view" value="' . esc_attr( $view ) . '"><input type="hidden" name="id" value="' . (int) $id . '"><input type="hidden" name="return" value="' . esc_attr( esc_url_raw( add_query_arg( array() ) ) ) . '">' . wp_nonce_field( 'kcs_church_admin', '_kcs_admin', true, false );
	}

	/**
	 * A small inline status form (select + save).
	 *
	 * @param string $do       Action.
	 * @param string $view     View.
	 * @param int    $id       ID.
	 * @param array  $statuses Statuses.
	 * @param string $current  Current.
	 * @return string
	 */
	private static function status_form( $do, $view, $id, $statuses, $current ) {
		$out = '<form method="post" class="kca-inline">' . self::fields( $do, $view, $id ) . '<select name="status" aria-label="' . esc_attr__( 'Status', 'kingdom-church-suite' ) . '" onchange="this.form.submit()">';
		foreach ( $statuses as $k => $l ) {
			$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $current, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $out . '</select><noscript><button class="kcs-btn">' . esc_html__( 'Save', 'kingdom-church-suite' ) . '</button></noscript></form>';
	}

	/**
	 * Shortcode output.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return kcs_get_template( 'partner-login', array( 'notice' => __( 'Church staff: please log in to open the admin dashboard.', 'kingdom-church-suite' ) ) );
		}
		if ( ! self::can() ) {
			return '<div class="kcs-alert kcs-alert--error">' . esc_html__( 'This dashboard is for church staff only.', 'kingdom-church-suite' ) . '</div>';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$views = self::views();
		$view  = isset( $_GET['view'] ) && isset( $views[ sanitize_key( $_GET['view'] ) ] ) ? sanitize_key( $_GET['view'] ) : 'overview';
		// phpcs:enable
		$flash = get_transient( 'kcs_admin_flash_' . get_current_user_id() );
		delete_transient( 'kcs_admin_flash_' . get_current_user_id() );
		$counts = self::badges();

		ob_start();
		?>
		<div class="kca" data-kca>
			<aside class="kca__side">
				<div class="kca__brand"><span class="kca__logo" aria-hidden="true"></span><span><strong><?php echo esc_html( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ); ?></strong><small><?php esc_html_e( 'Church admin', 'kingdom-church-suite' ); ?></small></span></div>
				<nav class="kca__nav" aria-label="<?php esc_attr_e( 'Dashboard sections', 'kingdom-church-suite' ); ?>">
					<?php foreach ( $views as $slug => $v ) : ?>
						<a href="<?php echo esc_url( self::url( $slug ) ); ?>"<?php echo $slug === $view ? ' aria-current="page"' : ''; ?>><span class="kca__ic" aria-hidden="true"><?php echo esc_html( $v[1] ); ?></span><?php echo esc_html( $v[0] ); ?><?php if ( ! empty( $counts[ $slug ] ) ) : ?><em><?php echo (int) $counts[ $slug ]; ?></em><?php endif; ?></a>
					<?php endforeach; ?>
				</nav>
				<a class="kca__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'kingdom-church-suite' ); ?></a>
			</aside>
			<main class="kca__main">
				<header class="kca__head">
					<h2><?php echo esc_html( $views[ $view ][0] ); ?></h2>
					<span class="kcs-muted"><?php echo esc_html( wp_get_current_user()->display_name ); ?> · <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></span>
				</header>
				<?php if ( $flash ) : ?><div class="kcs-alert kcs-alert--<?php echo esc_attr( $flash[1] ); ?>" role="status"><?php echo esc_html( $flash[0] ); ?></div><?php endif; ?>
				<?php call_user_func( array( __CLASS__, 'view_' . $view ) ); ?>
			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Counts shown as nav badges.
	 *
	 * @return array
	 */
	private static function badges() {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'prayers'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'prayer_requests' ) . " WHERE status = 'new'" ),
			'visits'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'visits' ) . " WHERE status = 'new'" ),
			'payments'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'payments' ) . " WHERE status IN ('pending','review') AND gateway = 'bank_transfer'" ),
			'testimonies' => (int) wp_count_posts( 'kcs_testimony' )->pending,
			'partners'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'partners' ) . " WHERE status = 'overdue'" ),
		);
		// phpcs:enable
	}

	/**
	 * Overview.
	 */
	private static function view_overview() {
		global $wpdb;
		$stats = KCS_Partners::stats();
		$sum   = function ( $rows ) {
			return $rows ? implode( ' + ', array_map( function ( $r ) { return kcs_format_money( $r->total, $r->currency ); }, $rows ) ) : kcs_format_money( 0 );
		};
		$due   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'partners' ) . " WHERE status IN ('active','overdue') AND frequency <> 'one_time' AND next_due_date <= %s ORDER BY next_due_date ASC LIMIT 8", gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +7 days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$plans = array();
		foreach ( KCS_Plans::all() as $pl ) {
			$plans[ $pl->id ] = $pl->name;
		}
		$by_plan = $wpdb->get_results( 'SELECT plan_id, COUNT(*) c FROM ' . kcs_table( 'partners' ) . " WHERE status IN ('active','overdue') GROUP BY plan_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$max     = 1;
		foreach ( $by_plan as $r ) {
			$max = max( $max, (int) $r->c );
		}
		?>
		<div class="kca-kpis">
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'partners', array( 'status' => 'active' ) ) ); ?>"><span><?php esc_html_e( 'Active partners', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_by_status']['active']; ?></strong><small><?php echo (int) $stats['partners_total']; ?> <?php esc_html_e( 'total', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi kca-kpi--warn" href="<?php echo esc_url( self::url( 'partners', array( 'status' => 'overdue' ) ) ); ?>"><span><?php esc_html_e( 'Overdue (locked)', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_by_status']['overdue']; ?></strong><small><?php echo (int) $stats['partners_by_status']['pending']; ?> <?php esc_html_e( 'awaiting first payment', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'payments', array( 'status' => 'completed' ) ) ); ?>"><span><?php esc_html_e( 'Received this month', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $sum( $stats['revenue_this_month'] ) ); ?></strong><small><?php echo esc_html( $sum( $stats['revenue_all_time'] ) ); ?> <?php esc_html_e( 'all time', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'prayers' ) ); ?>"><span><?php esc_html_e( 'New prayer requests', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['new_prayers']; ?></strong><small><?php esc_html_e( 'waiting for the prayer team', 'kingdom-church-suite' ); ?></small></a>
		</div>
		<div class="kca-cols">
			<section class="kca-card">
				<h3><?php esc_html_e( 'Due in the next 7 days', 'kingdom-church-suite' ); ?></h3>
				<?php if ( ! $due ) : ?><p class="kcs-muted"><?php esc_html_e( 'No partnership payments are due this week.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
				<ul class="kca-list">
					<?php foreach ( $due as $p ) : ?>
						<li><a href="<?php echo esc_url( self::url( 'partners', array( 'id' => $p->id ) ) ); ?>"><strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong><small><?php echo esc_html( kcs_format_money( $p->amount, $p->currency ) . ' · ' . mysql2date( get_option( 'date_format' ), $p->next_due_date ) ); ?></small></a><?php echo kcs_status_badge( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="kca-card">
				<h3><?php esc_html_e( 'Partners by plan', 'kingdom-church-suite' ); ?></h3>
				<div class="kca-bars">
					<?php foreach ( $by_plan as $r ) : ?>
						<div class="kca-bar"><span><?php echo esc_html( $plans[ $r->plan_id ] ?? __( 'Custom', 'kingdom-church-suite' ) ); ?></span><i style="--w:<?php echo esc_attr( round( $r->c / $max * 100 ) ); ?>%"></i><b><?php echo (int) $r->c; ?></b></div>
					<?php endforeach; ?>
					<?php if ( ! $by_plan ) : ?><p class="kcs-muted"><?php esc_html_e( 'No active partners yet.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
				</div>
				<p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'notify' ) ); ?>"><?php esc_html_e( 'Send a notification', 'kingdom-church-suite' ); ?></a></p>
			</section>
		</div>
		<?php
	}

	/**
	 * Partners list / detail.
	 */
	private static function view_partners() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		$plans = KCS_Plans::all();
		if ( $id && ( $p = KCS_Partners::get( $id ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found,Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
			$pays = KCS_Payments::query( array( 'partner_id' => $p->id, 'per_page' => 20 ) )['items'];
			$lock = KCS_Access::lock_reason( $p );
			?>
			<p><a href="<?php echo esc_url( self::url( 'partners' ) ); ?>">← <?php esc_html_e( 'All partners', 'kingdom-church-suite' ); ?></a></p>
			<div class="kca-cols">
				<form method="post" class="kca-card kcs-form">
					<?php echo self::fields( 'partner_save', 'partners', $p->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h3><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?> <small class="kcs-muted"><?php echo esc_html( $p->partner_code ); ?></small></h3>
					<?php if ( $lock ) : ?><div class="kcs-alert kcs-alert--error"><?php /* translators: %s: reason */ printf( esc_html__( 'Dashboard locked: %s. It unlocks automatically after the next successful payment.', 'kingdom-church-suite' ), esc_html( $lock ) ); ?></div><?php endif; ?>
					<div class="kcs-grid-2">
						<label><?php esc_html_e( 'First name', 'kingdom-church-suite' ); ?><input name="first_name" value="<?php echo esc_attr( $p->first_name ); ?>"></label>
						<label><?php esc_html_e( 'Last name', 'kingdom-church-suite' ); ?><input name="last_name" value="<?php echo esc_attr( $p->last_name ); ?>"></label>
						<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" name="email" value="<?php echo esc_attr( $p->email ); ?>"></label>
						<label><?php esc_html_e( 'Phone', 'kingdom-church-suite' ); ?><input name="phone" value="<?php echo esc_attr( $p->phone ); ?>"></label>
						<label><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?><select name="plan_id"><?php foreach ( $plans as $pl ) : ?><option value="<?php echo (int) $pl->id; ?>" <?php selected( (int) $p->plan_id, (int) $pl->id ); ?>><?php echo esc_html( $pl->name ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?> (<?php echo esc_html( $p->currency ); ?>)<input type="number" step="0.01" name="amount" value="<?php echo esc_attr( $p->amount ); ?>"></label>
						<?php if ( count( kcs_regions() ) > 1 ) : ?>
							<label><?php esc_html_e( 'Partner type', 'kingdom-church-suite' ); ?><select name="region"><?php foreach ( kcs_regions() as $rk => $r ) : ?><option value="<?php echo esc_attr( $rk ); ?>" <?php selected( kcs_currency_region( $p->currency ), $rk ); ?>><?php echo esc_html( $r['label'] . ' (' . $r['currency'] . ')' ); ?></option><?php endforeach; ?></select></label>
						<?php endif; ?>
						<label><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?><select name="status"><?php foreach ( KCS_Partners::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p->status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Next due date', 'kingdom-church-suite' ); ?><input type="date" name="next_due_date" value="<?php echo esc_attr( $p->next_due_date ); ?>"></label>
					</div>
					<label><?php esc_html_e( 'Notes', 'kingdom-church-suite' ); ?><textarea name="notes" rows="4"><?php echo esc_textarea( $p->notes ); ?></textarea></label>
					<button class="kcs-btn"><?php esc_html_e( 'Save partner', 'kingdom-church-suite' ); ?></button>
				</form>
				<section class="kca-card">
					<h3><?php esc_html_e( 'Giving', 'kingdom-church-suite' ); ?></h3>
					<p><?php esc_html_e( 'Total given', 'kingdom-church-suite' ); ?>: <strong><?php echo esc_html( KCS_Partners::total_given_label( $p ) ); ?></strong></p>
					<ul class="kca-list">
						<?php foreach ( $pays as $pay ) : ?>
							<li><span><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( mysql2date( get_option( 'date_format' ), $pay->created_at ) . ' · ' . $pay->gateway ); ?></small></span><?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
						<?php endforeach; ?>
						<?php if ( ! $pays ) : ?><li class="kcs-muted"><?php esc_html_e( 'No payments yet.', 'kingdom-church-suite' ); ?></li><?php endif; ?>
					</ul>
					<?php if ( $p->phone ) : ?><p><a class="kcs-btn kcs-btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $p->phone ) ); ?>"><?php esc_html_e( 'WhatsApp this partner', 'kingdom-church-suite' ); ?></a></p><?php endif; ?>
				</section>
			</div>
			<?php
			return;
		}

		$result = KCS_Partners::query( array( 'search' => $search, 'status' => $status, 'per_page' => 25, 'page' => $page ) );
		$names  = array();
		foreach ( $plans as $pl ) {
			$names[ $pl->id ] = $pl->name;
		}
		?>
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="partners">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone, ID…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search partners', 'kingdom-church-suite' ); ?>">
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-church-suite' ); ?></option><?php foreach ( KCS_Partners::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<button class="kcs-btn"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
		</form>
		<div class="kca-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Partner', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Next due', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Total given', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $result['items'] as $p ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Partner', 'kingdom-church-suite' ); ?>"><a href="<?php echo esc_url( self::url( 'partners', array( 'id' => $p->id ) ) ); ?>"><strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong></a><small><?php echo esc_html( $p->email . ' · ' . $p->phone ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Plan', 'kingdom-church-suite' ); ?>"><?php echo esc_html( $names[ $p->plan_id ] ?? '—' ); ?><small><?php echo esc_html( kcs_format_money( $p->amount, $p->currency ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Next due', 'kingdom-church-suite' ); ?>"><?php echo $p->next_due_date ? esc_html( mysql2date( get_option( 'date_format' ), $p->next_due_date ) ) : '—'; ?></td>
						<td data-label="<?php esc_attr_e( 'Total given', 'kingdom-church-suite' ); ?>"><?php echo esc_html( KCS_Partners::total_given_label( $p ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><?php echo self::status_form( 'partner_status', 'partners', $p->id, KCS_Partners::statuses(), $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $result['items'] ) : ?><tr><td colspan="5" class="kcs-muted"><?php esc_html_e( 'No partners match.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		self::pager( $result['total'], 25, $page, array( 'view' => 'partners', 's' => $search, 'status' => $status ) );
	}

	/**
	 * Payments.
	 */
	private static function view_payments() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		$purpose  = isset( $_GET['purpose'] ) ? sanitize_key( $_GET['purpose'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$purposes = kcs_all_purposes();
		$result   = KCS_Payments::query( array( 'status' => $status, 'search' => $search, 'purpose' => $purpose, 'per_page' => 25, 'page' => $page ) );
		global $wpdb;
		$month = $wpdb->get_results( $wpdb->prepare( 'SELECT purpose, currency, SUM(amount) total FROM ' . kcs_table( 'payments' ) . " WHERE status = 'completed' AND paid_at >= %s GROUP BY purpose, currency ORDER BY total DESC", current_time( 'Y-m-01 00:00:00' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$by    = array();
		foreach ( $month as $m ) {
			$by[ $m->purpose ][] = kcs_format_money( $m->total, $m->currency );
		}
		if ( $by ) :
			?>
			<div class="kca-kpis">
				<?php foreach ( array_slice( $by, 0, 6, true ) as $k => $totals ) : ?>
					<a class="kca-kpi" href="<?php echo esc_url( self::url( 'payments', array( 'purpose' => $k ) ) ); ?>"><span><?php echo esc_html( ( $purposes[ $k ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $k ) ) ) . ' · ' . __( 'this month', 'kingdom-church-suite' ) ); ?></span><strong><?php echo esc_html( implode( ' + ', $totals ) ); ?></strong></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="payments">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Reference, name or email…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search payments', 'kingdom-church-suite' ); ?>">
			<select name="purpose" aria-label="<?php esc_attr_e( 'Purpose', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All purposes', 'kingdom-church-suite' ); ?></option><?php foreach ( $purposes as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $purpose, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-church-suite' ); ?></option><?php foreach ( KCS_Payments::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<button class="kcs-btn"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
		</form>
		<div class="kca-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Giver', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Method', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $result['items'] as $pay ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Giver', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $pay->name ? $pay->name : $pay->email ); ?></strong><small><?php echo esc_html( $pay->reference . ' · ' . mysql2date( get_option( 'date_format' ), $pay->created_at ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Amount', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( $purposes[ $pay->purpose ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $pay->purpose ) ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Method', 'kingdom-church-suite' ); ?>"><?php echo esc_html( $pay->gateway ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td class="kca-actions">
							<?php if ( in_array( $pay->status, array( 'pending', 'review', 'failed' ), true ) ) : ?>
								<form method="post" class="kca-inline"><?php echo self::fields( 'payment_complete', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn"><?php esc_html_e( 'Mark received', 'kingdom-church-suite' ); ?></button></form>
								<?php if ( 'bank_transfer' !== $pay->gateway ) : ?><form method="post" class="kca-inline"><?php echo self::fields( 'payment_verify', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Check gateway', 'kingdom-church-suite' ); ?></button></form><?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $result['items'] ) : ?><tr><td colspan="5" class="kcs-muted"><?php esc_html_e( 'No payments match.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		self::pager( $result['total'], 25, $page, array( 'view' => 'payments', 's' => $search, 'status' => $status, 'purpose' => $purpose ) );
	}

	/**
	 * Partnership plans with international and local prices.
	 */
	private static function view_plans() {
		$freqs   = kcs_frequencies();
		$intl    = kcs_get_option( 'currency', 'USD' );
		$local   = kcs_local_currency();
		$edit_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plans   = KCS_Plans::all();
		?>
		<form method="post" class="kca-card kcs-form">
			<?php echo self::fields( 'currency_save', 'plans' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Local and international partners', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'The international currency is selected by default on the partnership, registration, giving and tithe pages. Visitors can switch to the local currency.', 'kingdom-church-suite' ); ?></p>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'International currency (default)', 'kingdom-church-suite' ); ?><select name="currency"><?php foreach ( kcs_currencies() as $c => $l ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $intl, $c ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Local currency', 'kingdom-church-suite' ); ?><select name="local_currency"><option value="none"><?php esc_html_e( '— No local option —', 'kingdom-church-suite' ); ?></option><?php foreach ( kcs_currencies() as $c => $l ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $local, $c ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'International label', 'kingdom-church-suite' ); ?><input name="international_label" value="<?php echo esc_attr( kcs_get_option( 'international_label', 'International' ) ); ?>"></label>
				<label><?php esc_html_e( 'Local label', 'kingdom-church-suite' ); ?><input name="local_label" value="<?php echo esc_attr( kcs_get_option( 'local_label', 'Local' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Nigeria', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Quick amounts (international)', 'kingdom-church-suite' ); ?><input name="amounts_international" value="<?php echo esc_attr( implode( ',', kcs_region_amounts( 'international' ) ) ); ?>"></label>
				<label><?php esc_html_e( 'Quick amounts (local)', 'kingdom-church-suite' ); ?><input name="amounts_local" value="<?php echo esc_attr( implode( ',', kcs_region_amounts( 'local' ) ) ); ?>"></label>
			</div>
			<button class="kcs-btn"><?php esc_html_e( 'Save currency settings', 'kingdom-church-suite' ); ?></button>
		</form>

		<div class="kca-plans">
			<?php
			foreach ( array_merge( $plans, array( null ) ) as $pl ) :
				$open = $pl ? $edit_id === (int) $pl->id : 'new' === ( $_GET['id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<details class="kca-card kca-plan"<?php echo $open ? ' open' : ''; ?>>
					<summary>
						<?php if ( $pl ) : ?>
							<strong><?php echo esc_html( $pl->name ); ?></strong><?php echo $pl->is_featured ? ' ⭐' : ''; ?>
							<span class="kcs-muted"><?php echo esc_html( KCS_Plans::price_label( $pl, 'international' ) ); ?><?php echo $local && ! KCS_Plans::is_custom( $pl ) ? ' · ' . esc_html( (float) $pl->local_amount > 0 ? KCS_Plans::price_label( $pl, 'local' ) : __( 'local price not set', 'kingdom-church-suite' ) ) : ''; ?></span>
							<?php echo kcs_status_badge( $pl->is_active ? 'active' : 'inactive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							<strong>＋ <?php esc_html_e( 'Add a plan', 'kingdom-church-suite' ); ?></strong>
						<?php endif; ?>
					</summary>
					<form method="post" class="kcs-form">
						<?php echo self::fields( 'plan_save', 'plans', $pl ? $pl->id : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<label><?php esc_html_e( 'Plan name', 'kingdom-church-suite' ); ?><input name="name" required value="<?php echo esc_attr( $pl ? $pl->name : '' ); ?>"></label>
						<div class="kcs-grid-3">
							<?php /* translators: %s: currency code */ ?>
							<label><?php printf( esc_html__( 'International price (%s)', 'kingdom-church-suite' ), esc_html( $intl ) ); ?><input type="number" step="0.01" min="0" name="amount" value="<?php echo esc_attr( $pl ? $pl->amount : '' ); ?>"></label>
							<?php if ( $local ) : ?>
								<?php /* translators: %s: currency code */ ?>
								<label><?php printf( esc_html__( 'Local price (%s)', 'kingdom-church-suite' ), esc_html( $local ) ); ?><input type="number" step="0.01" min="0" name="local_amount" value="<?php echo esc_attr( $pl ? $pl->local_amount : '' ); ?>"></label>
							<?php endif; ?>
							<label><?php esc_html_e( 'Every', 'kingdom-church-suite' ); ?><select name="frequency"><?php foreach ( $freqs as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $pl ? $pl->frequency : 'monthly', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
						</div>
						<label><?php esc_html_e( 'Short description', 'kingdom-church-suite' ); ?><input name="description" value="<?php echo esc_attr( $pl ? $pl->description : '' ); ?>"></label>
						<label><?php esc_html_e( 'Benefits (one per line)', 'kingdom-church-suite' ); ?><textarea name="benefits" rows="4"><?php echo esc_textarea( $pl ? $pl->benefits : '' ); ?></textarea></label>
						<div class="kca-switches">
							<label class="kcs-check kca-switch"><input type="checkbox" name="allow_custom_amount" value="1" <?php checked( $pl && $pl->allow_custom_amount ); ?>> <?php esc_html_e( 'Partner chooses the amount', 'kingdom-church-suite' ); ?></label>
							<label class="kcs-check kca-switch"><input type="checkbox" name="is_featured" value="1" <?php checked( $pl && $pl->is_featured ); ?>> <?php esc_html_e( 'Most popular', 'kingdom-church-suite' ); ?></label>
							<label class="kcs-check kca-switch"><input type="checkbox" name="is_active" value="1" <?php checked( ! $pl || $pl->is_active ); ?>> <?php esc_html_e( 'Active', 'kingdom-church-suite' ); ?></label>
						</div>
						<label><?php esc_html_e( 'Level (higher number = higher plan)', 'kingdom-church-suite' ); ?><input type="number" name="sort_order" value="<?php echo esc_attr( $pl ? $pl->sort_order : count( $plans ) ); ?>"></label>
						<button class="kcs-btn"><?php echo $pl ? esc_html__( 'Save plan', 'kingdom-church-suite' ) : esc_html__( 'Add plan', 'kingdom-church-suite' ); ?></button>
					</form>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Prayer requests.
	 */
	private static function view_prayers() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . kcs_table( 'prayer_requests' ) . " ORDER BY FIELD(status,'new','praying','approved','answered','archived'), id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<p class="kcs-muted">' . esc_html__( 'Requests marked “On prayer wall” or “Answered” (and not private) appear on the public prayer wall.', 'kingdom-church-suite' ) . '</p><div class="kca-stack">';
		foreach ( $rows as $r ) {
			echo '<article class="kca-card kca-item"><div><p>' . nl2br( esc_html( $r->request ) ) . '</p><small class="kcs-muted">' . esc_html( ( $r->name ? $r->name : __( 'Anonymous', 'kingdom-church-suite' ) ) . ' · ' . trim( $r->email . ' ' . $r->phone ) . ' · ' . mysql2date( get_option( 'date_format' ), $r->created_at ) ) . ( $r->is_private ? ' · ' . esc_html__( 'Private', 'kingdom-church-suite' ) : '' ) . ( $r->prayer_count ? ' · 🙏 ' . (int) $r->prayer_count : '' ) . '</small></div>' . self::status_form( 'prayer_status', 'prayers', $r->id, KCS_Prayer::statuses(), $r->status ) . '</article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $rows ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No prayer requests yet.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Visit requests.
	 */
	private static function view_visits() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . kcs_table( 'visits' ) . " ORDER BY FIELD(status,'new','confirmed','visited','no_show'), visit_date ASC, id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<div class="kca-stack">';
		foreach ( $rows as $v ) {
			$wa = $v->phone ? '<a class="kcs-btn kcs-btn--ghost" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $v->phone ) ) . '">WhatsApp</a>' : '';
			echo '<article class="kca-card kca-item"><div><strong>' . esc_html( $v->name ) . '</strong> <span class="kcs-muted">' . esc_html( ( $v->visit_date ? mysql2date( 'D, ' . get_option( 'date_format' ), $v->visit_date ) : __( 'Date not set', 'kingdom-church-suite' ) ) . ( $v->service ? ' · ' . $v->service : '' ) ) . '</span><p class="kcs-muted">' . esc_html( sprintf( /* translators: 1: adults, 2: kids */ __( '%1$d adult(s), %2$d child(ren)', 'kingdom-church-suite' ), $v->adults, $v->kids ) . ' · ' . trim( $v->email . ' ' . $v->phone ) ) . '</p>' . ( $v->notes ? '<p>' . esc_html( $v->notes ) . '</p>' : '' ) . '</div><div class="kca-actions">' . $wa . self::status_form( 'visit_status', 'visits', $v->id, KCS_Visits::statuses(), $v->status ) . '</div></article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $rows ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No visit requests yet. They come from the “Plan your visit” form.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Testimonies waiting for review.
	 */
	private static function view_testimonies() {
		$pending = get_posts( array( 'post_type' => 'kcs_testimony', 'post_status' => 'pending', 'posts_per_page' => 50 ) );
		echo '<div class="kca-stack">';
		foreach ( $pending as $t ) {
			echo '<article class="kca-card kca-item"><div><strong>' . esc_html( get_the_title( $t ) ) . '</strong><div>' . wp_kses_post( $t->post_content ) . '</div><small class="kcs-muted">— ' . esc_html( get_post_meta( $t->ID, '_kcs_person', true ) . ( get_post_meta( $t->ID, '_kcs_location', true ) ? ', ' . get_post_meta( $t->ID, '_kcs_location', true ) : '' ) ) . '</small></div><div class="kca-actions">';
			foreach ( array( 'publish' => __( 'Publish', 'kingdom-church-suite' ), 'trash' => __( 'Remove', 'kingdom-church-suite' ) ) as $k => $l ) {
				echo '<form method="post" class="kca-inline">' . self::fields( 'testimony', 'testimonies', $t->ID ) . '<input type="hidden" name="decision" value="' . esc_attr( $k ) . '"><button class="kcs-btn' . ( 'trash' === $k ? ' kcs-btn--ghost' : '' ) . '">' . esc_html( $l ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></article>';
		}
		if ( ! $pending ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No testimonies are waiting for review.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Notify partners (email and/or dashboard announcement, by plan).
	 */
	private static function view_notify() {
		$plans = KCS_Plans::all();
		?>
		<div class="kca-cols">
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'notify', 'notify' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<fieldset><legend><?php esc_html_e( 'Who should receive it?', 'kingdom-church-suite' ); ?></legend>
					<div class="kca-chips">
						<?php foreach ( $plans as $pl ) : ?>
							<label class="kca-chip"><input type="checkbox" name="plan_ids[]" value="<?php echo (int) $pl->id; ?>"> <?php echo esc_html( $pl->name ); ?></label>
						<?php endforeach; ?>
					</div>
					<small class="kcs-muted"><?php esc_html_e( 'Leave every plan unticked to reach all partners.', 'kingdom-church-suite' ); ?></small>
					<label><?php esc_html_e( 'Partner status', 'kingdom-church-suite' ); ?><select name="audience_status"><option value=""><?php esc_html_e( 'Any status', 'kingdom-church-suite' ); ?></option><option value="active"><?php esc_html_e( 'Active only', 'kingdom-church-suite' ); ?></option><option value="overdue"><?php esc_html_e( 'Overdue only (renewal reminder)', 'kingdom-church-suite' ); ?></option><option value="pending"><?php esc_html_e( 'Awaiting first payment', 'kingdom-church-suite' ); ?></option></select></label>
				</fieldset>
				<label><?php esc_html_e( 'Subject / title', 'kingdom-church-suite' ); ?><input name="subject" required></label>
				<label><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?><textarea name="message" rows="6" required>Dear {first_name},&#10;&#10;</textarea></label>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Type', 'kingdom-church-suite' ); ?><select name="kind"><option value="news"><?php esc_html_e( 'Announcement', 'kingdom-church-suite' ); ?></option><option value="event"><?php esc_html_e( 'Event / meeting', 'kingdom-church-suite' ); ?></option><option value="resource"><?php esc_html_e( 'Resource (teaching, e-book, video)', 'kingdom-church-suite' ); ?></option></select></label>
					<label><?php esc_html_e( 'Link (optional)', 'kingdom-church-suite' ); ?><input type="url" name="link" placeholder="https://"></label>
				</div>
				<label class="kcs-check"><input type="checkbox" name="send_email" value="1" checked> <?php esc_html_e( 'Send by email', 'kingdom-church-suite' ); ?></label>
				<label class="kcs-check"><input type="checkbox" name="post_dashboard" value="1" checked> <?php esc_html_e( 'Show on their partner dashboard', 'kingdom-church-suite' ); ?></label>
				<button class="kcs-btn"><?php esc_html_e( 'Send notification', 'kingdom-church-suite' ); ?></button>
			</form>
			<section class="kca-card">
				<h3><?php esc_html_e( 'On partner dashboards now', 'kingdom-church-suite' ); ?></h3>
				<ul class="kca-list">
					<?php
					$names = array();
					foreach ( $plans as $pl ) {
						$names[ $pl->id ] = $pl->name;
					}
					foreach ( KCS_Announcements::recent( 30 ) as $a ) :
						$targets = array_filter( array_map( 'intval', explode( ',', $a->plan_ids ) ) );
						?>
						<li><span><strong><?php echo esc_html( $a->title ); ?></strong><small><?php echo esc_html( ( $targets ? implode( ', ', array_map( function ( $t ) use ( $names ) { return $names[ $t ] ?? '#' . $t; }, $targets ) ) : __( 'All partners', 'kingdom-church-suite' ) ) . ' · ' . mysql2date( get_option( 'date_format' ), $a->created_at ) ); ?></small></span>
							<form method="post" class="kca-inline"><?php echo self::fields( 'announcement_delete', 'notify', $a->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost" aria-label="<?php esc_attr_e( 'Remove', 'kingdom-church-suite' ); ?>">✕</button></form></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
		<?php
	}

	/**
	 * Pastors & WhatsApp numbers.
	 */
	private static function view_pastors() {
		$plans   = KCS_Access::ranked_plans();
		$top     = KCS_Access::top_plan_id();
		$pastors = KCS_Pastors::all();
		$edit    = null;
		$want    = isset( $_GET['pastor'] ) ? sanitize_key( $_GET['pastor'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $pastors as $p ) {
			if ( $p['id'] === $want ) {
				$edit = $p;
			}
		}
		?>
		<?php $chat = KCS_WhatsApp_Chat::get(); ?>
		<form method="post" class="kca-card kcs-form">
			<?php echo self::fields( 'wa_chat_save', 'pastors' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Website “Chat with us” on WhatsApp', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'A floating WhatsApp button on every page of the public website. Visitors pick a contact and WhatsApp opens with a ready-made message.', 'kingdom-church-suite' ); ?></p>
			<label class="kcs-check"><input type="checkbox" name="wa_enabled" value="1" <?php checked( ! empty( $chat['enabled'] ) ); ?>> <?php esc_html_e( 'Show the WhatsApp chat button on the website', 'kingdom-church-suite' ); ?></label>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Chat title', 'kingdom-church-suite' ); ?><input name="wa_title" value="<?php echo esc_attr( $chat['title'] ); ?>"></label>
				<label><?php esc_html_e( 'Reply time / office hours', 'kingdom-church-suite' ); ?><input name="wa_hours" value="<?php echo esc_attr( $chat['hours'] ); ?>"></label>
			</div>
			<label><?php esc_html_e( 'Greeting', 'kingdom-church-suite' ); ?><textarea name="wa_greeting" rows="2"><?php echo esc_textarea( $chat['greeting'] ); ?></textarea></label>
			<fieldset><legend><?php esc_html_e( 'Contacts (leave the number empty to remove a row)', 'kingdom-church-suite' ); ?></legend>
				<?php
				$rows = $chat['contacts'];
				if ( ! $rows ) {
					$rows = array(
						array( 'label' => __( 'General enquiries', 'kingdom-church-suite' ), 'phone' => '', 'message' => '' ),
						array( 'label' => __( 'Prayer line', 'kingdom-church-suite' ), 'phone' => '', 'message' => __( 'Hello, I would like someone to pray with me.', 'kingdom-church-suite' ) ),
						array( 'label' => __( 'First-time visitors', 'kingdom-church-suite' ), 'phone' => '', 'message' => __( 'Hello, I am planning to visit {church} this Sunday.', 'kingdom-church-suite' ) ),
					);
				}
				$rows[] = array( 'label' => '', 'phone' => '', 'message' => '' );
				foreach ( $rows as $i => $row ) :
					?>
					<div class="kca-wa-row">
						<input name="wa_label[]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label, e.g. Church office', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'kingdom-church-suite' ); ?>">
						<input name="wa_phone[]" value="<?php echo esc_attr( $row['phone'] ); ?>" inputmode="tel" placeholder="2348012345678" aria-label="<?php esc_attr_e( 'WhatsApp number with country code', 'kingdom-church-suite' ); ?>">
						<input name="wa_message[]" value="<?php echo esc_attr( $row['message'] ); ?>" placeholder="<?php esc_attr_e( 'Pre-filled message (optional)', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Pre-filled message', 'kingdom-church-suite' ); ?>">
					</div>
				<?php endforeach; ?>
				<small class="kcs-muted"><?php esc_html_e( 'Use {church} or {page} in a message to insert the church name or the page the visitor is on.', 'kingdom-church-suite' ); ?></small>
			</fieldset>
			<button class="kcs-btn"><?php esc_html_e( 'Save website chat', 'kingdom-church-suite' ); ?></button>
		</form>

		<h3><?php esc_html_e( 'Pastors for partners', 'kingdom-church-suite' ); ?></h3>
		<p class="kcs-muted"><?php esc_html_e( 'Partners see a “Chat on WhatsApp” button for each pastor assigned to their plan (and to lower plans). Partners on the highest plan can chat with every pastor.', 'kingdom-church-suite' ); ?></p>
		<div class="kca-cols">
			<div class="kca-stack">
				<?php foreach ( $pastors as $p ) : ?>
					<article class="kca-card kca-item">
						<div class="kca-person">
							<?php if ( $p['photo'] ) : ?><img src="<?php echo esc_url( $p['photo'] ); ?>" alt=""><?php else : ?><span class="kca-avatar"><?php echo esc_html( mb_substr( $p['name'], 0, 1 ) ); ?></span><?php endif; ?>
							<div><strong><?php echo esc_html( $p['name'] ); ?></strong> <?php echo empty( $p['active'] ) ? kcs_status_badge( 'paused' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small class="kcs-muted"><?php echo esc_html( $p['role'] . ' · +' . $p['phone'] ); ?></small>
								<div class="kca-chips">
									<?php foreach ( $plans as $pl ) : ?>
										<?php if ( in_array( (int) $pl->id, (array) $p['plan_ids'], true ) || (int) $pl->id === $top ) : ?><span class="kca-chip is-on"><?php echo esc_html( $pl->name ); ?></span><?php endif; ?>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<div class="kca-actions">
							<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'pastors', array( 'pastor' => $p['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a>
							<form method="post" class="kca-inline"><?php echo self::fields( 'pastor_delete', 'pastors' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="pastor_id" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Remove', 'kingdom-church-suite' ); ?></button></form>
						</div>
					</article>
				<?php endforeach; ?>
				<?php if ( ! $pastors ) : ?><p class="kcs-muted"><?php esc_html_e( 'No pastors yet. Add the first one with the form.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
			</div>
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'pastor_save', 'pastors' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $edit['id'] ?? '' ); ?>">
				<h3><?php echo $edit ? esc_html__( 'Edit pastor', 'kingdom-church-suite' ) : esc_html__( 'Add a pastor', 'kingdom-church-suite' ); ?></h3>
				<label><?php esc_html_e( 'Name', 'kingdom-church-suite' ); ?><input name="name" required value="<?php echo esc_attr( $edit['name'] ?? '' ); ?>" placeholder="Pastor Emeka Obi"></label>
				<label><?php esc_html_e( 'Role', 'kingdom-church-suite' ); ?><input name="role" value="<?php echo esc_attr( $edit['role'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Partnership pastor', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'WhatsApp number with country code', 'kingdom-church-suite' ); ?><input name="phone" required inputmode="tel" value="<?php echo esc_attr( $edit['phone'] ?? '' ); ?>" placeholder="2348012345678"></label>
				<label><?php esc_html_e( 'Photo URL (optional)', 'kingdom-church-suite' ); ?><input type="url" name="photo" value="<?php echo esc_attr( $edit['photo'] ?? '' ); ?>"></label>
				<fieldset><legend><?php esc_html_e( 'Available to plans', 'kingdom-church-suite' ); ?></legend>
					<div class="kca-chips">
						<?php foreach ( $plans as $pl ) : ?>
							<label class="kca-chip"><input type="checkbox" name="plan_ids[]" value="<?php echo (int) $pl->id; ?>" <?php checked( in_array( (int) $pl->id, (array) ( $edit['plan_ids'] ?? array() ), true ) || (int) $pl->id === $top ); ?> <?php disabled( (int) $pl->id === $top ); ?>> <?php echo esc_html( $pl->name ); ?><?php echo (int) $pl->id === $top ? ' ★' : ''; ?></label>
						<?php endforeach; ?>
					</div>
					<small class="kcs-muted"><?php esc_html_e( '★ The highest plan always reaches every pastor.', 'kingdom-church-suite' ); ?></small>
				</fieldset>
				<label class="kcs-check"><input type="checkbox" name="active" value="1" <?php checked( ! $edit || ! empty( $edit['active'] ) ); ?>> <?php esc_html_e( 'Available for chat', 'kingdom-church-suite' ); ?></label>
				<button class="kcs-btn"><?php esc_html_e( 'Save pastor', 'kingdom-church-suite' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Website: show/hide sections and the homepage banner (settings provided by the active theme).
	 */
	private static function view_website() {
		$fields = (array) apply_filters( 'kcs_website_settings', array() );
		if ( ! $fields ) {
			echo '<p class="kcs-muted">' . esc_html__( 'The active theme does not offer website settings here. Use Appearance → Customize instead.', 'kingdom-church-suite' ) . '</p>';
			return;
		}
		$groups = array(
			'banner'   => __( 'Homepage banner', 'kingdom-church-suite' ),
			'sections' => __( 'Show or hide sections', 'kingdom-church-suite' ),
		);
		?>
		<form method="post" class="kca-cols">
			<?php echo self::fields( 'website_save', 'website' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php foreach ( $groups as $group => $title ) : ?>
				<section class="kca-card kcs-form">
					<h3><?php echo esc_html( $title ); ?></h3>
					<?php if ( 'banner' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Paste a YouTube, Vimeo or MP4 link to play it, muted and looping, behind the homepage banner. Leave it empty to use the banner image.', 'kingdom-church-suite' ); ?></p>
					<?php else : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Untick a section to remove it from every page of the website.', 'kingdom-church-suite' ); ?></p>
						<div class="kca-switches">
					<?php endif; ?>
					<?php
					foreach ( $fields as $key => $f ) :
						if ( $f[2] !== $group ) {
							continue;
						}
						$value = get_theme_mod( $key, $f[3] );
						if ( 'checkbox' === $f[1] ) :
							?>
							<label class="kcs-check kca-switch"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( (bool) $value ); ?>> <?php echo esc_html( $f[0] ); ?></label>
						<?php elseif ( 'select' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><select name="<?php echo esc_attr( $key ); ?>"><?php foreach ( (array) $f[4] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $value, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
						<?php else : ?>
							<label><?php echo esc_html( $f[0] ); ?><input type="<?php echo 'url' === $f[1] ? 'url' : ( 'number' === $f[1] ? 'number' : 'text' ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo 'number' === $f[1] ? ' min="0" max="90"' : ''; ?><?php echo 'url' === $f[1] ? ' placeholder="https://youtu.be/…"' : ''; ?>></label>
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ( 'sections' === $group ) : ?></div><?php endif; ?>
					<button class="kcs-btn"><?php esc_html_e( 'Save website', 'kingdom-church-suite' ); ?></button>
				</section>
			<?php endforeach; ?>
		</form>
		<p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View website', 'kingdom-church-suite' ); ?></a></p>
		<?php
	}

	/**
	 * Simple pager.
	 *
	 * @param int   $total Total.
	 * @param int   $per   Per page.
	 * @param int   $page  Current.
	 * @param array $args  Query args.
	 */
	private static function pager( $total, $per, $page, $args ) {
		$pages = (int) ceil( $total / $per );
		if ( $pages < 2 ) {
			return;
		}
		echo '<nav class="kca-pager">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			echo '<a href="' . esc_url( add_query_arg( array_merge( array_filter( $args ), array( 'pg' => $i ) ), kcs_page_url( 'church_admin' ) ) ) . '"' . ( $i === $page ? ' aria-current="page"' : '' ) . '>' . (int) $i . '</a>';
		}
		echo '</nav>';
	}
}
