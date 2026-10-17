<?php
/**
 * Partner portal: login, registration, profile updates and access control.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Portal.
 */
class KCS_Portal {

	/**
	 * Flash messages for the current request.
	 *
	 * @var array
	 */
	private static $messages = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_forms' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	/**
	 * Add a message.
	 *
	 * @param string $text Text.
	 * @param string $type success|error|info.
	 */
	public static function add_message( $text, $type = 'error' ) {
		self::$messages[] = array( 'text' => $text, 'type' => $type );
	}

	/**
	 * Render messages.
	 *
	 * @return string
	 */
	public static function messages() {
		$out = '';
		foreach ( self::$messages as $m ) {
			$out .= '<div class="kcs-alert kcs-alert--' . esc_attr( $m['type'] ) . '" role="alert">' . esc_html( $m['text'] ) . '</div>';
		}
		return $out;
	}

	/**
	 * Is the user only a partner (no editing capabilities)?
	 *
	 * @param WP_User|null $user User.
	 * @return bool
	 */
	private static function is_partner_only( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user && $user->exists() && in_array( 'kcs_partner', (array) $user->roles, true ) && ! user_can( $user, 'edit_posts' );
	}

	/**
	 * Keep partners out of wp-admin.
	 */
	public static function block_admin() {
		if ( wp_doing_ajax() || ! self::is_partner_only() ) {
			return;
		}
		wp_safe_redirect( kcs_page_url( 'partner_dashboard' ) );
		exit;
	}

	/**
	 * Hide the toolbar for partners.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		return self::is_partner_only() ? false : $show;
	}

	/**
	 * Send partners to their dashboard after wp-login.
	 *
	 * @param string           $redirect  Redirect.
	 * @param string           $requested Requested.
	 * @param WP_User|WP_Error $user      User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && self::is_partner_only( $user ) ) {
			return kcs_page_url( 'partner_dashboard' );
		}
		return $redirect;
	}

	/**
	 * Process portal form posts.
	 */
	public static function handle_forms() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['kcs_form'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$form = sanitize_key( $_POST['kcs_form'] );
		if ( ! isset( $_POST['_kcs_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kcs_nonce'] ), 'kcs_' . $form ) ) {
			self::add_message( __( 'Your session expired. Please try again.', 'kingdom-church-suite' ) );
			return;
		}
		if ( ! empty( $_POST['kcs_website'] ) ) { // Honeypot.
			return;
		}
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field downstream.

