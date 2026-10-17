<?php
/**
 * Subscription billing: checkout, verification, webhooks and invoices.
 *
 * Gateways: Paystack and Stripe (hosted checkout, verified server-side), bank transfer
 * (confirmed by the platform owner) and a test checkout when test mode is on.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Billing.
 */
class KS_Billing {

	/**
	 * Gateways available to churches.
	 *
	 * @param string $currency Currency.
	 * @return array id => label.
	 */
	public static function gateways( $currency = '' ) {
		$out = array();
		if ( ks_get( 'paystack_secret' ) && ( ! $currency || in_array( $currency, array( 'NGN', 'GHS', 'ZAR', 'KES', 'USD' ), true ) ) ) {
			$out['paystack'] = __( 'Paystack — card, bank transfer, USSD', 'kingdom-saas' );
		}
		if ( ks_get( 'stripe_secret' ) ) {
			$out['stripe'] = __( 'Stripe — card, Apple Pay, Google Pay', 'kingdom-saas' );
		}
		if ( ks_get( 'bank_details' ) ) {
			$out['bank'] = __( 'Bank transfer', 'kingdom-saas' );
		}
		if ( ks_test_mode() ) {
			$out['test'] = __( 'Test payment (test mode)', 'kingdom-saas' );
		}
		return $out;
	}

