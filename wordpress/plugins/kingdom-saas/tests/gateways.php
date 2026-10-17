<?php
// Gateway tests (run with wp eval-file).
ks_set( array( 'paystack_secret' => 'sk_test_abc', 'stripe_secret' => 'sk_test_str', 'stripe_webhook' => 'whsec_test' ) );
$faith = (int) $GLOBALS['wpdb']->get_var( "SELECT blog_id FROM {$GLOBALS['wpdb']->base_prefix}ks_subscriptions WHERE subdomain='faith.churchhub.localhost'" );
$ok = function ( $label, $cond ) { echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n"; };
$hook = function ( $path, $body, $headers ) {
	$res = wp_remote_post( 'http://churchhub.localhost/wp-json/ks/v1/' . $path, array( 'body' => $body, 'headers' => $headers + array( 'Content-Type' => 'application/json' ), 'timeout' => 20 ) );
	return is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res );
};
echo 'gateways (NGN): ' . implode( ', ', array_keys( KS_Billing::gateways( 'NGN' ) ) ) . "\n";

// Paystack: checkout → webhook.
$before = KS_Tenants::get( $faith );
$url = KS_Billing::checkout( $faith, 'growth', 'month', 'local', 'paystack' );
$sent = get_site_option( 'ks_test_last_paystack' );
$ok( 'paystack checkout returns hosted URL', is_string( $url ) && 0 === strpos( $url, 'https://checkout.paystack.com/' ) );
$ok( 'paystack amount in kobo + NGN + callback', 4000000 === $sent['amount'] && 'NGN' === $sent['currency'] && false !== strpos( $sent['callback_url'], 'billing/return' ) );
$inv = KS_Billing::invoice( $sent['reference'] );
update_site_option( 'ks_test_mock', array( 'amount' => 4000000, 'currency' => 'NGN' ) );
$body = wp_json_encode( array( 'event' => 'charge.success', 'data' => array( 'reference' => $inv->reference ) ) );
$ok( 'paystack webhook bad signature → 401', 401 === $hook( 'webhooks/paystack', $body, array( 'x-paystack-signature' => 'nope' ) ) );
$ok( 'paystack webhook bad signature leaves invoice pending', 'pending' === KS_Billing::invoice( $inv->reference )->status );
$ok( 'paystack webhook good signature → 200', 200 === $hook( 'webhooks/paystack', $body, array( 'x-paystack-signature' => hash_hmac( 'sha512', $body, 'sk_test_abc' ) ) ) );
$after = KS_Tenants::get( $faith );
$ok( 'invoice paid, plan → growth, region local', 'paid' === KS_Billing::invoice( $inv->reference )->status && 'growth' === $after->plan && 'local' === $after->region );
$ok( 'period extended by one month from previous end', strtotime( $after->current_period_end ) - strtotime( $before->current_period_end ) > 27 * DAY_IN_SECONDS );
$end = $after->current_period_end;
$hook( 'webhooks/paystack', $body, array( 'x-paystack-signature' => hash_hmac( 'sha512', $body, 'sk_test_abc' ) ) );
$ok( 'duplicate webhook does not extend twice', KS_Tenants::get( $faith )->current_period_end === $end );

// Paystack amount tampering.
$url = KS_Billing::checkout( $faith, 'ministry', 'year', 'local', 'paystack' );
$inv = KS_Billing::invoice( get_site_option( 'ks_test_last_paystack' )['reference'] );
update_site_option( 'ks_test_mock', array( 'amount' => 100, 'currency' => 'NGN' ) );
$ok( 'underpaid amount is rejected', false === KS_Billing::verify( $inv ) && 'failed' === KS_Billing::invoice( $inv->reference )->status && 'growth' === KS_Tenants::get( $faith )->plan );

