<?php
// Test only: fake Paystack and Stripe API responses (the sandbox has no outbound access).
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$m = get_site_option( 'ks_test_mock', array() );
	if ( false !== strpos( $url, 'api.paystack.co/transaction/initialize' ) ) {
		$b = json_decode( $args['body'], true );
		update_site_option( 'ks_test_last_paystack', $b );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => true, 'data' => array( 'authorization_url' => 'https://checkout.paystack.com/fake' . $b['reference'] ) ) ) );
	}
	if ( false !== strpos( $url, 'api.paystack.co/transaction/verify/' ) ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => true, 'data' => array( 'status' => 'success', 'amount' => $m['amount'], 'currency' => $m['currency'], 'id' => 991 ) ) ) );
	}
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions' ) && 'POST' === $args['method'] ) {
		update_site_option( 'ks_test_last_stripe', $args['body'] );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123' ) ) );
	}
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions/' ) ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'id' => 'cs_test_123', 'payment_status' => 'paid', 'client_reference_id' => $m['ref'], 'amount_total' => $m['amount'], 'currency' => strtolower( $m['currency'] ), 'payment_intent' => 'pi_123' ) ) );
	}
	return $pre;
}, 10, 3 );
