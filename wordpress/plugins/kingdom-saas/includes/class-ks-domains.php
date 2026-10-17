<?php
/**
 * Custom domains: add, verify, connect (becomes the site's main address), remove.
 *
 * Verification passes when http://domain/.well-known/ks-domain-check answers with the
 * domain's token (the domain already points at this server), or when a TXT record
 * _ks-verify.domain holds the token and the domain's CNAME/A record points here.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Domains.
 */
class KS_Domains {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'ks_daily', array( __CLASS__, 'recheck_pending' ) );
	}

	/**
	 * Domains of a site.
	 *
	 * @param int $blog_id Site.
	 * @return array
	 */
	public static function for_site( $blog_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'domains' ) . ' WHERE blog_id = %d ORDER BY id DESC', $blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * All domains.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . ks_table( 'domains' ) . ' ORDER BY id DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * One domain.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'domains' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Clean a typed domain: "https://www.Grace.org/about" → "www.grace.org".
	 *
	 * @param string $raw Input.
	 * @return string
	 */
	public static function normalize( $raw ) {
		$d = strtolower( trim( (string) $raw ) );
		$d = preg_replace( '#^[a-z]+://#', '', $d );
		$d = preg_replace( '#[/?\#].*$#', '', $d );
		$d = preg_replace( '/:\d+$/', '', $d );
		return rtrim( $d, '.' );
	}

	/**
	 * Add a domain to a site.
	 *
	 * @param int    $blog_id Site.
	 * @param string $raw     Domain.
	 * @return object|WP_Error
	 */
	public static function add( $blog_id, $raw ) {
		global $wpdb;
		if ( ! KS_Tenants::feature( 'custom_domain', $blog_id ) ) {
			return new WP_Error( 'ks_plan', __( 'Custom domains are included on the Growth and Ministry plans. Upgrade to connect your own domain.', 'kingdom-saas' ) );
		}
		$domain = self::normalize( $raw );
		$base   = strtolower( ks_get( 'base_domain' ) );
		if ( ! preg_match( '/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $domain ) ) {
			return new WP_Error( 'ks_domain', __( 'Please enter a domain like yourchurch.org or www.yourchurch.org.', 'kingdom-saas' ) );
		}
		if ( $domain === $base || substr( $domain, -strlen( '.' . $base ) ) === '.' . $base ) {
			return new WP_Error( 'ks_domain', __( 'That is a platform address. Please enter a domain you own.', 'kingdom-saas' ) );
		}
		$bare  = preg_replace( '/^www\./', '', $domain );
		$taken = $wpdb->get_var( $wpdb->prepare( 'SELECT blog_id FROM ' . ks_table( 'domains' ) . ' WHERE domain IN (%s, %s)', $bare, 'www.' . $bare ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ( $taken && (int) $taken !== (int) $blog_id ) || ( ! $taken && domain_exists( $domain, '/' ) ) ) {
			return new WP_Error( 'ks_domain', __( 'That domain is already connected to another church.', 'kingdom-saas' ) );
		}
		// One custom domain per church: replace any previous one.
		foreach ( self::for_site( $blog_id ) as $old ) {
			self::remove( $old->id );
		}
		$wpdb->insert(
			ks_table( 'domains' ),
			array(
				'blog_id'    => $blog_id,
				'domain'     => $domain,
				'token'      => wp_generate_password( 32, false, false ),
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return self::get( $wpdb->insert_id );
	}

	/**
	 * DNS records the church needs to add.
	 *
	 * @param object $d Domain row.
	 * @return array Rows of array( type, host, value ).
	 */
	public static function records( $d ) {
		$target = ks_get( 'cname_target' ) ? ks_get( 'cname_target' ) : ks_get( 'base_domain' );
		$apex   = 0 === strpos( $d->domain, 'www.' ) ? substr( $d->domain, 4 ) : $d->domain;
		$is_sub = substr_count( $apex, '.' ) > 1 && 0 !== strpos( $d->domain, 'www.' );
		$rows   = array();
		if ( $is_sub || 0 === strpos( $d->domain, 'www.' ) ) {
			$rows[] = array( 'CNAME', $d->domain, $target );
		} elseif ( ks_get( 'server_ip' ) ) {
			$rows[] = array( 'A', $d->domain, ks_get( 'server_ip' ) );
			$rows[] = array( 'CNAME', 'www.' . $d->domain, $target );
		} else {
			$rows[] = array( 'CNAME / ALIAS', $d->domain, $target );
		}
		$rows[] = array( 'TXT', '_ks-verify.' . $apex, $d->token );
		return $rows;
	}

	/**
	 * Check DNS and connect the domain when it is ready.
	 *
	 * @param int $id Domain ID.
	 * @return true|WP_Error
	 */
	public static function verify( $id ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d ) {
			return new WP_Error( 'ks_domain', __( 'Domain not found.', 'kingdom-saas' ) );
		}
		if ( ! KS_Tenants::feature( 'custom_domain', $d->blog_id ) ) {
			return new WP_Error( 'ks_plan', __( 'Your plan does not include custom domains.', 'kingdom-saas' ) );
		}
		$ok = self::http_check( $d ) || ( self::txt_check( $d ) && self::points_here( $d->domain ) );
		$wpdb->update( ks_table( 'domains' ), array( 'checked_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		if ( ! $ok ) {
			$msg = __( 'We could not see your domain pointing to us yet. DNS changes can take up to 24 hours — we check again automatically.', 'kingdom-saas' );
			$wpdb->update( ks_table( 'domains' ), array( 'status' => 'pending', 'last_error' => $msg ), array( 'id' => $id ) );
			return new WP_Error( 'ks_dns', $msg );
		}
		$wpdb->update( ks_table( 'domains' ), array( 'status' => 'verified', 'last_error' => '', 'verified_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		self::make_primary( $id );
		$sub = KS_Tenants::get( $d->blog_id );
		if ( $sub ) {
			KS_Email::owner(
				$sub,
				/* translators: %s: domain */
				sprintf( __( '%s is now connected', 'kingdom-saas' ), $d->domain ),
				/* translators: %s: domain */
				'<p>' . esc_html( sprintf( __( 'Your website now opens at %s. Your old address redirects there automatically.', 'kingdom-saas' ), $d->domain ) ) . '</p>',
				array( __( 'Visit your website', 'kingdom-saas' ), ks_scheme() . '://' . $d->domain )
			);
		}
		return true;
	}

	/**
	 * Does the domain already reach this server?
	 *
	 * @param object $d Domain row.
	 * @return bool
	 */
	private static function http_check( $d ) {
		$res = wp_remote_get( 'http://' . $d->domain . '/.well-known/ks-domain-check', array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => false ) );
		return ! is_wp_error( $res ) && trim( wp_remote_retrieve_body( $res ) ) === $d->token;
	}

	/**
	 * Is the TXT token published?
	 *
	 * @param object $d Domain row.
	 * @return bool
	 */
	private static function txt_check( $d ) {
		$apex    = preg_replace( '/^www\./', '', $d->domain );
		$records = apply_filters( 'ks_dns_records', @dns_get_record( '_ks-verify.' . $apex, DNS_TXT ), '_ks-verify.' . $apex, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		foreach ( (array) $records as $r ) {
			if ( isset( $r['txt'] ) && trim( $r['txt'] ) === $d->token ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does the domain's CNAME or A record point at the platform?
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	private static function points_here( $domain ) {
		$target  = strtolower( ks_get( 'cname_target' ) ? ks_get( 'cname_target' ) : ks_get( 'base_domain' ) );
		$ip      = ks_get( 'server_ip' );
		$records = apply_filters( 'ks_dns_records', @dns_get_record( $domain, DNS_CNAME | DNS_A ), $domain, DNS_CNAME | DNS_A ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		foreach ( (array) $records as $r ) {
			if ( ( isset( $r['target'] ) && rtrim( strtolower( $r['target'] ), '.' ) === $target ) || ( $ip && isset( $r['ip'] ) && $r['ip'] === $ip ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Use the domain as the site's main address.
	 *
	 * @param int $id Domain ID.
	 */
	public static function make_primary( $id ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d || 'verified' !== $d->status ) {
			return;
		}
		self::set_site_domain( $d->blog_id, $d->domain );
		$wpdb->update( ks_table( 'domains' ), array( 'is_primary' => 0 ), array( 'blog_id' => $d->blog_id ) );
		$wpdb->update( ks_table( 'domains' ), array( 'is_primary' => 1 ), array( 'id' => $id ) );
	}

	/**
	 * Remove a domain (the church goes back to its platform subdomain).
	 *
	 * @param int $id Domain ID.
	 */
	public static function remove( $id ) {
		global $wpdb;
		$d = self::get( $id );
		if ( ! $d ) {
			return;
		}
		if ( $d->is_primary ) {
			self::revert( $d->blog_id );
		}
		$wpdb->delete( ks_table( 'domains' ), array( 'id' => $id ) );
	}

	/**
	 * Point a site back at its subdomain.
	 *
	 * @param int $blog_id Site.
	 */
	public static function revert( $blog_id ) {
		$sub = KS_Tenants::get( $blog_id );
		if ( $sub && $sub->subdomain ) {
			self::set_site_domain( $blog_id, $sub->subdomain );
		}
	}

	/**
	 * Disconnect custom domains when the plan no longer includes them.
	 *
	 * @param int $blog_id Site.
	 */
	public static function enforce_plan( $blog_id ) {
		global $wpdb;
		if ( KS_Tenants::feature( 'custom_domain', $blog_id ) ) {
			return;
		}
		foreach ( self::for_site( $blog_id ) as $d ) {
			if ( $d->is_primary ) {
				self::revert( $blog_id );
			}
			$wpdb->update( ks_table( 'domains' ), array( 'status' => 'pending', 'is_primary' => 0, 'last_error' => __( 'Your current plan does not include custom domains.', 'kingdom-saas' ) ), array( 'id' => $d->id ) );
		}
	}

	/**
	 * Change a site's address (wp_blogs row and its home/siteurl).
	 *
	 * @param int    $blog_id Site.
	 * @param string $domain  Domain.
	 */
	private static function set_site_domain( $blog_id, $domain ) {
		wp_update_site( $blog_id, array( 'domain' => $domain, 'path' => '/' ) );
		$url = ks_scheme() . '://' . $domain;
		update_blog_option( $blog_id, 'home', $url );
		update_blog_option( $blog_id, 'siteurl', $url );
		clean_blog_cache( $blog_id );
	}

	/**
	 * Daily: try pending domains again.
	 */
	public static function recheck_pending() {
		foreach ( self::all() as $d ) {
			if ( 'pending' === $d->status && KS_Tenants::feature( 'custom_domain', $d->blog_id ) ) {
				self::verify( $d->id );
			}
		}
	}

	/**
	 * May the TLS proxy issue a certificate for this host? (Caddy on-demand TLS "ask".)
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function tls_allowed( $host ) {
		global $wpdb;
		$host = self::normalize( $host );
		if ( ! $host ) {
			return false;
		}
		$bare = preg_replace( '/^www\./', '', $host );
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ks_table( 'domains' ) . ' WHERE domain IN (%s, %s, %s)', $host, $bare, 'www.' . $bare ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return true;
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND deleted = 0", $host ) ) ) {
			return true;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ks_table( 'subscriptions' ) . ' WHERE subdomain = %s', $host ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
