<?php
/**
 * Transactional email, templates, queue and outgoing webhooks.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email.
 */
class KCS_Email {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'kcs_process_email_queue', array( __CLASS__, 'process_queue' ) );
	}

	/**
	 * Editable templates and their defaults.
	 *
	 * @return array
	 */
	public static function templates() {
		return apply_filters(
			'kcs_email_templates',
			array(
				'partner_welcome'   => array(
					'label'   => __( 'Partner welcome', 'kingdom-church-suite' ),
					'subject' => 'Welcome to the {church_name} partnership family, {first_name}!',
					'body'    => "<p>Dear {first_name},</p><p>Thank you for choosing to partner with {church_name}. Your partner ID is <strong>{partner_code}</strong>.</p><p>Plan: <strong>{plan_name}</strong> — {amount} ({frequency}).</p><p>You can manage your partnership, give and view your history anytime from your partner dashboard.</p><p><a class=\"btn\" href=\"{dashboard_url}\">Open my dashboard</a></p><p>God bless you richly.</p>",
					'vars'    => array( '{first_name}', '{partner_code}', '{plan_name}', '{amount}', '{frequency}', '{dashboard_url}', '{church_name}' ),
				),
				'payment_receipt'   => array(
					'label'   => __( 'Payment receipt', 'kingdom-church-suite' ),
					'subject' => 'Receipt {reference} — thank you for giving',
					'body'    => "<p>Dear {name},</p><p>We have received your {purpose} of <strong>{amount}</strong>. Thank you for your generosity!</p><table class=\"details\"><tr><td>Reference</td><td>{reference}</td></tr><tr><td>Date</td><td>{date}</td></tr><tr><td>Method</td><td>{gateway}</td></tr></table><p>\"Each of you should give what you have decided in your heart to give… for God loves a cheerful giver.\" — 2 Corinthians 9:7</p>",
					'vars'    => array( '{name}', '{amount}', '{purpose}', '{reference}', '{date}', '{gateway}', '{church_name}' ),
				),
				'payment_reminder'  => array(
					'label'   => __( 'Partnership reminder', 'kingdom-church-suite' ),
					'subject' => 'Your partnership seed is due on {due_date}',
					'body'    => "<p>Dear {first_name},</p><p>This is a friendly reminder that your {frequency} partnership of <strong>{amount}</strong> is due on <strong>{due_date}</strong>.</p><p><a class=\"btn\" href=\"{pay_url}\">Give now</a></p><p>Thank you for standing with us.</p>",
					'vars'    => array( '{first_name}', '{amount}', '{frequency}', '{due_date}', '{pay_url}', '{church_name}' ),
				),
				'plan_changed'      => array(
					'label'   => __( 'Partnership plan changed', 'kingdom-church-suite' ),
					'subject' => 'Your partnership is now {plan_name}',
					'body'    => "<p>Dear {first_name},</p><p>Thank you! Your partnership has been {change} from <strong>{old_plan}</strong> to <strong>{plan_name}</strong> — {amount} ({frequency}).</p><p>Your new benefits are listed on your dashboard.</p><p><a class=\"btn\" href=\"{pay_url}\">Give your first seed on this plan</a></p>",
					'vars'    => array( '{first_name}', '{old_plan}', '{plan_name}', '{change}', '{amount}', '{frequency}', '{pay_url}', '{church_name}' ),
				),
				'admin_new_partner' => array(
					'label'   => __( 'Admin: new partner', 'kingdom-church-suite' ),
					'subject' => 'New partner: {name} ({plan_name})',
					'body'    => '<p>A new partner has registered.</p><p><strong>{name}</strong><br>{email}<br>{phone}<br>Plan: {plan_name} — {amount} ({frequency})</p><p><a class="btn" href="{admin_url}">View partner</a></p>',
					'vars'    => array( '{name}', '{email}', '{phone}', '{plan_name}', '{amount}', '{frequency}', '{admin_url}' ),
				),
				'admin_new_payment' => array(
					'label'   => __( 'Admin: new payment', 'kingdom-church-suite' ),
					'subject' => 'New {purpose}: {amount} from {name}',
					'body'    => '<p><strong>{name}</strong> ({email}) gave <strong>{amount}</strong> via {gateway}.</p><p>Purpose: {purpose}<br>Reference: {reference}</p><p><a class="btn" href="{admin_url}">View payments</a></p>',
					'vars'    => array( '{name}', '{email}', '{amount}', '{gateway}', '{purpose}', '{reference}', '{admin_url}' ),
				),
				'subscribe_confirm' => array(
					'label'   => __( 'Newsletter confirmation', 'kingdom-church-suite' ),
					'subject' => 'Please confirm your subscription to {church_name}',
					'body'    => '<p>Hi {name},</p><p>Please confirm you want to receive news, devotionals and event updates from {church_name}.</p><p><a class="btn" href="{confirm_url}">Yes, subscribe me</a></p><p>If you did not request this, simply ignore this email.</p>',
					'vars'    => array( '{name}', '{confirm_url}', '{church_name}' ),
				),
				'subscribe_welcome' => array(
					'label'   => __( 'Newsletter welcome', 'kingdom-church-suite' ),
					'subject' => "You're subscribed to {church_name}",
					'body'    => '<p>Hi {name},</p><p>Welcome! You will now receive our latest sermons, events and devotionals.</p>',
					'vars'    => array( '{name}', '{church_name}' ),
				),
				'prayer_received'   => array(
					'label'   => __( 'Prayer request received', 'kingdom-church-suite' ),
					'subject' => 'We are praying with you, {name}',
					'body'    => '<p>Dear {name},</p><p>We have received your prayer request and our prayer team is standing in agreement with you.</p><p>"The prayer of a righteous person is powerful and effective." — James 5:16</p>',
					'vars'    => array( '{name}', '{church_name}' ),
				),
			)
		);
	}

	/**
	 * Build subject/body for a template with variables replaced.
	 *
	 * @param string $key  Template key.
	 * @param array  $vars Variables without braces.
	 * @return array{subject:string,body:string}
	 */
	public static function render_template( $key, $vars = array() ) {
		$templates = self::templates();
		$subject   = kcs_get_option( 'tpl_' . $key . '_subject', isset( $templates[ $key ] ) ? $templates[ $key ]['subject'] : '' );
		$body      = kcs_get_option( 'tpl_' . $key . '_body', isset( $templates[ $key ] ) ? $templates[ $key ]['body'] : '' );
		$vars      = array_merge(
			array(
				'church_name' => kcs_get_option( 'church_name', get_bloginfo( 'name' ) ),
				'site_url'    => home_url( '/' ),
			),
			$vars
		);
		return array(
			'subject' => self::replace( $subject, $vars, false ),
			'body'    => self::replace( $body, $vars, true ),
		);
	}

	/**
	 * Replace {placeholders}.
	 *
	 * @param string $text   Text.
	 * @param array  $vars   Variables.
	 * @param bool   $escape Escape values for HTML.
	 * @return string
	 */
	public static function replace( $text, $vars, $escape = true ) {
		$map = array();
		foreach ( $vars as $k => $v ) {
			$v                 = (string) $v;
			$map[ '{' . $k . '}' ] = $escape ? ( preg_match( '~^https?://~', $v ) ? esc_url( $v ) : esc_html( $v ) ) : wp_strip_all_tags( $v );
		}
		return strtr( $text, $map );
	}

	/**
	 * Send a templated email immediately.
	 *
	 * @param string $to   Recipient.
	 * @param string $key  Template key.
	 * @param array  $vars Variables.
	 * @return bool
	 */
	public static function send_template( $to, $key, $vars = array() ) {
		$t = self::render_template( $key, $vars );
		return self::send( $to, $t['subject'], $t['body'] );
	}

	/**
	 * Send an HTML email wrapped in the branded layout.
	 *
	 * @param string|array $to      Recipient(s).
	 * @param string       $subject Subject.
	 * @param string       $body    Inner HTML.
	 * @param array        $args    Extra: unsubscribe_url, preheader.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $args = array() ) {
		$html    = self::wrap( $subject, $body, $args );
		$from    = kcs_get_option( 'from_email', get_option( 'admin_email' ) );
		$name    = kcs_get_option( 'from_name', kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', wp_specialchars_decode( $name, ENT_QUOTES ), $from ),
		);
		if ( ! empty( $args['unsubscribe_url'] ) ) {
			$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $args['unsubscribe_url'] ) . '>';
		}
		return wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Branded HTML layout.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 * @param array  $args    Args.
	 * @return string
	 */
	public static function wrap( $subject, $body, $args = array() ) {
		$color  = kcs_get_option( 'email_color', '#5b2a86' );
		$church = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		$logo   = kcs_get_option( 'church_logo' );
		$footer = kcs_get_option( 'email_footer', $church . ' · ' . kcs_get_option( 'church_address' ) );
		$pre    = isset( $args['preheader'] ) ? $args['preheader'] : '';
		$unsub  = isset( $args['unsubscribe_url'] ) ? $args['unsubscribe_url'] : '';

		ob_start();
		?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html( $subject ); ?></title>
<style>
body{margin:0;background:#f4f2f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#2d2a32;line-height:1.6}
.wrap{max-width:600px;margin:0 auto;padding:24px 12px}
.card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.head{background:<?php echo esc_attr( $color ); ?>;color:#fff;padding:24px;text-align:center;font-size:20px;font-weight:700}
.head img{max-height:56px}
.body{padding:28px}
.btn{display:inline-block;background:<?php echo esc_attr( $color ); ?>;color:#fff!important;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600}
.details{width:100%;border-collapse:collapse;margin:16px 0}.details td{padding:8px;border-bottom:1px solid #eee}
.foot{text-align:center;color:#8a8590;font-size:12px;padding:18px}
.foot a{color:#8a8590}
</style></head>
<body><span style="display:none;max-height:0;overflow:hidden"><?php echo esc_html( $pre ); ?></span>
<div class="wrap"><div class="card">
<div class="head"><?php echo $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $church ) . '">' : esc_html( $church ); ?></div>
<div class="body"><?php echo wp_kses_post( $body ); ?></div>
</div>
<div class="foot"><?php echo esc_html( $footer ); ?>
		<?php if ( $unsub ) : ?>
<br><a href="<?php echo esc_url( $unsub ); ?>"><?php esc_html_e( 'Unsubscribe', 'kingdom-church-suite' ); ?></a>
<?php endif; ?>
</div></div></body></html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Admin notification recipients.
	 *
	 * @return array
	 */
	public static function admin_recipients() {
		$raw  = kcs_get_option( 'admin_notify_email', get_option( 'admin_email' ) );
		$list = array_filter( array_map( 'sanitize_email', explode( ',', $raw ) ) );
		return $list ? $list : array( get_option( 'admin_email' ) );
	}

	/**
	 * Add a message to the send queue (already fully rendered HTML).
	 *
	 * @param string $email       Recipient.
	 * @param string $subject     Subject.
	 * @param string $html        Full HTML.
	 * @param int    $campaign_id Campaign.
	 */
	public static function queue( $email, $subject, $html, $campaign_id = 0 ) {
		global $wpdb;
		$wpdb->insert(
			kcs_table( 'email_queue' ),
			array(
				'campaign_id' => (int) $campaign_id,
				'email'       => $email,
				'subject'     => $subject,
				'body'        => $html,
				'status'      => 'queued',
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Cron: send a batch of queued emails.
	 */
	public static function process_queue() {
		global $wpdb;
		$table = kcs_table( 'email_queue' );
		$batch = max( 1, (int) kcs_get_option( 'batch_size', 50 ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'queued' AND attempts < 3 ORDER BY id ASC LIMIT %d", $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$from    = kcs_get_option( 'from_email', get_option( 'admin_email' ) );
		$name    = kcs_get_option( 'from_name', kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) );
		$headers = array( 'Content-Type: text/html; charset=UTF-8', sprintf( 'From: %s <%s>', wp_specialchars_decode( $name, ENT_QUOTES ), $from ) );

		$touched = array();
		foreach ( $rows as $row ) {
			$ok = wp_mail( $row->email, $row->subject, $row->body, $headers );
			$wpdb->update(
				$table,
				array(
					'status'   => $ok ? 'sent' : ( $row->attempts >= 2 ? 'failed' : 'queued' ),
					'attempts' => $row->attempts + 1,
					'sent_at'  => $ok ? current_time( 'mysql' ) : null,
					'error'    => $ok ? null : 'wp_mail returned false',
				),
				array( 'id' => $row->id )
			);
			if ( $row->campaign_id ) {
				$touched[ $row->campaign_id ] = true;
			}
		}
		foreach ( array_keys( $touched ) as $cid ) {
			KCS_Newsletter::refresh_campaign_stats( $cid );
		}
	}

	/**
	 * Fire a signed outgoing webhook (non-blocking).
	 *
	 * @param string $event Event name.
	 * @param array  $data  Payload.
	 */
	public static function webhook( $event, $data ) {
		$url = kcs_get_option( 'webhook_out_url' );
		if ( ! $url ) {
			return;
		}
		$body = wp_json_encode(
			array(
				'event'     => $event,
				'site'      => home_url(),
				'timestamp' => time(),
				'data'      => $data,
			)
		);
		wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'    => 'application/json',
					'X-KCS-Event'     => $event,
					'X-KCS-Signature' => hash_hmac( 'sha256', $body, (string) kcs_get_option( 'api_key' ) ),
				),
				'body'     => $body,
			)
		);
	}
}
