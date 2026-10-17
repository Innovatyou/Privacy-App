<?php
/**
 * Test checkout (test mode only) — simulates the payment page.
 *
 * @package KingdomSaaS
 * @var object $invoice Invoice.
 */

defined( 'ABSPATH' ) || exit;

$sub  = KS_Tenants::get( $invoice->blog_id );
$plan = KS_Plans::get( $invoice->plan );
?>
<section class="ks-band">
	<div class="ks-wrap ks-narrow">
		<div class="ks-card ks-form ks-testpay">
			<span class="ks-test-tag"><?php esc_html_e( 'Test mode — no real money moves', 'kingdom-saas' ); ?></span>
			<h1><?php echo esc_html( ks_money( $invoice->amount, $invoice->currency ) ); ?></h1>
			<p class="ks-lead"><?php echo esc_html( ( $plan['name'] ?? $invoice->plan ) . ' · ' . ( 'year' === $invoice->billing_interval ? __( 'yearly', 'kingdom-saas' ) : __( 'monthly', 'kingdom-saas' ) ) . ' · ' . ( $sub ? $sub->church_name : '' ) ); ?></p>
			<p class="ks-muted"><?php echo esc_html( $invoice->reference ); ?></p>
			<?php if ( 'pending' !== $invoice->status ) : ?>
				<?php /* translators: %s: status */ ?>
				<div class="ks-alert"><?php printf( esc_html__( 'This invoice is already %s.', 'kingdom-saas' ), esc_html( $invoice->status ) ); ?></div>
			<?php else : ?>
				<form method="post" class="ks-testpay__actions">
					<input type="hidden" name="ks_do" value="test_pay"><input type="hidden" name="reference" value="<?php echo esc_attr( $invoice->reference ); ?>">
					<?php wp_nonce_field( 'ks_test_pay_' . $invoice->reference, '_ks' ); ?>
					<button class="ks-btn ks-btn--lg" name="result" value="success"><?php esc_html_e( 'Simulate successful payment', 'kingdom-saas' ); ?></button>
					<button class="ks-btn ks-btn--ghost" name="result" value="fail"><?php esc_html_e( 'Simulate declined card', 'kingdom-saas' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</section>