// Return URL (payer comes back from Paystack).
$url = KS_Billing::checkout( $faith, 'growth', 'month', 'local', 'paystack' );
$inv = KS_Billing::invoice( get_site_option( 'ks_test_last_paystack' )['reference'] );
update_site_option( 'ks_test_mock', array( 'amount' => 4000000, 'currency' => 'NGN' ) );
$res = wp_remote_get( 'http://churchhub.localhost/wp-json/ks/v1/billing/return?reference=' . $inv->reference, array( 'redirection' => 0 ) );
$loc = wp_remote_retrieve_header( $res, 'location' );
$ok( 'return URL verifies and redirects to church billing with ks_paid=1', false !== strpos( $loc, 'faith.churchhub.localhost' ) && false !== strpos( $loc, 'ks_paid=1' ) && 'paid' === KS_Billing::invoice( $inv->reference )->status );

// Stripe: checkout → signed webhook.
$url = KS_Billing::checkout( $faith, 'growth', 'month', 'international', 'stripe' );
$sent = get_site_option( 'ks_test_last_stripe' );
$ok( 'stripe checkout returns hosted URL', 'https://checkout.stripe.com/c/pay/cs_test_123' === $url );
$ok( 'stripe line item 4900 usd', '4900' == $sent['line_items[0][price_data][unit_amount]'] && 'usd' === $sent['line_items[0][price_data][currency]'] );
$inv = KS_Billing::invoice( $sent['client_reference_id'] );
update_site_option( 'ks_test_mock', array( 'amount' => 4900, 'currency' => 'USD', 'ref' => $inv->reference ) );
$body = wp_json_encode( array( 'type' => 'checkout.session.completed', 'data' => array( 'object' => array( 'id' => 'cs_test_123', 'client_reference_id' => $inv->reference ) ) ) );
$t = time();
$ok( 'stripe webhook bad signature → 400', 400 === $hook( 'webhooks/stripe', $body, array( 'stripe-signature' => "t=$t,v1=bad" ) ) );
$ok( 'stripe webhook old timestamp → 400', 400 === $hook( 'webhooks/stripe', $body, array( 'stripe-signature' => 't=' . ( $t - 3600 ) . ',v1=' . hash_hmac( 'sha256', ( $t - 3600 ) . '.' . $body, 'whsec_test' ) ) ) );
$ok( 'stripe webhook good signature → 200', 200 === $hook( 'webhooks/stripe', $body, array( 'stripe-signature' => "t=$t,v1=" . hash_hmac( 'sha256', $t . '.' . $body, 'whsec_test' ) ) ) );
$after = KS_Tenants::get( $faith );
$ok( 'stripe invoice paid, region international', 'paid' === KS_Billing::invoice( $inv->reference )->status && 'international' === $after->region );

// Cancel at period end → cancelled when the period runs out.
KS_Tenants::update( $faith, array( 'cancel_at_period_end' => 1, 'current_period_end' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
KS_Lifecycle::run();
$ok( 'cancelled at period end', 'cancelled' === KS_Tenants::get( $faith )->status );
$url = KS_Billing::checkout( $faith, 'starter', 'month', 'international', 'test' );
KS_Billing::mark_paid( KS_Billing::invoice( wp_parse_args( wp_parse_url( $url, PHP_URL_QUERY ) )['reference'] ), 'T' );
$s = KS_Tenants::get( $faith );
$ok( 'paying again revives cancelled church', 'active' === $s->status && 0 === (int) $s->cancel_at_period_end && strtotime( $s->current_period_end ) > time() + 27 * DAY_IN_SECONDS );

// Reminder email a few days before renewal (sent once).
KS_Tenants::update( $faith, array( 'current_period_end' => gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ), 'last_notice' => '' ) );
$a = KS_Lifecycle::run(); $b = KS_Lifecycle::run();
$ok( 'renewal reminder sent once', 1 === $a['reminded'] && 0 === $b['reminded'] );
ks_set( array( 'paystack_secret' => '', 'stripe_secret' => '', 'stripe_webhook' => '' ) );
