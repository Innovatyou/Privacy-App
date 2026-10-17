<?php
/**
 * Partner registration form.
 *
 * @package KingdomChurchSuite
 * @var array   $plans    Plans.
 * @var int     $selected Selected plan.
 * @var WP_User $user     Current user.
 */

defined( 'ABSPATH' ) || exit;

$freqs      = kcs_frequencies();
$currencies = KCS_Payments::allowed_currencies();
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- repopulating the form after a failed, nonce-checked submit.
$old = function ( $key, $default = '' ) {
	return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing
};
if ( ! $selected && $plans ) {
	foreach ( $plans as $p ) {
		if ( $p->is_featured ) {
			$selected = (int) $p->id;
		}
	}
}
$selected = (int) $old( 'plan_id', $selected );
?>
<form method="post" class="kcs-form kcs-register" data-kcs-register>
	<h3 class="kcs-form__title"><?php esc_html_e( 'Become a Partner', 'kingdom-church-suite' ); ?></h3>

	<fieldset class="kcs-plan-picker">
		<legend><?php esc_html_e( '1. Choose your partnership', 'kingdom-church-suite' ); ?></legend>
		<?php foreach ( $plans as $plan ) : ?>
			<label class="kcs-plan-option" data-custom="<?php echo $plan->allow_custom_amount ? '1' : '0'; ?>">
				<input type="radio" name="plan_id" value="<?php echo (int) $plan->id; ?>" <?php checked( $selected, (int) $plan->id ); ?> required>
				<span>
					<strong><?php echo esc_html( $plan->name ); ?></strong>
					<em><?php echo $plan->amount > 0 ? esc_html( kcs_format_money( $plan->amount, $plan->currency ) . ' / ' . strtolower( $freqs[ $plan->frequency ] ?? '' ) ) : esc_html__( 'Choose amount', 'kingdom-church-suite' ); ?></em>
				</span>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<div class="kcs-custom-amount" data-kcs-custom-amount>
		<div class="kcs-grid-3">
			<label><?php esc_html_e( 'Currency', 'kingdom-church-suite' ); ?>
				<select name="currency">
					<?php foreach ( $currencies as $code => $label ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $old( 'currency', kcs_get_option( 'currency', 'USD' ) ), $code ); ?>><?php echo esc_html( $code ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?><input type="number" step="0.01" min="1" name="amount" value="<?php echo esc_attr( $old( 'amount' ) ); ?>"></label>
			<label><?php esc_html_e( 'Frequency', 'kingdom-church-suite' ); ?>
				<select name="frequency">
					<?php foreach ( $freqs as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $old( 'frequency', 'monthly' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
	</div>

	<fieldset>
		<legend><?php esc_html_e( '2. Your details', 'kingdom-church-suite' ); ?></legend>
		<div class="kcs-grid-2">
			<label><?php esc_html_e( 'First name', 'kingdom-church-suite' ); ?><input type="text" name="first_name" required value="<?php echo esc_attr( $old( 'first_name', $user->exists() ? $user->first_name : '' ) ); ?>" autocomplete="given-name"></label>
			<label><?php esc_html_e( 'Last name', 'kingdom-church-suite' ); ?><input type="text" name="last_name" value="<?php echo esc_attr( $old( 'last_name', $user->exists() ? $user->last_name : '' ) ); ?>" autocomplete="family-name"></label>
		</div>
		<div class="kcs-grid-2">
			<?php if ( $user->exists() ) : ?>
				<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled></label>
			<?php else : ?>
				<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" name="email" required value="<?php echo esc_attr( $old( 'email' ) ); ?>" autocomplete="email"></label>
			<?php endif; ?>
			<label><?php esc_html_e( 'Phone / WhatsApp', 'kingdom-church-suite' ); ?><input type="tel" name="phone" value="<?php echo esc_attr( $old( 'phone' ) ); ?>" autocomplete="tel"></label>
		</div>
		<div class="kcs-grid-2">
			<label><?php esc_html_e( 'Country', 'kingdom-church-suite' ); ?><input type="text" name="country" value="<?php echo esc_attr( $old( 'country' ) ); ?>" autocomplete="country-name"></label>
			<label><?php esc_html_e( 'City', 'kingdom-church-suite' ); ?><input type="text" name="city" value="<?php echo esc_attr( $old( 'city' ) ); ?>" autocomplete="address-level2"></label>
		</div>
	</fieldset>

	<?php if ( ! $user->exists() ) : ?>
		<fieldset>
			<legend><?php esc_html_e( '3. Create your partner login', 'kingdom-church-suite' ); ?></legend>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Password', 'kingdom-church-suite' ); ?><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
				<label><?php esc_html_e( 'Confirm password', 'kingdom-church-suite' ); ?><input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
			</div>
		</fieldset>
	<?php endif; ?>

	<label class="kcs-check"><input type="checkbox" name="email_opt_in" value="1" checked> <?php esc_html_e( 'Send me partner updates and the church newsletter', 'kingdom-church-suite' ); ?></label>
	<input type="hidden" name="kcs_form" value="register">
	<input type="text" name="kcs_website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
	<?php wp_nonce_field( 'kcs_register', '_kcs_nonce' ); ?>
	<button type="submit" class="kcs-btn kcs-btn--lg kcs-btn--block"><?php esc_html_e( 'Join the partnership', 'kingdom-church-suite' ); ?></button>
	<?php if ( ! $user->exists() ) : ?>
		<p class="kcs-auth__links"><?php esc_html_e( 'Already have an account?', 'kingdom-church-suite' ); ?> <a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink() ), kcs_page_url( 'partner_login' ) ) ); ?>"><?php esc_html_e( 'Log in first', 'kingdom-church-suite' ); ?></a></p>
	<?php endif; ?>
</form>
