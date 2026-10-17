<?php
/**
 * Payment form (giving & partnership).
 *
 * Override: yourtheme/kingdom-church-suite/payment-form.php
 *
 * @package KingdomChurchSuite
 * @var string      $context  donation|partnership.
 * @var string      $purpose  Preselected purpose.
 * @var array       $amounts  Preset amounts.
 * @var string      $title    Title.
 * @var string      $amount   Default amount.
 * @var string      $currency Default currency.
 * @var string      $name     Name.
 * @var string      $email    Email.
 * @var object|null $partner  Partner.
 */

defined( 'ABSPATH' ) || exit;

$gateways   = KCS_Payments::enabled_gateways();
$currencies = KCS_Payments::allowed_currencies();
$purposes   = kcs_giving_purposes();
$uid        = 'kcs-pay-' . wp_rand( 1000, 99999 );
$regions    = kcs_regions();
$split      = count( $regions ) > 1 && 'partnership' !== $context;
$region     = kcs_currency_region( $currency );
if ( $split && empty( $amounts ) ) {
	$amounts = array();
}

if ( ! $gateways ) {
	echo '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'Online giving is not available yet. Please check back soon.', 'kingdom-church-suite' ) . '</div>';
	if ( current_user_can( 'manage_kcs' ) ) {
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=kcs-settings&tab=payments' ) ) . '">' . esc_html__( 'Admin: enable a payment gateway →', 'kingdom-church-suite' ) . '</a></p>';
	}
	return;
}
?>
<form class="kcs-form kcs-pay-form" data-kcs-payment-form id="<?php echo esc_attr( $uid ); ?>"<?php echo $split ? ' data-kcs-region-scope data-region="' . esc_attr( $region ) . '"' : ''; ?>>
	<?php if ( $title ) : ?><h3 class="kcs-form__title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>

	<?php if ( $split ) : ?>
		<?php echo kcs_region_toggle( $region ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>

	<?php if ( 'partnership' === $context ) : ?>
		<input type="hidden" name="purpose" value="partnership">
		<input type="hidden" name="as_partner" value="1">
	<?php elseif ( $purpose && isset( $purposes[ $purpose ] ) ) : ?>
		<input type="hidden" name="purpose" value="<?php echo esc_attr( $purpose ); ?>">
	<?php else : ?>
		<label><?php esc_html_e( 'I am giving towards', 'kingdom-church-suite' ); ?>
			<select name="purpose">
				<?php foreach ( $purposes as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	<?php endif; ?>

	<div class="kcs-amount-row">
		<?php if ( $split ) : ?>
			<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>" data-kcs-currency>
			<span class="kcs-currency kcs-currency--fixed" aria-hidden="true"><?php foreach ( $regions as $rk => $r ) : ?><b class="kcs-in-<?php echo esc_attr( $rk ); ?>"><?php echo esc_html( kcs_currency_symbol( $r['currency'] ) . ' ' . $r['currency'] ); ?></b><?php endforeach; ?></span>
		<?php else : ?>
		<label class="kcs-currency"><?php esc_html_e( 'Currency', 'kingdom-church-suite' ); ?>
			<select name="currency" data-kcs-currency>
				<?php foreach ( $currencies as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $currency ); ?>><?php echo esc_html( $code ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php endif; ?>
		<label class="kcs-amount"><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?>
			<input type="number" name="amount" min="<?php echo esc_attr( kcs_get_option( 'min_amount', 1 ) ); ?>" step="0.01" required value="<?php echo esc_attr( $amount ); ?>" inputmode="decimal">
		</label>
	</div>

	<?php if ( $split ) : ?>
		<?php foreach ( array_keys( $regions ) as $rk ) : ?>
			<div class="kcs-presets kcs-in-<?php echo esc_attr( $rk ); ?>" role="group" aria-label="<?php esc_attr_e( 'Quick amounts', 'kingdom-church-suite' ); ?>">
				<?php foreach ( ( 'international' === $rk && ! empty( $amounts ) ) ? $amounts : kcs_region_amounts( $rk ) as $preset ) : ?>
					<button type="button" class="kcs-preset" data-amount="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( kcs_currency_symbol( $regions[ $rk ]['currency'] ) . number_format_i18n( $preset ) ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	<?php elseif ( ! empty( $amounts ) ) : ?>
		<div class="kcs-presets" role="group" aria-label="<?php esc_attr_e( 'Quick amounts', 'kingdom-church-suite' ); ?>">
			<?php foreach ( $amounts as $preset ) : ?>
				<button type="button" class="kcs-preset" data-amount="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( number_format_i18n( $preset ) ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $partner ) : ?>
		<div class="kcs-grid-2">
			<label><?php esc_html_e( 'Full name', 'kingdom-church-suite' ); ?><input type="text" name="name" required value="<?php echo esc_attr( $name ); ?>" autocomplete="name"></label>
			<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" name="email" required value="<?php echo esc_attr( $email ); ?>" autocomplete="email"></label>
		</div>
		<label><?php esc_html_e( 'Phone (optional)', 'kingdom-church-suite' ); ?><input type="tel" name="phone" autocomplete="tel"></label>
	<?php endif; ?>

	<fieldset class="kcs-gateways">
		<legend><?php esc_html_e( 'Payment method', 'kingdom-church-suite' ); ?></legend>
		<?php $first = true; foreach ( $gateways as $g ) : ?>
			<label class="kcs-gateway" data-currencies="<?php echo esc_attr( implode( ',', $g->currency_list() ) ); ?>">
				<input type="radio" name="gateway" value="<?php echo esc_attr( $g->id ); ?>" <?php checked( $first ); ?>>
				<span class="kcs-gateway__name kcs-gw-<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->display_title() ); ?></span>
			</label>
			<?php $first = false; endforeach; ?>
	</fieldset>

	<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
	<button type="submit" class="kcs-btn kcs-btn--lg kcs-btn--block">
		<?php echo 'partnership' === $context ? esc_html__( 'Give my partnership seed', 'kingdom-church-suite' ) : esc_html__( 'Give now', 'kingdom-church-suite' ); ?>
	</button>
	<p class="kcs-secure">🔒 <?php esc_html_e( 'Secure payment. You will be redirected to complete your gift.', 'kingdom-church-suite' ); ?></p>
	<div class="kcs-form-msg" aria-live="polite"></div>
</form>
