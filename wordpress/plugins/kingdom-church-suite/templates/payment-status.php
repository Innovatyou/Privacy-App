<?php
/**
 * Payment result page.
 *
 * @package KingdomChurchSuite
 * @var object|null $payment Payment.
 * @var bool        $offline Offline (bank transfer).
 */

defined( 'ABSPATH' ) || exit;

if ( ! $payment ) {
	echo '<div class="kcs-result"><h2>' . esc_html__( 'Thank you!', 'kingdom-church-suite' ) . '</h2><p>' . esc_html__( 'We could not find that payment reference.', 'kingdom-church-suite' ) . '</p></div>';
	return;
}

$gateway = KCS_Payments::gateway( $payment->gateway );
$amount  = kcs_format_money( $payment->amount, $payment->currency );
$back    = $payment->partner_id ? kcs_page_url( 'partner_dashboard' ) : home_url( '/' );
?>
<div class="kcs-result kcs-result--<?php echo esc_attr( $payment->status ); ?>" data-kcs-payment-status="<?php echo esc_attr( $payment->reference ); ?>" data-status="<?php echo esc_attr( $payment->status ); ?>">
	<?php if ( 'completed' === $payment->status ) : ?>
		<div class="kcs-result__icon">✓</div>
		<h2><?php esc_html_e( 'Thank you for your generosity!', 'kingdom-church-suite' ); ?></h2>
		<p><?php /* translators: %s: amount */ printf( esc_html__( 'Your gift of %s was received successfully. A receipt has been sent to your email.', 'kingdom-church-suite' ), '<strong>' . esc_html( $amount ) . '</strong>' ); ?></p>
		<blockquote class="kcs-verse"><p>“<?php esc_html_e( 'Give, and it will be given to you. A good measure, pressed down, shaken together and running over…', 'kingdom-church-suite' ); ?>”</p><cite>Luke 6:38</cite></blockquote>
	<?php elseif ( $offline || 'bank_transfer' === $payment->gateway ) : ?>
		<div class="kcs-result__icon">🏦</div>
		<h2><?php esc_html_e( 'Complete your transfer', 'kingdom-church-suite' ); ?></h2>
		<p><?php /* translators: 1: amount, 2: reference */ printf( esc_html__( 'Please transfer %1$s using the reference %2$s. We will confirm it and email your receipt.', 'kingdom-church-suite' ), '<strong>' . esc_html( $amount ) . '</strong>', '<code>' . esc_html( $payment->reference ) . '</code>' ); ?></p>
		<div class="kcs-bank-details"><?php echo wp_kses_post( $gateway ? $gateway->opt( 'instructions' ) : '' ); ?></div>
	<?php elseif ( in_array( $payment->status, array( 'pending', 'review' ), true ) ) : ?>
		<div class="kcs-result__icon kcs-spin">⏳</div>
		<h2><?php esc_html_e( 'Confirming your payment…', 'kingdom-church-suite' ); ?></h2>
		<p><?php esc_html_e( 'This usually takes a few seconds. This page will update automatically.', 'kingdom-church-suite' ); ?></p>
	<?php else : ?>
		<div class="kcs-result__icon">!</div>
		<h2><?php esc_html_e( 'Payment not completed', 'kingdom-church-suite' ); ?></h2>
		<p><?php esc_html_e( 'Your payment was cancelled or could not be completed. No money was taken. Please try again.', 'kingdom-church-suite' ); ?></p>
	<?php endif; ?>
	<p class="kcs-result__ref"><?php esc_html_e( 'Reference', 'kingdom-church-suite' ); ?>: <code><?php echo esc_html( $payment->reference ); ?></code></p>
	<p><a class="kcs-btn" href="<?php echo esc_url( $back ); ?>"><?php echo $payment->partner_id ? esc_html__( 'Back to my dashboard', 'kingdom-church-suite' ) : esc_html__( 'Back to homepage', 'kingdom-church-suite' ); ?></a></p>
</div>
