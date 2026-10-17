<?php
/**
 * Admin dashboard: partners, payments, plans, newsletter, email, prayer requests, settings.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class KCS_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		foreach ( array( 'save_partner', 'partner_bulk', 'payment_action', 'save_plan', 'delete_plan', 'save_campaign', 'subscriber_action', 'import_subscribers', 'send_email', 'prayer_action', 'save_settings', 'export', 'test_email', 'regenerate_api_key', 'process_queue' ) as $action ) {
			add_action( 'admin_post_kcs_' . $action, array( __CLASS__, 'action_' . $action ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( KCS_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=kcs-settings' ) ) . '">' . esc_html__( 'Settings', 'kingdom-church-suite' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		$cap = 'manage_kcs';
		add_menu_page( __( 'Church Suite', 'kingdom-church-suite' ), __( 'Church Suite', 'kingdom-church-suite' ), $cap, 'kcs-dashboard', array( __CLASS__, 'page_dashboard' ), 'dashicons-heart', 3 );
		add_submenu_page( 'kcs-dashboard', __( 'Dashboard', 'kingdom-church-suite' ), __( 'Dashboard', 'kingdom-church-suite' ), $cap, 'kcs-dashboard', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Partners', 'kingdom-church-suite' ), __( 'Partners', 'kingdom-church-suite' ), $cap, 'kcs-partners', array( __CLASS__, 'page_partners' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Payments', 'kingdom-church-suite' ), __( 'Payments', 'kingdom-church-suite' ), $cap, 'kcs-payments', array( __CLASS__, 'page_payments' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Partnership Plans', 'kingdom-church-suite' ), __( 'Plans', 'kingdom-church-suite' ), $cap, 'kcs-plans', array( __CLASS__, 'page_plans' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Email Partners', 'kingdom-church-suite' ), __( 'Email Partners', 'kingdom-church-suite' ), $cap, 'kcs-email', array( __CLASS__, 'page_email' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Newsletter', 'kingdom-church-suite' ), __( 'Newsletter', 'kingdom-church-suite' ), $cap, 'kcs-newsletter', array( __CLASS__, 'page_newsletter' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Prayer Requests', 'kingdom-church-suite' ), __( 'Prayer Requests', 'kingdom-church-suite' ), $cap, 'kcs-prayer', array( __CLASS__, 'page_prayer' ) );
		add_submenu_page( 'kcs-dashboard', __( 'Settings', 'kingdom-church-suite' ), __( 'Settings', 'kingdom-church-suite' ), 'manage_options', 'kcs-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		$is_kcs = false !== strpos( $hook, 'kcs-' );
		$is_seo = $screen && 'post' === $screen->base;
		if ( ! $is_kcs && ! $is_seo ) {
			return;
		}
		wp_enqueue_style( 'kcs-admin', KCS_URL . 'assets/css/admin.css', array(), KCS_VERSION );
		wp_enqueue_script( 'kcs-admin', KCS_URL . 'assets/js/admin.js', array(), KCS_VERSION, true );
		if ( $is_kcs ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	/**
	 * Notices from redirects.
	 */
	public static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['kcs_notice'] ) ) {
			return;
		}
		$msg  = sanitize_text_field( wp_unslash( $_GET['kcs_notice'] ) );
		$type = isset( $_GET['kcs_type'] ) && 'error' === $_GET['kcs_type'] ? 'error' : 'success';
		// phpcs:enable
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $page  Page slug.
	 * @param string $msg   Message.
	 * @param array  $extra Extra args.
	 * @param string $type  success|error.
	 */
	private static function back( $page, $msg, $extra = array(), $type = 'success' ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'kcs_notice' => rawurlencode( $msg ), 'kcs_type' => $type ), $extra ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Verify capability + nonce for an action.
	 *
	 * @param string $action Action.
	 * @param string $cap    Capability.
	 */
	private static function guard( $action, $cap = 'manage_kcs' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-church-suite' ), 403 );
		}
		check_admin_referer( 'kcs_' . $action, isset( $_REQUEST['_kcs_nonce'] ) ? '_kcs_nonce' : '_wpnonce' );
	}

	/**
	 * Selected bulk action from either list-table dropdown.
	 *
	 * @return string
	 */
	private static function bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		foreach ( array( 'kcs_bulk', 'kcs_bulk2' ) as $k ) {
			if ( ! empty( $_POST[ $k ] ) && '-1' !== $_POST[ $k ] ) {
				return sanitize_key( $_POST[ $k ] );
			}
		}
		// phpcs:enable
		return '';
	}

	/**
	 * Page header.
	 *
	 * @param string $title  Title.
	 * @param string $action Optional action button HTML.
	 */
	private static function header( $title, $action = '' ) {
		echo '<div class="wrap kcs-admin"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1> ' . $action . '<hr class="wp-header-end">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/* =====================================================================
	 * Dashboard
	 * =================================================================== */

	/**
	 * Dashboard page.
	 */
	public static function page_dashboard() {
		$stats  = KCS_Partners::stats();
		$recent = KCS_Payments::query( array( 'per_page' => 8 ) );
		$live   = KCS_Livestream::status();
		$cur    = kcs_get_option( 'currency', 'USD' );

		$sum = function ( $rows ) use ( $cur ) {
			if ( ! $rows ) {
				return kcs_format_money( 0, $cur );
			}
			return implode(
				' + ',
				array_map(
					function ( $r ) {
						return kcs_format_money( $r->total, $r->currency );
					},
					$rows
				)
			);
		};

		// Chart: last 12 months in the default currency.
		$months = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$months[ gmdate( 'Y-m', strtotime( "-$i months", strtotime( current_time( 'Y-m-01' ) ) ) ) ] = 0;
		}
		foreach ( $stats['monthly_series'] as $row ) {
			if ( isset( $months[ $row->ym ] ) && $row->currency === $cur ) {
				$months[ $row->ym ] = (float) $row->total;
			}
		}
		$max = max( 1, max( $months ) );

		self::header( __( 'Church Suite Dashboard', 'kingdom-church-suite' ) );
		?>
		<div class="kcs-cards">
			<div class="kcs-card"><span><?php esc_html_e( 'Total partners', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_total']; ?></strong><small><?php echo (int) $stats['partners_by_status']['active']; ?> <?php esc_html_e( 'active', 'kingdom-church-suite' ); ?> · <?php echo (int) $stats['partners_by_status']['overdue']; ?> <?php esc_html_e( 'overdue', 'kingdom-church-suite' ); ?></small></div>
			<div class="kcs-card"><span><?php esc_html_e( 'Received this month', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $sum( $stats['revenue_this_month'] ) ); ?></strong></div>
			<div class="kcs-card"><span><?php esc_html_e( 'All-time giving', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $sum( $stats['revenue_all_time'] ) ); ?></strong></div>
			<div class="kcs-card"><span><?php esc_html_e( 'Subscribers', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['subscribers']; ?></strong></div>
			<div class="kcs-card"><span><?php esc_html_e( 'New prayer requests', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['new_prayers']; ?></strong><small><a href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-prayer' ) ); ?>"><?php esc_html_e( 'View', 'kingdom-church-suite' ); ?> →</a></small></div>
			<div class="kcs-card"><span><?php esc_html_e( 'Live stream', 'kingdom-church-suite' ); ?></span><strong><?php echo $live['is_live'] ? '🔴 ' . esc_html__( 'LIVE', 'kingdom-church-suite' ) : esc_html__( 'Offline', 'kingdom-church-suite' ); ?></strong><small><?php echo esc_html( $live['source'] ); ?></small></div>
		</div>

		<div class="kcs-cols">
			<div class="kcs-panel">
				<h2><?php /* translators: %s: currency */ printf( esc_html__( 'Giving — last 12 months (%s)', 'kingdom-church-suite' ), esc_html( $cur ) ); ?></h2>
				<div class="kcs-chart" role="img" aria-label="<?php esc_attr_e( 'Monthly giving chart', 'kingdom-church-suite' ); ?>">
					<?php foreach ( $months as $ym => $total ) : ?>
						<div class="kcs-chart__bar" title="<?php echo esc_attr( $ym . ': ' . kcs_format_money( $total, $cur ) ); ?>">
							<span style="height:<?php echo esc_attr( round( $total / $max * 100, 1 ) ); ?>%"></span>
							<em><?php echo esc_html( date_i18n( 'M', strtotime( $ym . '-01' ) ) ); ?></em>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="kcs-panel">
				<h2><?php esc_html_e( 'Partners by status', 'kingdom-church-suite' ); ?></h2>
				<ul class="kcs-status-list">
					<?php foreach ( KCS_Partners::statuses() as $k => $label ) : ?>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-partners&status=' . $k ) ); ?>"><?php echo kcs_status_badge( $k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a> <strong><?php echo (int) $stats['partners_by_status'][ $k ]; ?></strong></li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-partners&action=new' ) ); ?>"><?php esc_html_e( 'Add partner', 'kingdom-church-suite' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-email' ) ); ?>"><?php esc_html_e( 'Email partners', 'kingdom-church-suite' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-newsletter&action=new' ) ); ?>"><?php esc_html_e( 'New newsletter', 'kingdom-church-suite' ); ?></a>
				</p>
			</div>
		</div>

		<div class="kcs-panel">
			<h2><?php esc_html_e( 'Recent payments', 'kingdom-church-suite' ); ?> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-payments' ) ); ?>"><?php esc_html_e( 'View all', 'kingdom-church-suite' ); ?></a></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Date', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Name', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Purpose', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Gateway', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $recent['items'] ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No payments yet.', 'kingdom-church-suite' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $recent['items'] as $p ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $p->created_at ) ); ?></td>
						<td><?php echo esc_html( $p->name ? $p->name : $p->email ); ?></td>
						<td><?php echo esc_html( $p->purpose ); ?></td>
						<td><?php echo esc_html( $p->gateway ); ?></td>
						<td><?php echo esc_html( kcs_format_money( $p->amount, $p->currency ) ); ?></td>
						<td><?php echo kcs_status_badge( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="kcs-panel">
			<h2><?php esc_html_e( 'Shortcodes', 'kingdom-church-suite' ); ?></h2>
			<table class="widefat striped kcs-shortcodes">
				<?php
				$codes = array(
					'[kcs_partnership_plans columns="4"]'         => __( 'Partnership plans grid', 'kingdom-church-suite' ),
					'[kcs_partner_register]'                      => __( 'Partner sign-up form', 'kingdom-church-suite' ),
					'[kcs_partner_login]'                         => __( 'Partner login form', 'kingdom-church-suite' ),
					'[kcs_partner_dashboard]'                     => __( 'Partner dashboard (give, history, profile)', 'kingdom-church-suite' ),
					'[kcs_give purpose="" amounts="10,25,50,100"]' => __( 'Online giving form', 'kingdom-church-suite' ),
					'[kcs_payment_status]'                        => __( 'Payment result page', 'kingdom-church-suite' ),
					'[kcs_livestream chat="yes" platform="youtube|facebook|custom"]' => __( 'Live stream player with chat', 'kingdom-church-suite' ),
					'[kcs_live_badge]'                            => __( '"Live now" badge', 'kingdom-church-suite' ),
					'[kcs_service_countdown]'                     => __( 'Countdown to next service', 'kingdom-church-suite' ),
					'[kcs_service_times]'                         => __( 'Weekly service times', 'kingdom-church-suite' ),
					'[kcs_newsletter style="card|inline"]'        => __( 'Newsletter sign-up', 'kingdom-church-suite' ),
					'[kcs_prayer_form] [kcs_prayer_wall]'         => __( 'Prayer request form and public prayer wall', 'kingdom-church-suite' ),
					'[kcs_sermons count="3" series=""] [kcs_events count="3"]' => __( 'Latest sermons / upcoming events', 'kingdom-church-suite' ),
					'[kcs_verse]'                                 => __( 'Verse of the day', 'kingdom-church-suite' ),
				);
				foreach ( $codes as $code => $desc ) {
					echo '<tr><td><code>' . esc_html( $code ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
				}
				?>
			</table>
		</div>
		</div>
		<?php
	}

	/* =====================================================================
	 * Partners
	 * =================================================================== */

	/**
	 * Partners page (list or form).
	 */
	public static function page_partners() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			self::partner_form( 'edit' === $action ? KCS_Partners::get( absint( $_GET['id'] ?? 0 ) ) : null );
			return;
		}
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		// phpcs:enable
		$stats = KCS_Partners::stats();
		$views = array( 'all' => '<a href="' . esc_url( admin_url( 'admin.php?page=kcs-partners' ) ) . '"' . ( $current ? '' : ' class="current"' ) . '>' . esc_html__( 'All', 'kingdom-church-suite' ) . ' <span class="count">(' . (int) $stats['partners_total'] . ')</span></a>' );
		foreach ( KCS_Partners::statuses() as $k => $label ) {
			$views[ $k ] = '<a href="' . esc_url( admin_url( 'admin.php?page=kcs-partners&status=' . $k ) ) . '"' . ( $current === $k ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $stats['partners_by_status'][ $k ] . ')</span></a>';
		}
		$freqs = kcs_frequencies();
		$table = new KCS_List_Table(
			array(
				'singular' => 'partner',
				'plural'   => 'partners',
				'columns'  => array(
					'name'          => __( 'Partner', 'kingdom-church-suite' ),
					'contact'       => __( 'Contact', 'kingdom-church-suite' ),
					'plan'          => __( 'Plan', 'kingdom-church-suite' ),
					'amount'        => __( 'Commitment', 'kingdom-church-suite' ),
					'total_given'   => __( 'Total given', 'kingdom-church-suite' ),
					'next_due_date' => __( 'Next due', 'kingdom-church-suite' ),
					'status'        => __( 'Status', 'kingdom-church-suite' ),
				),
				'sortable' => array( 'amount', 'total_given', 'next_due_date', 'status' ),
				'bulk'     => array(
					'activate'   => __( 'Mark active', 'kingdom-church-suite' ),
					'deactivate' => __( 'Mark inactive', 'kingdom-church-suite' ),
					'email'      => __( 'Send email', 'kingdom-church-suite' ),
					'delete'     => __( 'Delete', 'kingdom-church-suite' ),
				),
				'views'    => $views,
				'query'    => array( 'KCS_Partners', 'query' ),
				'render'   => function ( $col, $p ) use ( $freqs ) {
					switch ( $col ) {
						case 'name':
							$edit = admin_url( 'admin.php?page=kcs-partners&action=edit&id=' . $p->id );
							$pays = admin_url( 'admin.php?page=kcs-payments&partner_id=' . $p->id );
							return '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( trim( $p->first_name . ' ' . $p->last_name ) ) . '</a></strong><br><code>' . esc_html( $p->partner_code ) . '</code><div class="row-actions"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'kingdom-church-suite' ) . '</a> | <a href="' . esc_url( $pays ) . '">' . esc_html__( 'Payments', 'kingdom-church-suite' ) . '</a></div>';
						case 'contact':
							return '<a href="mailto:' . esc_attr( $p->email ) . '">' . esc_html( $p->email ) . '</a><br>' . esc_html( $p->phone ) . ( $p->country ? '<br><small>' . esc_html( trim( $p->city . ', ' . $p->country, ', ' ) ) . '</small>' : '' );
						case 'plan':
							$plan = $p->plan_id ? KCS_Plans::get( $p->plan_id ) : null;
							return esc_html( $plan ? $plan->name : '—' );
						case 'amount':
							return esc_html( kcs_format_money( $p->amount, $p->currency ) ) . '<br><small>' . esc_html( $freqs[ $p->frequency ] ?? '' ) . '</small>';
						case 'total_given':
							return esc_html( kcs_format_money( $p->total_given, $p->currency ) );
						case 'next_due_date':
							return $p->next_due_date ? esc_html( mysql2date( get_option( 'date_format' ), $p->next_due_date ) ) : '—';
						case 'status':
							return kcs_status_badge( $p->status );
					}
					return '';
				},
			)
		);
		$table->prepare_items();

		$export = wp_nonce_url( admin_url( 'admin-post.php?action=kcs_export&type=partners' ), 'kcs_export' );
		self::header( __( 'Partners', 'kingdom-church-suite' ), '<a href="' . esc_url( admin_url( 'admin.php?page=kcs-partners&action=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'kingdom-church-suite' ) . '</a> <a href="' . esc_url( $export ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'kingdom-church-suite' ) . '</a>' );
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="kcs-partners">';
		if ( $current ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $current ) . '">';
		}
		$table->search_box( __( 'Search partners', 'kingdom-church-suite' ), 'kcs-partner' );
		echo '</form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="kcs_partner_bulk">';
		wp_nonce_field( 'kcs_partner_bulk', '_kcs_nonce' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Partner add/edit form.
	 *
	 * @param object|null $p Partner.
	 */
	private static function partner_form( $p ) {
		$is_new = ! $p;
		$v      = function ( $key, $default = '' ) use ( $p ) {
			return $p && isset( $p->$key ) ? $p->$key : $default;
		};
		self::header( $is_new ? __( 'Add Partner', 'kingdom-church-suite' ) : __( 'Edit Partner', 'kingdom-church-suite' ), '<a href="' . esc_url( admin_url( 'admin.php?page=kcs-partners' ) ) . '" class="page-title-action">' . esc_html__( '← Back to partners', 'kingdom-church-suite' ) . '</a>' );
		?>
		<div class="kcs-cols kcs-cols--form">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-panel">
			<input type="hidden" name="action" value="kcs_save_partner">
			<input type="hidden" name="id" value="<?php echo (int) $v( 'id', 0 ); ?>">
			<?php wp_nonce_field( 'kcs_save_partner' ); ?>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'First name', 'kingdom-church-suite' ); ?></th><td><input class="regular-text" name="first_name" required value="<?php echo esc_attr( $v( 'first_name' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Last name', 'kingdom-church-suite' ); ?></th><td><input class="regular-text" name="last_name" value="<?php echo esc_attr( $v( 'last_name' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?></th><td><input class="regular-text" type="email" name="email" required value="<?php echo esc_attr( $v( 'email' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Phone', 'kingdom-church-suite' ); ?></th><td><input class="regular-text" name="phone" value="<?php echo esc_attr( $v( 'phone' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Country / City', 'kingdom-church-suite' ); ?></th><td><input name="country" value="<?php echo esc_attr( $v( 'country' ) ); ?>" placeholder="<?php esc_attr_e( 'Country', 'kingdom-church-suite' ); ?>"> <input name="city" value="<?php echo esc_attr( $v( 'city' ) ); ?>" placeholder="<?php esc_attr_e( 'City', 'kingdom-church-suite' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Address', 'kingdom-church-suite' ); ?></th><td><textarea class="large-text" rows="2" name="address"><?php echo esc_textarea( $v( 'address' ) ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?></th><td><select name="plan_id"><option value="0">—</option>
					<?php foreach ( KCS_Plans::all() as $plan ) : ?>
						<option value="<?php echo (int) $plan->id; ?>" <?php selected( (int) $v( 'plan_id' ), (int) $plan->id ); ?>><?php echo esc_html( $plan->name ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
				<tr><th><?php esc_html_e( 'Commitment', 'kingdom-church-suite' ); ?></th><td>
					<select name="currency"><?php foreach ( kcs_currencies() as $c => $l ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $v( 'currency', kcs_get_option( 'currency', 'USD' ) ), $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select>
					<input type="number" step="0.01" min="0" name="amount" value="<?php echo esc_attr( $v( 'amount' ) ); ?>">
					<select name="frequency"><?php foreach ( kcs_frequencies() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'frequency', 'monthly' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				</td></tr>
				<tr><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><td><select name="status"><?php foreach ( KCS_Partners::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'status', 'active' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th><?php esc_html_e( 'Next due date', 'kingdom-church-suite' ); ?></th><td><input type="date" name="next_due_date" value="<?php echo esc_attr( $v( 'next_due_date' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Emails', 'kingdom-church-suite' ); ?></th><td><label><input type="checkbox" name="email_opt_in" value="1" <?php checked( (int) $v( 'email_opt_in', 1 ), 1 ); ?>> <?php esc_html_e( 'Opted in to newsletter', 'kingdom-church-suite' ); ?></label></td></tr>
				<?php if ( $is_new ) : ?>
					<tr><th><?php esc_html_e( 'Login account', 'kingdom-church-suite' ); ?></th><td><label><input type="checkbox" name="create_user" value="1" checked> <?php esc_html_e( 'Create a partner login and email a password-setup link', 'kingdom-church-suite' ); ?></label></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Internal notes', 'kingdom-church-suite' ); ?></th><td><textarea class="large-text" rows="3" name="notes"><?php echo esc_textarea( $v( 'notes' ) ); ?></textarea></td></tr>
			</table>
			<?php submit_button( $is_new ? __( 'Create partner', 'kingdom-church-suite' ) : __( 'Save partner', 'kingdom-church-suite' ) ); ?>
		</form>
		<?php if ( ! $is_new ) : ?>
			<div class="kcs-panel">
				<h2><?php esc_html_e( 'Summary', 'kingdom-church-suite' ); ?></h2>
				<p><?php esc_html_e( 'Partner ID', 'kingdom-church-suite' ); ?>: <code><?php echo esc_html( $p->partner_code ); ?></code></p>
				<p><?php esc_html_e( 'Total given', 'kingdom-church-suite' ); ?>: <strong><?php echo esc_html( kcs_format_money( $p->total_given, $p->currency ) ); ?></strong></p>
				<p><?php esc_html_e( 'Last payment', 'kingdom-church-suite' ); ?>: <?php echo $p->last_payment_at ? esc_html( mysql2date( get_option( 'date_format' ), $p->last_payment_at ) ) : '—'; ?></p>
				<p><?php esc_html_e( 'Joined', 'kingdom-church-suite' ); ?>: <?php echo esc_html( mysql2date( get_option( 'date_format' ), $p->created_at ) ); ?></p>
				<?php if ( $p->user_id ) : ?><p><a href="<?php echo esc_url( get_edit_user_link( $p->user_id ) ); ?>"><?php esc_html_e( 'WordPress user', 'kingdom-church-suite' ); ?> →</a></p><?php endif; ?>
				<h3><?php esc_html_e( 'Recent payments', 'kingdom-church-suite' ); ?></h3>
				<ul>
					<?php foreach ( KCS_Payments::query( array( 'partner_id' => $p->id, 'per_page' => 10 ) )['items'] as $pay ) : ?>
						<li><?php echo esc_html( mysql2date( get_option( 'date_format' ), $pay->created_at ) . ' — ' . kcs_format_money( $pay->amount, $pay->currency ) ); ?> <?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
					<?php endforeach; ?>
				</ul>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-email&partner_ids=' . $p->id ) ); ?>"><?php esc_html_e( 'Email this partner', 'kingdom-church-suite' ); ?></a></p>
			</div>
		<?php endif; ?>
		</div></div>
		<?php
	}

	/**
	 * Save partner.
	 */
	public static function action_save_partner() {
		self::guard( 'save_partner' );
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in guard, sanitized in repository.
		$id   = absint( $data['id'] ?? 0 );
		$data['email_opt_in'] = ! empty( $data['email_opt_in'] );
		if ( $id ) {
			KCS_Partners::update( $id, $data );
			self::back( 'kcs-partners', __( 'Partner saved.', 'kingdom-church-suite' ), array( 'action' => 'edit', 'id' => $id ) );
		}
		$new = KCS_Partners::create( $data, ! empty( $data['create_user'] ) );
		if ( is_wp_error( $new ) ) {
			self::back( 'kcs-partners', $new->get_error_message(), array( 'action' => 'new' ), 'error' );
		}
		self::back( 'kcs-partners', __( 'Partner created.', 'kingdom-church-suite' ), array( 'action' => 'edit', 'id' => $new ) );
	}

	/**
	 * Bulk partner actions.
	 */
	public static function action_partner_bulk() {
		self::guard( 'partner_bulk' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$action = self::bulk_action();
		// phpcs:enable
		if ( ! $ids || ! $action ) {
			self::back( 'kcs-partners', __( 'Select partners and an action.', 'kingdom-church-suite' ), array(), 'error' );
		}
		switch ( $action ) {
			case 'activate':
			case 'deactivate':
				foreach ( $ids as $id ) {
					KCS_Partners::update( $id, array( 'status' => 'activate' === $action ? 'active' : 'inactive' ) );
				}
				break;
			case 'delete':
				foreach ( $ids as $id ) {
					KCS_Partners::delete( $id );
				}
				break;
			case 'email':
				wp_safe_redirect( admin_url( 'admin.php?page=kcs-email&partner_ids=' . implode( ',', $ids ) ) );
				exit;
		}
		/* translators: %d: number of partners */
		self::back( 'kcs-partners', sprintf( __( '%d partner(s) updated.', 'kingdom-church-suite' ), count( $ids ) ) );
	}

	/* =====================================================================
	 * Payments
	 * =================================================================== */

	/**
	 * Payments page.
	 */
	public static function page_payments() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filters = array(
			'gateway'    => isset( $_GET['gateway'] ) ? sanitize_key( $_GET['gateway'] ) : '',
			'purpose'    => isset( $_GET['purpose'] ) ? sanitize_key( $_GET['purpose'] ) : '',
			'partner_id' => isset( $_GET['partner_id'] ) ? absint( $_GET['partner_id'] ) : 0,
			'date_from'  => isset( $_GET['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'    => isset( $_GET['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		);
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		// phpcs:enable
		$base  = admin_url( 'admin.php?page=kcs-payments' );
		$views = array( 'all' => '<a href="' . esc_url( $base ) . '"' . ( $current ? '' : ' class="current"' ) . '>' . esc_html__( 'All', 'kingdom-church-suite' ) . '</a>' );
		foreach ( KCS_Payments::statuses() as $k => $label ) {
			$views[ $k ] = '<a href="' . esc_url( add_query_arg( 'status', $k, $base ) ) . '"' . ( $current === $k ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		$gateways = KCS_Payments::gateways();
		$table    = new KCS_List_Table(
			array(
				'singular' => 'payment',
				'plural'   => 'payments',
				'columns'  => array(
					'reference' => __( 'Reference', 'kingdom-church-suite' ),
					'giver'     => __( 'Giver', 'kingdom-church-suite' ),
					'purpose'   => __( 'Purpose', 'kingdom-church-suite' ),
					'amount'    => __( 'Amount', 'kingdom-church-suite' ),
					'gateway'   => __( 'Gateway', 'kingdom-church-suite' ),
					'status'    => __( 'Status', 'kingdom-church-suite' ),
					'date'      => __( 'Date', 'kingdom-church-suite' ),
					'actions'   => '',
				),
				'views'    => $views,
				'query'    => function ( $args ) use ( $filters ) {
					return KCS_Payments::query( array_merge( $args, $filters ) );
				},
				'render'   => function ( $col, $p ) use ( $gateways ) {
					switch ( $col ) {
						case 'reference':
							return '<code>' . esc_html( $p->reference ) . '</code>' . ( $p->gateway_txn_id ? '<br><small>' . esc_html( $p->gateway_txn_id ) . '</small>' : '' ) . ( $p->note ? '<br><small class="kcs-note">' . esc_html( $p->note ) . '</small>' : '' );
						case 'giver':
							$out = esc_html( $p->name ) . '<br><small>' . esc_html( $p->email ) . '</small>';
							if ( $p->partner_id ) {
								$out .= '<br><a href="' . esc_url( admin_url( 'admin.php?page=kcs-partners&action=edit&id=' . $p->partner_id ) ) . '">' . esc_html__( 'Partner', 'kingdom-church-suite' ) . ' →</a>';
							}
							return $out;
						case 'purpose':
							return esc_html( ucwords( str_replace( array( '-', '_' ), ' ', $p->purpose ) ) );
						case 'amount':
							return '<strong>' . esc_html( kcs_format_money( $p->amount, $p->currency ) ) . '</strong>';
						case 'gateway':
							return esc_html( isset( $gateways[ $p->gateway ] ) ? $gateways[ $p->gateway ]->title : $p->gateway );
						case 'status':
							return kcs_status_badge( $p->status );
						case 'date':
							return esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $p->paid_at ? $p->paid_at : $p->created_at ) );
						case 'actions':
							$links = array();
							$mk    = function ( $do, $label, $confirm = '' ) use ( $p ) {
								$url = wp_nonce_url( admin_url( 'admin-post.php?action=kcs_payment_action&do=' . $do . '&id=' . $p->id ), 'kcs_payment_action' );
								return '<a class="button button-small" href="' . esc_url( $url ) . '"' . ( $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>' . esc_html( $label ) . '</a>';
							};
							if ( in_array( $p->status, array( 'pending', 'review', 'failed' ), true ) ) {
								if ( 'bank_transfer' !== $p->gateway ) {
									$links[] = $mk( 'verify', __( 'Re-verify', 'kingdom-church-suite' ) );
								}
								$links[] = $mk( 'complete', __( 'Mark paid', 'kingdom-church-suite' ), __( 'Mark this payment as received?', 'kingdom-church-suite' ) );
							}
							if ( 'completed' === $p->status ) {
								$links[] = $mk( 'refunded', __( 'Mark refunded', 'kingdom-church-suite' ), __( 'Mark as refunded? (Refund it in your gateway dashboard first.)', 'kingdom-church-suite' ) );
							}
							if ( 'pending' === $p->status ) {
								$links[] = $mk( 'cancelled', __( 'Cancel', 'kingdom-church-suite' ) );
							}
							return implode( ' ', $links );
					}
					return '';
				},
			)
		);
		$table->prepare_items();
		$export = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'kcs_export', 'type' => 'payments', 'status' => $current ), array_filter( $filters ) ), admin_url( 'admin-post.php' ) ), 'kcs_export' );
		self::header( __( 'Payments', 'kingdom-church-suite' ), '<a href="' . esc_url( $export ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'kingdom-church-suite' ) . '</a>' );
		$table->views();
		?>
		<form method="get" class="kcs-filters">
			<input type="hidden" name="page" value="kcs-payments">
			<?php if ( $current ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $current ); ?>"><?php endif; ?>
			<select name="gateway"><option value=""><?php esc_html_e( 'All gateways', 'kingdom-church-suite' ); ?></option>
				<?php foreach ( $gateways as $g ) : ?><option value="<?php echo esc_attr( $g->id ); ?>" <?php selected( $filters['gateway'], $g->id ); ?>><?php echo esc_html( $g->title ); ?></option><?php endforeach; ?>
			</select>
			<select name="purpose"><option value=""><?php esc_html_e( 'All purposes', 'kingdom-church-suite' ); ?></option>
				<?php foreach ( array_merge( array( 'partnership' => __( 'Partnership', 'kingdom-church-suite' ), 'donation' => __( 'Donation', 'kingdom-church-suite' ) ), kcs_giving_purposes() ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $filters['purpose'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
			</select>
			<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"> – <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
			<?php if ( $filters['partner_id'] ) : ?><input type="hidden" name="partner_id" value="<?php echo (int) $filters['partner_id']; ?>"><?php endif; ?>
			<button class="button"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
			<?php $table->search_box( __( 'Search', 'kingdom-church-suite' ), 'kcs-payment' ); ?>
		</form>
		<?php
		$table->display();
		echo '</div>';
	}

	/**
	 * Single payment actions.
	 */
	public static function action_payment_action() {
		self::guard( 'payment_action' );
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		$p  = KCS_Payments::get( absint( $_GET['id'] ?? 0 ) );
		// phpcs:enable
		if ( ! $p ) {
			self::back( 'kcs-payments', __( 'Payment not found.', 'kingdom-church-suite' ), array(), 'error' );
		}
		switch ( $do ) {
			case 'verify':
				$status = KCS_Payments::verify_and_update( $p );
				/* translators: %s: status */
				self::back( 'kcs-payments', sprintf( __( 'Payment status: %s', 'kingdom-church-suite' ), $status ) );
				break;
			case 'complete':
				KCS_Payments::complete( $p, $p->gateway_txn_id, (float) $p->amount, $p->currency, true );
				self::back( 'kcs-payments', __( 'Payment marked as completed. Receipt sent.', 'kingdom-church-suite' ) );
				break;
			case 'refunded':
			case 'cancelled':
				$wpdb->update( kcs_table( 'payments' ), array( 'status' => $do ), array( 'id' => $p->id ) );
				if ( 'refunded' === $do && $p->partner_id ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . kcs_table( 'partners' ) . ' SET total_given = GREATEST(0, total_given - %f) WHERE id = %d', (float) $p->amount, (int) $p->partner_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
				self::back( 'kcs-payments', __( 'Payment updated.', 'kingdom-church-suite' ) );
				break;
		}
		self::back( 'kcs-payments', __( 'Unknown action.', 'kingdom-church-suite' ), array(), 'error' );
	}

	/* =====================================================================
	 * Plans
	 * =================================================================== */

	/**
	 * Plans page.
	 */
	public static function page_plans() {
		$edit  = isset( $_GET['id'] ) ? KCS_Plans::get( absint( $_GET['id'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$freqs = kcs_frequencies();
		self::header( __( 'Partnership Plans', 'kingdom-church-suite' ) );
		$v = function ( $k, $d = '' ) use ( $edit ) {
			return $edit && isset( $edit->$k ) ? $edit->$k : $d;
		};
		?>
		<div class="kcs-cols kcs-cols--form">
			<div class="kcs-panel">
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Frequency', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( KCS_Plans::all() as $plan ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $plan->name ); ?></strong><?php echo $plan->is_featured ? ' ⭐' : ''; ?><br><small><?php echo esc_html( $plan->description ); ?></small></td>
							<td><?php echo $plan->amount > 0 ? esc_html( kcs_format_money( $plan->amount, $plan->currency ) ) : esc_html__( 'Custom', 'kingdom-church-suite' ); ?></td>
							<td><?php echo esc_html( $freqs[ $plan->frequency ] ?? '' ); ?></td>
							<td><?php echo kcs_status_badge( $plan->is_active ? 'active' : 'inactive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=kcs-plans&id=' . $plan->id ) ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a>
								<a class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this plan?', 'kingdom-church-suite' ) ); ?>')" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kcs_delete_plan&id=' . $plan->id ), 'kcs_delete_plan' ) ); ?>"><?php esc_html_e( 'Delete', 'kingdom-church-suite' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-panel">
				<h2><?php echo $edit ? esc_html__( 'Edit plan', 'kingdom-church-suite' ) : esc_html__( 'Add plan', 'kingdom-church-suite' ); ?></h2>
				<input type="hidden" name="action" value="kcs_save_plan">
				<input type="hidden" name="id" value="<?php echo (int) $v( 'id', 0 ); ?>">
				<?php wp_nonce_field( 'kcs_save_plan' ); ?>
				<p><label><?php esc_html_e( 'Name', 'kingdom-church-suite' ); ?><br><input class="widefat" name="name" required value="<?php echo esc_attr( $v( 'name' ) ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Short description', 'kingdom-church-suite' ); ?><br><textarea class="widefat" name="description" rows="2"><?php echo esc_textarea( $v( 'description' ) ); ?></textarea></label></p>
				<p><label><?php esc_html_e( 'Benefits (one per line)', 'kingdom-church-suite' ); ?><br><textarea class="widefat" name="benefits" rows="4"><?php echo esc_textarea( $v( 'benefits' ) ); ?></textarea></label></p>
				<p>
					<select name="currency"><?php foreach ( kcs_currencies() as $c => $l ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $v( 'currency', kcs_get_option( 'currency', 'USD' ) ), $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select>
					<input type="number" step="0.01" min="0" name="amount" value="<?php echo esc_attr( $v( 'amount', 0 ) ); ?>" style="width:120px">
					<select name="frequency"><?php foreach ( $freqs as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'frequency', 'monthly' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				</p>
				<p><label><input type="checkbox" name="allow_custom_amount" value="1" <?php checked( (int) $v( 'allow_custom_amount', 0 ), 1 ); ?>> <?php esc_html_e( 'Partner chooses amount & frequency', 'kingdom-church-suite' ); ?></label></p>
				<p><label><input type="checkbox" name="is_featured" value="1" <?php checked( (int) $v( 'is_featured', 0 ), 1 ); ?>> <?php esc_html_e( 'Featured ("Most popular")', 'kingdom-church-suite' ); ?></label></p>
				<p><label><input type="checkbox" name="is_active" value="1" <?php checked( (int) $v( 'is_active', 1 ), 1 ); ?>> <?php esc_html_e( 'Active', 'kingdom-church-suite' ); ?></label></p>
				<p><label><?php esc_html_e( 'Sort order', 'kingdom-church-suite' ); ?> <input type="number" name="sort_order" value="<?php echo esc_attr( $v( 'sort_order', 0 ) ); ?>" style="width:80px"></label></p>
				<?php submit_button( $edit ? __( 'Update plan', 'kingdom-church-suite' ) : __( 'Add plan', 'kingdom-church-suite' ) ); ?>
			</form>
		</div></div>
		<?php
	}

	/**
	 * Save plan.
	 */
	public static function action_save_plan() {
		self::guard( 'save_plan' );
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		KCS_Plans::save( $data, absint( $data['id'] ?? 0 ) );
		self::back( 'kcs-plans', __( 'Plan saved.', 'kingdom-church-suite' ) );
	}

	/**
	 * Delete plan.
	 */
	public static function action_delete_plan() {
		self::guard( 'delete_plan' );
		KCS_Plans::delete( absint( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::back( 'kcs-plans', __( 'Plan deleted.', 'kingdom-church-suite' ) );
	}

	/* =====================================================================
	 * Email partners
	 * =================================================================== */

	/**
	 * Compose email to partners.
	 */
	public static function page_email() {
		global $wpdb;
		$ids   = isset( $_GET['partner_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['partner_ids'] ) ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$queue = $wpdb->get_row( 'SELECT SUM(status = "queued") queued, SUM(status = "sent") sent, SUM(status = "failed") failed FROM ' . kcs_table( 'email_queue' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::header( __( 'Email Partners', 'kingdom-church-suite' ) );
		?>
		<div class="kcs-cols kcs-cols--form">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-panel">
			<input type="hidden" name="action" value="kcs_send_email">
			<?php wp_nonce_field( 'kcs_send_email' ); ?>
			<?php if ( $ids ) : ?>
				<input type="hidden" name="partner_ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
				<p class="notice notice-info inline"><?php /* translators: %d: count */ printf( esc_html__( 'Sending to %d selected partner(s).', 'kingdom-church-suite' ), count( $ids ) ); ?></p>
			<?php else : ?>
				<p><label><strong><?php esc_html_e( 'Send to', 'kingdom-church-suite' ); ?></strong><br>
					<select name="audience">
						<?php foreach ( KCS_Newsletter::audiences() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, 'partners' ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></label></p>
			<?php endif; ?>
			<p><label><strong><?php esc_html_e( 'Subject', 'kingdom-church-suite' ); ?></strong><br><input class="widefat" name="subject" required></label></p>
			<?php wp_editor( "<p>Dear {first_name},</p>\n<p></p>", 'kcs_email_message', array( 'textarea_name' => 'message', 'textarea_rows' => 14 ) ); ?>
			<p class="description"><?php esc_html_e( 'Placeholders: {name} {first_name} {email} {church_name} {unsubscribe_url}', 'kingdom-church-suite' ); ?></p>
			<?php submit_button( __( 'Send email', 'kingdom-church-suite' ) ); ?>
		</form>
		<div class="kcs-panel">
			<h2><?php esc_html_e( 'Email queue', 'kingdom-church-suite' ); ?></h2>
			<p><?php esc_html_e( 'Emails are sent in batches every 5 minutes to protect your mail server reputation.', 'kingdom-church-suite' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Queued', 'kingdom-church-suite' ); ?>: <strong><?php echo (int) ( $queue->queued ?? 0 ); ?></strong></li>
				<li><?php esc_html_e( 'Sent', 'kingdom-church-suite' ); ?>: <strong><?php echo (int) ( $queue->sent ?? 0 ); ?></strong></li>
				<li><?php esc_html_e( 'Failed', 'kingdom-church-suite' ); ?>: <strong><?php echo (int) ( $queue->failed ?? 0 ); ?></strong></li>
			</ul>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kcs_process_queue' ), 'kcs_process_queue' ) ); ?>"><?php esc_html_e( 'Send next batch now', 'kingdom-church-suite' ); ?></a></p>
			<p class="description"><?php esc_html_e( 'Tip: for reliable delivery, install an SMTP plugin (e.g. WP Mail SMTP) with Mailgun, SendGrid, Amazon SES, Brevo or Postmark.', 'kingdom-church-suite' ); ?></p>
		</div>
		</div></div>
		<?php
	}

	/**
	 * Queue email to partners.
	 */
	public static function action_send_email() {
		self::guard( 'send_email' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$subject  = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message  = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
		$ids      = ! empty( $_POST['partner_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['partner_ids'] ) ) ) ) ) : array();
		$audience = $ids ? 'partners' : sanitize_key( $_POST['audience'] ?? 'partners' );
		// phpcs:enable
		if ( ! $subject || ! $message ) {
			self::back( 'kcs-email', __( 'Subject and message are required.', 'kingdom-church-suite' ), array(), 'error' );
		}
		$count = KCS_Newsletter::queue_message( $audience, $subject, $message, '', 0, $ids );
		wp_schedule_single_event( time() + 5, 'kcs_process_email_queue' );
		/* translators: %d: count */
		self::back( 'kcs-email', sprintf( __( '%d email(s) queued for delivery.', 'kingdom-church-suite' ), $count ) );
	}

	/**
	 * Process queue now.
	 */
	public static function action_process_queue() {
		self::guard( 'process_queue' );
		KCS_Email::process_queue();
		self::back( 'kcs-email', __( 'Batch processed.', 'kingdom-church-suite' ) );
	}

	/* =====================================================================
	 * Newsletter
	 * =================================================================== */

	/**
	 * Newsletter page: campaigns + subscribers tabs.
	 */
	public static function page_newsletter() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'campaigns';
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// phpcs:enable
		$base = admin_url( 'admin.php?page=kcs-newsletter' );

		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			$c = 'edit' === $action ? KCS_Newsletter::get_campaign( absint( $_GET['id'] ?? 0 ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::header( $c ? __( 'Edit Campaign', 'kingdom-church-suite' ) : __( 'New Campaign', 'kingdom-church-suite' ), '<a class="page-title-action" href="' . esc_url( $base ) . '">' . esc_html__( '← Back', 'kingdom-church-suite' ) . '</a>' );
			$locked = $c && in_array( $c->status, array( 'sending', 'sent' ), true );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-panel">
				<input type="hidden" name="action" value="kcs_save_campaign">
				<input type="hidden" name="id" value="<?php echo (int) ( $c->id ?? 0 ); ?>">
				<?php wp_nonce_field( 'kcs_save_campaign' ); ?>
				<p><label><strong><?php esc_html_e( 'Subject', 'kingdom-church-suite' ); ?></strong><br><input class="widefat" name="subject" required value="<?php echo esc_attr( $c->subject ?? '' ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Preview text (preheader)', 'kingdom-church-suite' ); ?><br><input class="widefat" name="preheader" value="<?php echo esc_attr( $c->preheader ?? '' ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Audience', 'kingdom-church-suite' ); ?><br>
					<select name="audience"><?php foreach ( KCS_Newsletter::audiences() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c->audience ?? 'subscribers', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label></p>
				<?php wp_editor( $c->content ?? "<h2>This week at church</h2>\n<p>Hi {first_name},</p>\n<p></p>", 'kcs_campaign_content', array( 'textarea_name' => 'content', 'textarea_rows' => 18 ) ); ?>
				<p class="description"><?php esc_html_e( 'Placeholders: {name} {first_name} {email} {church_name} {unsubscribe_url}. An unsubscribe link is added automatically.', 'kingdom-church-suite' ); ?></p>
				<p><label><?php esc_html_e( 'Schedule (optional)', 'kingdom-church-suite' ); ?> <input type="datetime-local" name="scheduled_at" value="<?php echo esc_attr( ! empty( $c->scheduled_at ) ? gmdate( 'Y-m-d\TH:i', strtotime( $c->scheduled_at ) ) : '' ); ?>"></label></p>
				<?php if ( ! $locked ) : ?>
					<p>
						<button class="button" name="do" value="save"><?php esc_html_e( 'Save draft', 'kingdom-church-suite' ); ?></button>
						<button class="button" name="do" value="test"><?php esc_html_e( 'Send test to me', 'kingdom-church-suite' ); ?></button>
						<button class="button button-primary" name="do" value="send" onclick="return confirm('<?php echo esc_js( __( 'Send (or schedule) this campaign now?', 'kingdom-church-suite' ) ); ?>')"><?php esc_html_e( 'Send / schedule', 'kingdom-church-suite' ); ?></button>
					</p>
				<?php else : ?>
					<p class="notice notice-info inline"><?php esc_html_e( 'This campaign has been sent and can no longer be edited.', 'kingdom-church-suite' ); ?></p>
				<?php endif; ?>
			</form></div>
			<?php
			return;
		}

		$tabs = array(
			'campaigns'   => __( 'Campaigns', 'kingdom-church-suite' ),
			'subscribers' => __( 'Subscribers', 'kingdom-church-suite' ),
		);
		self::header( __( 'Newsletter', 'kingdom-church-suite' ), '<a class="page-title-action" href="' . esc_url( add_query_arg( 'action', 'new', $base ) ) . '">' . esc_html__( 'New campaign', 'kingdom-church-suite' ) . '</a>' );
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';

		if ( 'subscribers' === $tab ) {
			$table = new KCS_List_Table(
				array(
					'singular' => 'subscriber',
					'plural'   => 'subscribers',
					'columns'  => array(
						'email'      => __( 'Email', 'kingdom-church-suite' ),
						'name'       => __( 'Name', 'kingdom-church-suite' ),
						'status'     => __( 'Status', 'kingdom-church-suite' ),
						'source'     => __( 'Source', 'kingdom-church-suite' ),
						'created_at' => __( 'Joined', 'kingdom-church-suite' ),
					),
					'bulk'     => array(
						'unsubscribe' => __( 'Unsubscribe', 'kingdom-church-suite' ),
						'resubscribe' => __( 'Mark subscribed', 'kingdom-church-suite' ),
						'delete'      => __( 'Delete', 'kingdom-church-suite' ),
					),
					'query'    => function ( $args ) use ( $wpdb ) {
						$t      = kcs_table( 'subscribers' );
						$like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
						$limit  = (int) $args['per_page'];
						$offset = ( (int) $args['page'] - 1 ) * $limit;
						// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE email LIKE %s OR name LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $limit, $offset ) );
						$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE email LIKE %s OR name LIKE %s", $like, $like ) );
						// phpcs:enable
						return array( 'items' => $items, 'total' => $total );
					},
					'render'   => function ( $col, $s ) {
						if ( 'status' === $col ) {
							return kcs_status_badge( $s->status );
						}
						if ( 'created_at' === $col ) {
							return esc_html( mysql2date( get_option( 'date_format' ), $s->created_at ) );
						}
						return esc_html( $s->$col );
					},
				)
			);
			$table->prepare_items();
			$export = wp_nonce_url( admin_url( 'admin-post.php?action=kcs_export&type=subscribers' ), 'kcs_export' );
			?>
			<div class="kcs-cols kcs-cols--form">
				<div>
					<form method="get"><input type="hidden" name="page" value="kcs-newsletter"><input type="hidden" name="tab" value="subscribers"><?php $table->search_box( __( 'Search', 'kingdom-church-suite' ), 'kcs-sub' ); ?></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="kcs_subscriber_action"><?php wp_nonce_field( 'kcs_subscriber_action', '_kcs_nonce' ); ?><?php $table->display(); ?></form>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-panel">
					<h2><?php esc_html_e( 'Add / import subscribers', 'kingdom-church-suite' ); ?></h2>
					<input type="hidden" name="action" value="kcs_import_subscribers">
					<?php wp_nonce_field( 'kcs_import_subscribers' ); ?>
					<p><?php esc_html_e( 'One per line: email,name', 'kingdom-church-suite' ); ?></p>
					<textarea name="list" rows="8" class="widefat" placeholder="jane@example.com,Jane Doe"></textarea>
					<p><label><input type="checkbox" name="confirm" value="1"> <?php esc_html_e( 'Send confirmation email (double opt-in)', 'kingdom-church-suite' ); ?></label></p>
					<?php submit_button( __( 'Import', 'kingdom-church-suite' ), 'secondary' ); ?>
					<p><a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'kingdom-church-suite' ); ?></a></p>
				</form>
			</div></div>
			<?php
			return;
		}

		$campaigns = $wpdb->get_results( 'SELECT * FROM ' . kcs_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 100' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$aud       = KCS_Newsletter::audiences();
		?>
		<table class="widefat striped kcs-mt">
			<thead><tr><th><?php esc_html_e( 'Subject', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Audience', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Delivered', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Date', 'kingdom-church-suite' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $campaigns ) : ?><tr><td colspan="5"><?php esc_html_e( 'No campaigns yet.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $campaigns as $c ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => $c->id ), $base ) ); ?>"><strong><?php echo esc_html( $c->subject ); ?></strong></a></td>
					<td><?php echo esc_html( $aud[ $c->audience ] ?? $c->audience ); ?></td>
					<td><?php echo kcs_status_badge( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo (int) $c->sent_count; ?> / <?php echo (int) $c->total; ?></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $c->sent_at ? $c->sent_at : ( $c->scheduled_at ? $c->scheduled_at : $c->created_at ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Save / test / send a campaign.
	 */
	public static function action_save_campaign() {
		self::guard( 'save_campaign' );
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id   = absint( $data['id'] ?? 0 );
		$c    = $id ? KCS_Newsletter::get_campaign( $id ) : null;
		if ( $c && in_array( $c->status, array( 'sending', 'sent' ), true ) ) {
			self::back( 'kcs-newsletter', __( 'Sent campaigns cannot be changed.', 'kingdom-church-suite' ), array(), 'error' );
		}
		$id = KCS_Newsletter::save_campaign( $data, $id );
		$do = sanitize_key( $data['do'] ?? 'save' );

		if ( 'test' === $do ) {
			$c    = KCS_Newsletter::get_campaign( $id );
			$user = wp_get_current_user();
			$vars = array( 'name' => $user->display_name, 'first_name' => $user->first_name ? $user->first_name : $user->display_name, 'email' => $user->user_email, 'unsubscribe_url' => home_url( '/' ) );
			KCS_Email::send( $user->user_email, '[TEST] ' . KCS_Email::replace( $c->subject, $vars, false ), KCS_Email::replace( $c->content, $vars ), array( 'preheader' => $c->preheader, 'unsubscribe_url' => home_url( '/' ) ) );
			/* translators: %s: email */
			self::back( 'kcs-newsletter', sprintf( __( 'Test sent to %s.', 'kingdom-church-suite' ), $user->user_email ), array( 'action' => 'edit', 'id' => $id ) );
		}
		if ( 'send' === $do ) {
			$c = KCS_Newsletter::get_campaign( $id );
			if ( $c->scheduled_at && strtotime( get_gmt_from_date( $c->scheduled_at ) ) > time() + 60 ) {
				global $wpdb;
				wp_clear_scheduled_hook( 'kcs_send_scheduled_campaign', array( $id ) );
				wp_schedule_single_event( strtotime( get_gmt_from_date( $c->scheduled_at ) ), 'kcs_send_scheduled_campaign', array( $id ) );
				$wpdb->update( kcs_table( 'campaigns' ), array( 'status' => 'scheduled' ), array( 'id' => $id ) );
				self::back( 'kcs-newsletter', __( 'Campaign scheduled.', 'kingdom-church-suite' ) );
			}
			$count = KCS_Newsletter::send_campaign( $id );
			if ( is_wp_error( $count ) ) {
				self::back( 'kcs-newsletter', $count->get_error_message(), array(), 'error' );
			}
			/* translators: %d: count */
			self::back( 'kcs-newsletter', sprintf( __( 'Campaign queued for %d recipient(s).', 'kingdom-church-suite' ), $count ) );
		}
		self::back( 'kcs-newsletter', __( 'Draft saved.', 'kingdom-church-suite' ), array( 'action' => 'edit', 'id' => $id ) );
	}

	/**
	 * Subscriber bulk actions.
	 */
	public static function action_subscriber_action() {
		self::guard( 'subscriber_action' );
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$action = self::bulk_action();
		// phpcs:enable
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$wpdb->delete( kcs_table( 'subscribers' ), array( 'id' => $id ) );
			} elseif ( in_array( $action, array( 'unsubscribe', 'resubscribe' ), true ) ) {
				$wpdb->update( kcs_table( 'subscribers' ), array( 'status' => 'unsubscribe' === $action ? 'unsubscribed' : 'subscribed' ), array( 'id' => $id ) );
			}
		}
		self::back( 'kcs-newsletter', __( 'Subscribers updated.', 'kingdom-church-suite' ), array( 'tab' => 'subscribers' ) );
	}

	/**
	 * Import subscribers.
	 */
	public static function action_import_subscribers() {
		self::guard( 'import_subscribers' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$lines   = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['list'] ?? '' ) ) );
		$confirm = ! empty( $_POST['confirm'] );
		// phpcs:enable
		$count = 0;
		foreach ( $lines as $line ) {
			$parts = str_getcsv( trim( $line ) );
			if ( ! empty( $parts[0] ) && is_email( trim( $parts[0] ) ) ) {
				$r = KCS_Newsletter::subscribe( trim( $parts[0] ), isset( $parts[1] ) ? trim( $parts[1] ) : '', 'import', ! $confirm );
				if ( ! is_wp_error( $r ) && 'exists' !== $r ) {
					++$count;
				}
			}
		}
		/* translators: %d: count */
		self::back( 'kcs-newsletter', sprintf( __( '%d subscriber(s) imported.', 'kingdom-church-suite' ), $count ), array( 'tab' => 'subscribers' ) );
	}

	/* =====================================================================
	 * Prayer requests
	 * =================================================================== */

	/**
	 * Prayer page.
	 */
	public static function page_prayer() {
		global $wpdb;
		$statuses = KCS_Prayer::statuses();
		$table    = new KCS_List_Table(
			array(
				'singular' => 'prayer',
				'plural'   => 'prayers',
				'columns'  => array(
					'request'    => __( 'Request', 'kingdom-church-suite' ),
					'person'     => __( 'From', 'kingdom-church-suite' ),
					'visibility' => __( 'Visibility', 'kingdom-church-suite' ),
					'status'     => __( 'Status', 'kingdom-church-suite' ),
					'created_at' => __( 'Date', 'kingdom-church-suite' ),
				),
				'bulk'     => array_merge( $statuses, array( 'delete' => __( 'Delete', 'kingdom-church-suite' ) ) ),
				'query'    => function ( $args ) use ( $wpdb ) {
					$t      = kcs_table( 'prayer_requests' );
					$limit  = (int) $args['per_page'];
					$offset = ( (int) $args['page'] - 1 ) * $limit;
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( $args['status'] ) {
						$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $args['status'], $limit, $offset ) );
						$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = %s", $args['status'] ) );
					} else {
						$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
						$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
					}
					// phpcs:enable
					return array( 'items' => $items, 'total' => $total );
				},
				'render'   => function ( $col, $r ) {
					switch ( $col ) {
						case 'request':
							return nl2br( esc_html( $r->request ) ) . ( $r->prayer_count ? '<br><small>🙏 ' . (int) $r->prayer_count . '</small>' : '' );
						case 'person':
							return esc_html( $r->name ? $r->name : __( 'Anonymous', 'kingdom-church-suite' ) ) . '<br><small>' . esc_html( $r->email ) . ' ' . esc_html( $r->phone ) . '</small>';
						case 'visibility':
							return $r->is_private ? esc_html__( 'Private', 'kingdom-church-suite' ) : esc_html__( 'Can be shared', 'kingdom-church-suite' );
						case 'status':
							return kcs_status_badge( $r->status );
						case 'created_at':
							return esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $r->created_at ) );
					}
					return '';
				},
			)
		);
		$table->prepare_items();
		self::header( __( 'Prayer Requests', 'kingdom-church-suite' ) );
		echo '<p class="description">' . esc_html__( 'Only requests that are not private and marked "On prayer wall" or "Answered" appear publicly on the prayer wall.', 'kingdom-church-suite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="kcs_prayer_action">';
		wp_nonce_field( 'kcs_prayer_action', '_kcs_nonce' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Prayer bulk actions.
	 */
	public static function action_prayer_action() {
		self::guard( 'prayer_action' );
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$action = self::bulk_action();
		// phpcs:enable
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$wpdb->delete( kcs_table( 'prayer_requests' ), array( 'id' => $id ) );
			} elseif ( array_key_exists( $action, KCS_Prayer::statuses() ) ) {
				$wpdb->update( kcs_table( 'prayer_requests' ), array( 'status' => $action ), array( 'id' => $id ) );
			}
		}
		self::back( 'kcs-prayer', __( 'Prayer requests updated.', 'kingdom-church-suite' ) );
	}

	/* =====================================================================
	 * Settings
	 * =================================================================== */

	/**
	 * Settings page.
	 */
	public static function page_settings() {
		$tabs = KCS_Settings::tabs();
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all  = KCS_Settings::schema();
		self::header( __( 'Church Suite Settings', 'kingdom-church-suite' ) );
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=kcs-settings&tab=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-settings">
			<input type="hidden" name="action" value="kcs_save_settings">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'kcs_save_settings' ); ?>
			<?php if ( 'payments' === $tab ) : ?>
				<p class="description"><?php esc_html_e( 'Enable one or more gateways. Each gateway verifies every payment server-to-server before marking it paid. Configure the webhook URL shown under each gateway in its dashboard for instant confirmation.', 'kingdom-church-suite' ); ?></p>
			<?php endif; ?>
			<?php if ( 'api' === $tab ) : ?>
				<?php self::api_docs(); ?>
			<?php endif; ?>
			<?php foreach ( $all[ $tab ] as $section => $fields ) : ?>
				<div class="kcs-panel kcs-settings__section<?php echo 'payments' === $tab ? ' kcs-collapsible' : ''; ?>">
					<h2><?php echo esc_html( $section ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( $fields as $key => $field ) : ?>
							<tr>
								<th scope="row"><label for="kcs_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
								<td><?php self::render_field( $key, $field ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
		<?php if ( 'email' === $tab ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kcs-panel">
				<input type="hidden" name="action" value="kcs_test_email"><?php wp_nonce_field( 'kcs_test_email' ); ?>
				<h2><?php esc_html_e( 'Send a test email', 'kingdom-church-suite' ); ?></h2>
				<input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"> <button class="button"><?php esc_html_e( 'Send test', 'kingdom-church-suite' ); ?></button>
			</form>
		<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a settings field.
	 *
	 * @param string $key   Key.
	 * @param array  $field Field.
	 */
	private static function render_field( $key, $field ) {
		$settings = get_option( 'kcs_settings', array() );
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : ( $field['default'] ?? '' );
		$name     = 'kcs[' . $key . ']';
		$id       = 'kcs_' . $key;
		switch ( $field['type'] ) {
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $value, '1', false ) . '> ' . esc_html__( 'Yes', 'kingdom-church-suite' ) . '</label>';
				break;
			case 'textarea':
			case 'code':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . ( 'code' === $field['type'] ? 8 : 4 ) . '" class="large-text' . ( 'code' === $field['type'] ? ' code' : '' ) . '">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'editor':
				wp_editor( $value, $id, array( 'textarea_name' => $name, 'textarea_rows' => 8, 'media_buttons' => false ) );
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $field['options'] as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>';
				break;
			case 'color':
				echo '<input type="text" class="kcs-color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
				break;
			case 'password':
				echo '<input type="password" autocomplete="new-password" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"> <button type="button" class="button kcs-reveal" data-target="' . esc_attr( $id ) . '">' . esc_html__( 'Show', 'kingdom-church-suite' ) . '</button>';
				break;
			case 'info':
				break;
			default:
				echo '<input type="' . esc_attr( in_array( $field['type'], array( 'email', 'url', 'number' ), true ) ? $field['type'] : 'text' ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( 'number' === $field['type'] ? ' step="any"' : '' ) . '>';
		}
		if ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . wp_kses_post( $field['desc'] ) . '</p>';
		}
	}

	/**
	 * API quick reference.
	 */
	private static function api_docs() {
		$base = rest_url( 'kcs/v1' );
		?>
		<div class="kcs-panel">
			<h2><?php esc_html_e( 'REST API quick reference', 'kingdom-church-suite' ); ?></h2>
			<p><?php esc_html_e( 'Base URL', 'kingdom-church-suite' ); ?>: <code><?php echo esc_html( $base ); ?></code> — <?php esc_html_e( 'full documentation is in the plugin README.', 'kingdom-church-suite' ); ?></p>
			<pre class="kcs-code">curl -H "X-KCS-API-Key: YOUR_KEY" <?php echo esc_html( $base ); ?>/partners?status=active

curl -X POST -H "X-KCS-API-Key: YOUR_KEY" -H "Content-Type: application/json" \
  -d '{"audience":"partners_active","subject":"Partners meeting","message":"&lt;p&gt;Dear {first_name}, …&lt;/p&gt;"}' \
  <?php echo esc_html( $base ); ?>/notifications/email</pre>
			<p><a class="button" onclick="return confirm('<?php echo esc_js( __( 'Generate a new key? Existing integrations will stop working.', 'kingdom-church-suite' ) ); ?>')" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kcs_regenerate_api_key' ), 'kcs_regenerate_api_key' ) ); ?>"><?php esc_html_e( 'Generate new API key', 'kingdom-church-suite' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	public static function action_save_settings() {
		self::guard( 'save_settings', 'manage_options' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$tab   = sanitize_key( $_POST['tab'] ?? 'general' );
		$input = isset( $_POST['kcs'] ) && is_array( $_POST['kcs'] ) ? $_POST['kcs'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field in save_tab.
		// phpcs:enable
		KCS_Settings::save_tab( $tab, $input );
		if ( 'general' === $tab ) {
			flush_rewrite_rules();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=kcs-settings&tab=' . $tab . '&kcs_notice=' . rawurlencode( __( 'Settings saved.', 'kingdom-church-suite' ) ) ) );
		exit;
	}

	/**
	 * Test email.
	 */
	public static function action_test_email() {
		self::guard( 'test_email', 'manage_options' );
		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ok = KCS_Email::send( $to, __( 'Test email from Church Suite', 'kingdom-church-suite' ), '<p>' . esc_html__( 'Your email settings are working. 🎉', 'kingdom-church-suite' ) . '</p><p><a class="btn" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Visit website', 'kingdom-church-suite' ) . '</a></p>' );
		wp_safe_redirect( admin_url( 'admin.php?page=kcs-settings&tab=email&kcs_type=' . ( $ok ? 'success' : 'error' ) . '&kcs_notice=' . rawurlencode( $ok ? __( 'Test email sent.', 'kingdom-church-suite' ) : __( 'wp_mail() failed. Configure SMTP.', 'kingdom-church-suite' ) ) ) );
		exit;
	}

	/**
	 * New API key.
	 */
	public static function action_regenerate_api_key() {
		self::guard( 'regenerate_api_key', 'manage_options' );
		kcs_update_option( 'api_key', wp_generate_password( 40, false, false ) );
		wp_safe_redirect( admin_url( 'admin.php?page=kcs-settings&tab=api&kcs_notice=' . rawurlencode( __( 'New API key generated.', 'kingdom-church-suite' ) ) ) );
		exit;
	}

	/* =====================================================================
	 * CSV export
	 * =================================================================== */

	/**
	 * Export CSV.
	 */
	public static function action_export() {
		self::guard( 'export' );
		global $wpdb;
		$type = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows = array();
		if ( 'partners' === $type ) {
			$rows = $wpdb->get_results( 'SELECT partner_code, first_name, last_name, email, phone, country, city, amount, currency, frequency, status, next_due_date, total_given, created_at FROM ' . kcs_table( 'partners' ) . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'payments' === $type ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$args = array(
				'status'     => sanitize_key( $_GET['status'] ?? '' ),
				'gateway'    => sanitize_key( $_GET['gateway'] ?? '' ),
				'purpose'    => sanitize_key( $_GET['purpose'] ?? '' ),
				'partner_id' => absint( $_GET['partner_id'] ?? 0 ),
				'date_from'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ) ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
				'date_to'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ) ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
				'per_page'   => 100000,
			);
			// phpcs:enable
			foreach ( KCS_Payments::query( $args )['items'] as $p ) {
				$rows[] = array_intersect_key( (array) $p, array_flip( array( 'reference', 'name', 'email', 'phone', 'amount', 'currency', 'gateway', 'gateway_txn_id', 'purpose', 'status', 'created_at', 'paid_at' ) ) );
			}
		} elseif ( 'subscribers' === $type ) {
			$rows = $wpdb->get_results( 'SELECT email, name, status, source, created_at, confirmed_at FROM ' . kcs_table( 'subscribers' ) . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( $rows ) {
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				// Neutralise spreadsheet formula injection.
				fputcsv(
					$out,
					array_map(
						function ( $v ) {
							return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) && ! preg_match( '/^[+\-]?[\d\s().-]+$/', $v ) ? "'" . $v : $v;
						},
						$row
					)
				);
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
