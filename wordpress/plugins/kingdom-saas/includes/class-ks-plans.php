<?php
/**
 * SaaS plans sold to churches.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plans.
 */
class KS_Plans {

	/**
	 * Default plans. Prices are monthly; yearly is 10 × monthly (two months free).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'starter'  => array(
				'name'          => 'Starter',
				'desc'          => 'For new and growing churches.',
				'usd'           => 19,
				'local'         => 15000,
				'max_partners'  => 100,
				'custom_domain' => 0,
				'white_label'   => 0,
				'featured'      => 0,
				'highlights'    => "Church website with 25+ page designs\nPartner portal & dashboards\nOnline giving, tithe & offering\nUp to 100 partners\nyourchurch subdomain",
			),
			'growth'   => array(
				'name'          => 'Growth',
				'desc'          => 'Everything a busy church needs.',
				'usd'           => 49,
				'local'         => 40000,
				'max_partners'  => 1000,
				'custom_domain' => 1,
				'white_label'   => 0,
				'featured'      => 1,
				'highlights'    => "Everything in Starter\nUse your own domain (yourchurch.org)\nUp to 1,000 partners\nNewsletter & partner notifications\nPriority email support",
			),
			'ministry' => array(
				'name'          => 'Ministry',
				'desc'          => 'For large churches and networks.',
				'usd'           => 99,
				'local'         => 80000,
				'max_partners'  => 0,
				'custom_domain' => 1,
				'white_label'   => 1,
				'featured'      => 0,
				'highlights'    => "Everything in Growth\nUnlimited partners\nNo platform branding\nOnboarding call\nWhatsApp support",
			),
		);
	}

	/**
	 * All plans.
	 *
	 * @return array
	 */
	public static function all() {
		$plans = get_site_option( 'ks_plans' );
		return is_array( $plans ) && $plans ? $plans : self::defaults();
	}

	/**
	 * One plan.
	 *
	 * @param string $id Plan ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Price for a plan.
	 *
	 * @param string $id       Plan ID.
	 * @param string $interval month|year.
	 * @param string $region   international|local.
	 * @return array{amount: float, currency: string}
	 */
	public static function price( $id, $interval = 'month', $region = 'international' ) {
		$plan    = self::get( $id );
		$monthly = $plan ? (float) ( 'local' === $region ? $plan['local'] : $plan['usd'] ) : 0;
		return array(
			'amount'   => 'year' === $interval ? $monthly * 10 : $monthly,
			'currency' => ks_region_currency( $region ),
		);
	}

	/**
	 * Feature value for a plan.
	 *
	 * @param string $id      Plan ID.
	 * @param string $feature Feature key.
	 * @return mixed
	 */
	public static function feature( $id, $feature ) {
		$plan = self::get( $id );
		return $plan[ $feature ] ?? null;
	}

	/**
	 * Save plans from a form.
	 *
	 * @param array $rows Rows keyed by plan ID.
	 */
	public static function save( $rows ) {
		$out = array();
		foreach ( self::all() as $id => $plan ) {
			$r          = $rows[ $id ] ?? array();
			$out[ $id ] = array(
				'name'          => sanitize_text_field( $r['name'] ?? $plan['name'] ),
				'desc'          => sanitize_text_field( $r['desc'] ?? $plan['desc'] ),
				'usd'           => max( 0, (float) ( $r['usd'] ?? $plan['usd'] ) ),
				'local'         => max( 0, (float) ( $r['local'] ?? $plan['local'] ) ),
				'max_partners'  => max( 0, (int) ( $r['max_partners'] ?? $plan['max_partners'] ) ),
				'custom_domain' => empty( $r['custom_domain'] ) ? 0 : 1,
				'white_label'   => empty( $r['white_label'] ) ? 0 : 1,
				'featured'      => empty( $r['featured'] ) ? 0 : 1,
				'highlights'    => sanitize_textarea_field( $r['highlights'] ?? $plan['highlights'] ),
			);
		}
		update_site_option( 'ks_plans', $out );
	}
}
