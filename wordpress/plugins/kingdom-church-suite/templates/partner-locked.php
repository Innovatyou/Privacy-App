<?php
/**
 * Locked partner dashboard: shown until the partner renews.
 *
 * @package KingdomChurchSuite
 * @var object      $partner Partner.
 * @var object|null $plan    Plan.
 * @var string      $lock    pending|overdue|paused|inactive.
 */

defined( 'ABSPATH' ) || exit;

$titles = array(
	'pending'  => __( 'Activate your partnership', 'kingdom-church-suite' ),
	'overdue'  => __( 'Your partnership needs renewing', 'kingdom-church-suite' ),
	'paused'   => __( 'Your partnership is paused', 'kingdom-church-suite' ),
	'inactive' => __( 'Your partnership is inactive', 'kingdom-church-suite' ),
);
$texts  = array(
	'pending'  => __( 'Welcome! Give your first partnership seed to unlock your dashboard, your partner benefits and chat with your pastors.', 'kingdom-church-suite' ),
	/* translators: %s: due date */
	'overdue'  => sprintf( __( 'Your seed was due on %s. Your dashboard, benefits and pastor chat are locked until you renew. It unlocks as soon as your payment is confirmed.', 'kingdom-church-suite' ), $partner->next_due_date ? date_i18n( get_option( 'date_format' ), strtotime( $partner->next_due_date ) ) : '—' ),
	'paused'   => __( 'Give a seed to resume your partnership and unlock your dashboard.', 'kingdom-church-suite' ),
	'inactive' => __( 'Renew your partnership to unlock your dashboard again.', 'kingdom-church-suite' ),
);
?>
<div class="kcs-locked">
	<div class="kcs-locked__card">
		<div class="kcs-locked__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
		<h2><?php echo esc_html( $titles[ $lock ] ?? $titles['inactive'] ); ?></h2>
		<p><?php echo esc_html( $texts[ $lock ] ?? $texts['inactive'] ); ?></p>
		<ul class="kcs-locked__meta">
			<li><span><?php esc_html_e( 'Partner', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( trim( $partner->first_name . ' ' . $partner->last_name ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $plan ? $plan->name : __( 'Custom', 'kingdom-church-suite' ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Amount due', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( kcs_format_money( $partner->amount, $partner->currency ) ); ?></strong></li>
		</ul>
	</div>
	<?php
	echo kcs_get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		'payment-form',
		array(
			'context'  => 'partnership',
			'purpose'  => 'partnership',
			'amounts'  => array(),
			'title'    => 'pending' === $lock ? __( 'Give your first seed', 'kingdom-church-suite' ) : __( 'Renew my partnership', 'kingdom-church-suite' ),
			'amount'   => (float) $partner->amount,
			'currency' => $partner->currency,
			'name'     => '',
			'email'    => '',
			'partner'  => $partner,
		)
	);
	?>
	<p class="kcs-muted kcs-locked__foot"><?php esc_html_e( 'Paid by bank transfer? Your dashboard unlocks once the church confirms it.', 'kingdom-church-suite' ); ?> · <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'kingdom-church-suite' ); ?></a></p>
</div>