		switch ( $form ) {
			case 'login':
				self::login( $data );
				break;
			case 'register':
				self::register( $data );
				break;
			case 'profile':
				self::profile( $data );
				break;
			case 'password':
				self::password( $data );
				break;
			case 'change_plan':
				self::change_plan( $data );
				break;
		}
	}

	/**
	 * Login.
	 *
	 * @param array $data Data.
	 */
	private static function login( $data ) {
		if ( ! kcs_rate_limit( 'login', 10, 900 ) ) {
			self::add_message( __( 'Too many attempts. Please wait a few minutes.', 'kingdom-church-suite' ) );
			return;
		}
		$login = sanitize_text_field( $data['log'] ?? '' );
		if ( is_email( $login ) ) {
			$u     = get_user_by( 'email', $login );
			$login = $u ? $u->user_login : $login;
		}
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => (string) ( $data['pwd'] ?? '' ),
				'remember'      => ! empty( $data['rememberme'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			self::add_message( __( 'Invalid email/username or password.', 'kingdom-church-suite' ) );
			return;
		}
		$redirect = ! empty( $data['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( $data['redirect_to'] ), kcs_page_url( 'partner_dashboard' ) ) : kcs_page_url( 'partner_dashboard' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Registration.
	 *
	 * @param array $data Data.
	 */
	private static function register( $data ) {
		if ( ! kcs_rate_limit( 'register', 5, 3600 ) ) {
			self::add_message( __( 'Too many attempts. Please try again later.', 'kingdom-church-suite' ) );
			return;
		}
		$logged_in = is_user_logged_in();
		if ( $logged_in ) {
			if ( kcs_is_partner() ) {
				wp_safe_redirect( kcs_page_url( 'partner_dashboard' ) );
				exit;
			}
			$data['email'] = wp_get_current_user()->user_email;
		} else {
			$pass = (string) ( $data['password'] ?? '' );
			if ( strlen( $pass ) < 8 ) {
				self::add_message( __( 'Password must be at least 8 characters.', 'kingdom-church-suite' ) );
				return;
			}
			if ( $pass !== (string) ( $data['password2'] ?? '' ) ) {
				self::add_message( __( 'Passwords do not match.', 'kingdom-church-suite' ) );
				return;
			}
		}
		if ( empty( $data['first_name'] ) ) {
			self::add_message( __( 'Please enter your first name.', 'kingdom-church-suite' ) );
			return;
		}

		$plan = ! empty( $data['plan_id'] ) ? KCS_Plans::get( (int) $data['plan_id'] ) : null;
		if ( $plan && ! $plan->is_active ) {
			$plan = null;
		}
		$args = array(
			'first_name'   => $data['first_name'] ?? '',
			'last_name'    => $data['last_name'] ?? '',
			'email'        => $data['email'] ?? '',
			'phone'        => $data['phone'] ?? '',
			'country'      => $data['country'] ?? '',
			'city'         => $data['city'] ?? '',
			'plan_id'      => $plan ? $plan->id : 0,
			'amount'       => $data['amount'] ?? '',
			'currency'     => $data['currency'] ?? '',
			'frequency'    => ( ! $plan || $plan->allow_custom_amount ) ? ( $data['frequency'] ?? '' ) : '',
			'password'     => $data['password'] ?? '',
			'email_opt_in' => ! empty( $data['email_opt_in'] ),
		);
		$id   = KCS_Partners::create( $args, true );
		if ( is_wp_error( $id ) ) {
			self::add_message( $id->get_error_message() );
			return;
		}
		if ( ! $logged_in ) {
			$partner = KCS_Partners::get( $id );
			wp_set_current_user( $partner->user_id );
			wp_set_auth_cookie( $partner->user_id, true, is_ssl() );
		}
		wp_safe_redirect( add_query_arg( array( 'welcome' => 1, 'kcs_action' => 'pay' ), kcs_page_url( 'partner_dashboard' ) ) );
		exit;
	}

	/**
	 * Profile update.
	 *
	 * @param array $data Data.
	 */
	private static function profile( $data ) {
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		if ( ! $partner ) {
			return;
		}
		$update = array(
			'first_name'   => $data['first_name'] ?? $partner->first_name,
			'last_name'    => $data['last_name'] ?? $partner->last_name,
			'phone'        => $data['phone'] ?? '',
			'country'      => $data['country'] ?? '',
			'city'         => $data['city'] ?? '',
			'address'      => $data['address'] ?? '',
			'email_opt_in' => ! empty( $data['email_opt_in'] ),
		);
		// Partners may change their commitment (amount/frequency/plan).
		if ( isset( $data['plan_id'] ) ) {
			$plan = KCS_Plans::get( (int) $data['plan_id'] );
			if ( $plan && $plan->is_active ) {
				$update['plan_id'] = $plan->id;
				if ( ! $plan->allow_custom_amount && $plan->amount > 0 ) {
					$update['amount']    = $plan->amount;
					$update['currency']  = $plan->currency;
					$update['frequency'] = $plan->frequency;
				}
			}
		}
		if ( isset( $data['amount'] ) && ( empty( $update['plan_id'] ) || ! isset( $update['amount'] ) ) ) {
			$amount = (float) $data['amount'];
			if ( $amount > 0 ) {
				$update['amount'] = $amount;
			}
			if ( ! empty( $data['frequency'] ) ) {
				$update['frequency'] = $data['frequency'];
			}
			if ( ! empty( $data['currency'] ) ) {
				$update['currency'] = $data['currency'];
			}
		}
		if ( 'paused' === $partner->status && ! empty( $data['resume'] ) ) {
			$update['status'] = 'active';
		} elseif ( ! empty( $data['pause'] ) && in_array( $partner->status, array( 'active', 'overdue' ), true ) ) {
			$update['status'] = 'paused';
		}
		KCS_Partners::update( $partner->id, $update );
		wp_update_user(
			array(
				'ID'         => get_current_user_id(),
				'first_name' => sanitize_text_field( $update['first_name'] ),
				'last_name'  => sanitize_text_field( $update['last_name'] ),
			)
		);
		if ( $update['email_opt_in'] ) {
			KCS_Newsletter::subscribe( $partner->email, trim( $update['first_name'] . ' ' . $update['last_name'] ), 'partner', true );
		} else {
			$sub = KCS_Newsletter::get_by_email( $partner->email );
			if ( $sub ) {
				KCS_Newsletter::unsubscribe( $sub->token );
			}
		}
		self::add_message( __( 'Your profile has been updated.', 'kingdom-church-suite' ), 'success' );
	}

	/**
	 * Upgrade / downgrade / switch plan from the dashboard.
	 *
	 * @param array $data Data.
	 */
	private static function change_plan( $data ) {
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		if ( ! $partner ) {
			return;
		}
		$result = KCS_Partners::change_plan( $partner, absint( $data['plan_id'] ?? 0 ), (float) ( $data['amount'] ?? 0 ), sanitize_key( $data['frequency'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			self::add_message( $result->get_error_message() );
			return;
		}
		wp_safe_redirect( add_query_arg( array( 'kcs_action' => 'pay', 'plan_changed' => $result ), kcs_page_url( 'partner_dashboard' ) ) );
		exit;
	}

	/**
	 * Password change.
	 *
	 * @param array $data Data.
	 */
	private static function password( $data ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return;
		}
		if ( ! wp_check_password( (string) ( $data['current'] ?? '' ), $user->user_pass, $user->ID ) ) {
			self::add_message( __( 'Your current password is incorrect.', 'kingdom-church-suite' ) );
			return;
		}
		$new = (string) ( $data['new'] ?? '' );
		if ( strlen( $new ) < 8 || $new !== (string) ( $data['new2'] ?? '' ) ) {
			self::add_message( __( 'New passwords must match and be at least 8 characters.', 'kingdom-church-suite' ) );
			return;
		}
		wp_set_password( $new, $user->ID );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		self::add_message( __( 'Password changed successfully.', 'kingdom-church-suite' ), 'success' );
	}
}