	/**
	 * Invoice by reference.
	 *
	 * @param string $ref Reference.
	 * @return object|null
	 */
	public static function invoice( $ref ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'invoices' ) . ' WHERE reference = %s', $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Invoices.
	 *
	 * @param int $blog_id Site (0 = all).
	 * @param int $limit   Limit.
	 * @return array
	 */
	public static function invoices( $blog_id = 0, $limit = 50 ) {
		global $wpdb;
		$t = ks_table( 'invoices' );
		return $blog_id
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE blog_id = %d ORDER BY id DESC LIMIT %d", $blog_id, $limit ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Start a checkout for a plan.
	 *
	 * @param int    $blog_id  Site.
	 * @param string $plan     Plan.
	 * @param string $interval month|year.
	 * @param string $region   international|local.
	 * @param string $gateway  Gateway.
	 * @return string|WP_Error URL to send the payer to.
	 */
	public static function checkout( $blog_id, $plan, $interval, $region, $gateway ) {
		$sub = KS_Tenants::get( $blog_id );
		if ( ! $sub || ! KS_Plans::get( $plan ) ) {
			return new WP_Error( 'ks_plan', __( 'Please choose a plan.', 'kingdom-saas' ) );
		}
		$interval = 'year' === $interval ? 'year' : 'month';
		$region   = 'local' === $region ? 'local' : 'international';
		$price    = KS_Plans::price( $plan, $interval, $region );
		if ( ! isset( self::gateways( $price['currency'] )[ $gateway ] ) ) {
			return new WP_Error( 'ks_gateway', __( 'That payment method is not available.', 'kingdom-saas' ) );
		}
		if ( $price['amount'] <= 0 ) {
			return new WP_Error( 'ks_amount', __( 'This plan has no price set.', 'kingdom-saas' ) );
		}
		global $wpdb;
		$ref = 'KSI-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 8, false, false ) );
		$wpdb->insert(
			ks_table( 'invoices' ),
			array(
				'reference'        => $ref,
				'blog_id'          => $blog_id,
				'plan'             => $plan,
				'billing_interval' => $interval,
				'amount'           => $price['amount'],
				'currency'         => $price['currency'],
				'gateway'          => $gateway,
				'status'           => 'pending',
				'created_at'       => current_time( 'mysql', true ),
			)
		);
		$return = add_query_arg( 'reference', $ref, get_rest_url( get_main_site_id(), 'ks/v1/billing/return' ) );
		$plan_r = KS_Plans::get( $plan );
		$label  = sprintf( '%s — %s (%s)', ks_get( 'platform_name' ), $plan_r['name'], 'year' === $interval ? __( 'yearly', 'kingdom-saas' ) : __( 'monthly', 'kingdom-saas' ) );

		switch ( $gateway ) {
			case 'paystack':
				$res = self::api(
					'POST',
					'https://api.paystack.co/transaction/initialize',
					array(
						'email'        => $sub->owner_email,
						'amount'       => (int) round( $price['amount'] * 100 ),
						'currency'     => $price['currency'],
						'reference'    => $ref,
						'callback_url' => $return,
						'metadata'     => array( 'blog_id' => $blog_id, 'plan' => $plan, 'interval' => $interval, 'custom_fields' => array( array( 'display_name' => 'Church', 'variable_name' => 'church', 'value' => $sub->church_name ) ) ),
					),
					array( 'Authorization' => 'Bearer ' . ks_get( 'paystack_secret' ) )
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return empty( $res['data']['authorization_url'] ) ? new WP_Error( 'ks_paystack', $res['message'] ?? 'Paystack error' ) : $res['data']['authorization_url'];

			case 'stripe':
				$res = self::api(
					'POST',
					'https://api.stripe.com/v1/checkout/sessions',
					array(
						'mode'                                => 'payment',
						'client_reference_id'                 => $ref,
						'customer_email'                      => $sub->owner_email,
						'success_url'                         => $return . '&session_id={CHECKOUT_SESSION_ID}',
						'cancel_url'                          => KS_Tenants::admin_url( $blog_id, 'subscription' ),
						'line_items[0][quantity]'             => 1,
						'line_items[0][price_data][currency]' => strtolower( $price['currency'] ),
						'line_items[0][price_data][unit_amount]' => (int) round( $price['amount'] * 100 ),
						'line_items[0][price_data][product_data][name]' => $label,
						'metadata[reference]'                 => $ref,
					),
					array( 'Authorization' => 'Bearer ' . ks_get( 'stripe_secret' ) ),
					'form'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				if ( empty( $res['url'] ) ) {
					return new WP_Error( 'ks_stripe', $res['error']['message'] ?? 'Stripe error' );
				}
				$wpdb->update( ks_table( 'invoices' ), array( 'gateway_ref' => $res['id'] ), array( 'reference' => $ref ) );
				return $res['url'];

			case 'bank':
				return add_query_arg( array( 'view' => 'subscription', 'invoice' => $ref ), KS_Tenants::admin_url( $blog_id ) );

			case 'test':
				return add_query_arg( 'reference', $ref, ks_platform_url( 'billing/test-checkout/' ) );
		}
		return new WP_Error( 'ks_gateway', __( 'That payment method is not available.', 'kingdom-saas' ) );
	}

	/**
	 * Confirm an invoice with its gateway, then apply it.
	 *
	 * @param object $inv    Invoice.
	 * @param array  $params Return parameters.
	 * @return bool Paid.
	 */
	public static function verify( $inv, $params = array() ) {
		if ( 'paid' === $inv->status ) {
			return true;
		}
		$ok     = false;
		$amount = 0;
		$cur    = '';
		$gref   = '';
		if ( 'paystack' === $inv->gateway ) {
			$res = self::api( 'GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode( $inv->reference ), array(), array( 'Authorization' => 'Bearer ' . ks_get( 'paystack_secret' ) ) );
			if ( ! is_wp_error( $res ) && 'success' === ( $res['data']['status'] ?? '' ) ) {
				$ok     = true;
				$amount = ( (int) $res['data']['amount'] ) / 100;
				$cur    = strtoupper( (string) $res['data']['currency'] );
				$gref   = (string) ( $res['data']['id'] ?? '' );
			}
		} elseif ( 'stripe' === $inv->gateway ) {
			$sid = $inv->gateway_ref ? $inv->gateway_ref : sanitize_text_field( $params['session_id'] ?? '' );
			$res = $sid ? self::api( 'GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( $sid ), array(), array( 'Authorization' => 'Bearer ' . ks_get( 'stripe_secret' ) ) ) : new WP_Error( 'ks_stripe', 'No session' );
			if ( ! is_wp_error( $res ) && 'paid' === ( $res['payment_status'] ?? '' ) && $inv->reference === ( $res['client_reference_id'] ?? '' ) ) {
				$ok     = true;
				$amount = ( (int) $res['amount_total'] ) / 100;
				$cur    = strtoupper( (string) $res['currency'] );
				$gref   = (string) ( $res['payment_intent'] ?? $sid );
			}
		}
		if ( ! $ok ) {
			return false;
		}
		if ( $cur !== strtoupper( $inv->currency ) || $amount + 0.01 < (float) $inv->amount ) {
			self::mark_failed( $inv, sprintf( 'Amount mismatch: %s %s', $amount, $cur ) );
			return false;
		}
		return self::mark_paid( $inv, $gref );
	}

	/**
	 * Mark an invoice paid and extend the subscription (idempotent).
	 *
	 * @param object $inv  Invoice.
	 * @param string $gref Gateway reference.
	 * @return bool
	 */
	public static function mark_paid( $inv, $gref = '' ) {
		global $wpdb;
		// Claim the invoice atomically so a webhook and the return page can't both extend the period.
		$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE ' . ks_table( 'invoices' ) . " SET status = 'paid', paid_at = %s, gateway_ref = IF(%s = '', gateway_ref, %s) WHERE id = %d AND status <> 'paid'", current_time( 'mysql', true ), $gref, $gref, $inv->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $claimed ) {
			return true;
		}
		$sub   = KS_Tenants::get( $inv->blog_id );
		$from  = max( time(), $sub && $sub->current_period_end && 'active' === $sub->status ? strtotime( $sub->current_period_end . ' UTC' ) : 0 );
		$until = gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . ( 'year' === $inv->billing_interval ? 'year' : 'month' ), $from ) );
		$wpdb->update( ks_table( 'invoices' ), array( 'period_end' => $until ), array( 'id' => $inv->id ) );
		KS_Tenants::update(
			$inv->blog_id,
			array(
				'plan'                 => $inv->plan,
				'billing_interval'     => $inv->billing_interval,
				'region'               => 'USD' === strtoupper( $inv->currency ) ? 'international' : 'local',
				'status'               => 'active',
				'current_period_end'   => $until,
				'cancel_at_period_end' => 0,
				'last_notice'          => '',
			)
		);
		KS_Domains::enforce_plan( $inv->blog_id );
		$sub  = KS_Tenants::get( $inv->blog_id );
		$plan = KS_Plans::get( $inv->plan );
		KS_Email::owner(
			$sub,
			/* translators: %s: amount */
			sprintf( __( 'Payment received — %s', 'kingdom-saas' ), ks_money( $inv->amount, $inv->currency ) ),
			/* translators: 1: amount, 2: plan, 3: date */
			'<p>' . esc_html( sprintf( __( 'Thank you! We received %1$s for the %2$s plan. Your subscription is active until %3$s.', 'kingdom-saas' ), ks_money( $inv->amount, $inv->currency ), $plan ? $plan['name'] : $inv->plan, date_i18n( get_option( 'date_format' ), strtotime( $until . ' UTC' ) ) ) ) . '</p><p style="color:#7a7385;font-size:13px">' . esc_html__( 'Reference', 'kingdom-saas' ) . ': ' . esc_html( $inv->reference ) . '</p>',
			array( __( 'View billing', 'kingdom-saas' ), KS_Tenants::admin_url( $inv->blog_id, 'subscription' ) )
		);
		do_action( 'ks_invoice_paid', $inv, $sub );
		return true;
	}

	/**
	 * Mark failed.
	 *
	 * @param object $inv  Invoice.
	 * @param string $note Reason.
	 */
	public static function mark_failed( $inv, $note = '' ) {
		global $wpdb;
		$wpdb->update( ks_table( 'invoices' ), array( 'status' => 'failed', 'gateway_ref' => substr( $note, 0, 190 ) ), array( 'id' => $inv->id, 'status' => 'pending' ) );
	}

	/**
	 * Paystack webhook (charge.success), signed with the secret key.
	 *
	 * @param string $body      Raw body.
	 * @param string $signature x-paystack-signature header.
	 * @return bool
	 */
	public static function paystack_webhook( $body, $signature ) {
		$secret = (string) ks_get( 'paystack_secret' );
		if ( ! $secret || ! hash_equals( hash_hmac( 'sha512', $body, $secret ), (string) $signature ) ) {
			return false;
		}
		$event = json_decode( $body, true );
		if ( 'charge.success' === ( $event['event'] ?? '' ) ) {
			$inv = self::invoice( (string) ( $event['data']['reference'] ?? '' ) );
			if ( $inv ) {
				self::verify( $inv );
			}
		}
		return true;
	}

	/**
	 * Stripe webhook (checkout.session.completed), verified with the signing secret.
	 *
	 * @param string $body   Raw body.
	 * @param string $header Stripe-Signature header.
	 * @return bool
	 */
	public static function stripe_webhook( $body, $header ) {
		$secret = (string) ks_get( 'stripe_webhook' );
		$parts  = array();
		foreach ( explode( ',', (string) $header ) as $kv ) {
			$pair = explode( '=', trim( $kv ), 2 );
			if ( 2 === count( $pair ) ) {
				$parts[ $pair[0] ][] = $pair[1];
			}
		}
		$ts = (int) ( $parts['t'][0] ?? 0 );
		if ( ! $secret || ! $ts || abs( time() - $ts ) > 600 ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $ts . '.' . $body, $secret );
		$valid    = false;
		foreach ( $parts['v1'] ?? array() as $sig ) {
			$valid = $valid || hash_equals( $expected, $sig );
		}
		if ( ! $valid ) {
			return false;
		}
		$event = json_decode( $body, true );
		if ( 'checkout.session.completed' === ( $event['type'] ?? '' ) ) {
			$s   = $event['data']['object'] ?? array();
			$inv = self::invoice( (string) ( $s['client_reference_id'] ?? '' ) );
			if ( $inv ) {
				self::verify( $inv, array( 'session_id' => $s['id'] ?? '' ) );
			}
		}
		return true;
	}

	/**
	 * Call a gateway API.
	 *
	 * @param string $method  GET|POST.
	 * @param string $url     URL.
	 * @param array  $body    Body.
	 * @param array  $headers Headers.
	 * @param string $format  json|form.
	 * @return array|WP_Error
	 */
	private static function api( $method, $url, $body, $headers, $format = 'json' ) {
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => $headers,
		);
		if ( 'POST' === $method ) {
			if ( 'json' === $format ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $body );
			} else {
				$args['body'] = $body;
			}
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : new WP_Error( 'ks_api', __( 'The payment provider did not respond. Please try again.', 'kingdom-saas' ) );
	}
}
