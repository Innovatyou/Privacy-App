<?php
/**
 * Advanced SEO: titles, meta, Open Graph, Twitter cards, JSON-LD schema,
 * breadcrumbs, sitemap tweaks, robots and 301 redirects.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO.
 */
class KCS_SEO {

	/**
	 * Post types with the SEO box.
	 *
	 * @return array
	 */
	public static function post_types() {
		return apply_filters( 'kcs_seo_post_types', array( 'post', 'page', 'kcs_sermon', 'kcs_event', 'kcs_ministry', 'kcs_leader', 'kcs_testimony' ) );
	}

	/**
	 * Is another SEO plugin active?
	 *
	 * @return bool
	 */
	public static function conflict() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirects' ), 1 );

		if ( self::conflict() || '1' !== kcs_get_option( 'seo_enabled', '1' ) ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'document_title_separator', array( __CLASS__, 'separator' ) );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_exclude' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_providers' ), 10, 2 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Meta box
	 * ------------------------------------------------------------------- */

	/**
	 * Add SEO meta box.
	 */
	public static function add_meta_box() {
		foreach ( self::post_types() as $type ) {
			add_meta_box( 'kcs_seo', __( 'SEO & Social', 'kingdom-church-suite' ), array( __CLASS__, 'render_meta_box' ), $type, 'normal', 'default' );
		}
	}

	/**
	 * Render SEO box with live Google preview and analysis (see admin.js).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'kcs_seo_save', 'kcs_seo_nonce' );
		$m = array();
		foreach ( array( 'title', 'desc', 'keyword', 'canonical', 'image', 'noindex', 'nofollow' ) as $k ) {
			$m[ $k ] = get_post_meta( $post->ID, '_kcs_seo_' . $k, true );
		}
		$sep = kcs_get_option( 'seo_separator', '|' );
		?>
		<div class="kcs-seo-box" data-sitename="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-sep="<?php echo esc_attr( $sep ); ?>" data-url="<?php echo esc_attr( get_permalink( $post ) ); ?>">
			<?php if ( self::conflict() ) : ?>
				<p class="notice notice-warning inline"><?php esc_html_e( 'Another SEO plugin is active, so these fields are not output. Disable it to use the built-in SEO.', 'kingdom-church-suite' ); ?></p>
			<?php endif; ?>
			<div class="kcs-serp">
				<div class="kcs-serp__url"></div>
				<div class="kcs-serp__title"></div>
				<div class="kcs-serp__desc"></div>
			</div>
			<p><label><strong><?php esc_html_e( 'Focus keyword', 'kingdom-church-suite' ); ?></strong><br>
				<input type="text" class="widefat" name="kcs_seo[keyword]" id="kcs_seo_keyword" value="<?php echo esc_attr( $m['keyword'] ); ?>"></label></p>
			<p><label><strong><?php esc_html_e( 'SEO title', 'kingdom-church-suite' ); ?></strong> <span class="kcs-count" data-for="kcs_seo_title" data-max="60"></span><br>
				<input type="text" class="widefat" name="kcs_seo[title]" id="kcs_seo_title" value="<?php echo esc_attr( $m['title'] ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) . ' ' . $sep . ' ' . get_bloginfo( 'name' ) ); ?>"></label></p>
			<p><label><strong><?php esc_html_e( 'Meta description', 'kingdom-church-suite' ); ?></strong> <span class="kcs-count" data-for="kcs_seo_desc" data-max="160"></span><br>
				<textarea class="widefat" rows="3" name="kcs_seo[desc]" id="kcs_seo_desc"><?php echo esc_textarea( $m['desc'] ); ?></textarea></label></p>
			<ul class="kcs-seo-analysis"></ul>
			<details>
				<summary><?php esc_html_e( 'Advanced', 'kingdom-church-suite' ); ?></summary>
				<p><label><?php esc_html_e( 'Canonical URL', 'kingdom-church-suite' ); ?><br><input type="url" class="widefat" name="kcs_seo[canonical]" value="<?php echo esc_attr( $m['canonical'] ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Social share image URL', 'kingdom-church-suite' ); ?><br><input type="url" class="widefat" name="kcs_seo[image]" value="<?php echo esc_attr( $m['image'] ); ?>"></label></p>
				<p><label><input type="checkbox" name="kcs_seo[noindex]" value="1" <?php checked( $m['noindex'], '1' ); ?>> <?php esc_html_e( 'Hide from search engines (noindex)', 'kingdom-church-suite' ); ?></label><br>
				<label><input type="checkbox" name="kcs_seo[nofollow]" value="1" <?php checked( $m['nofollow'], '1' ); ?>> <?php esc_html_e( 'Do not follow links (nofollow)', 'kingdom-church-suite' ); ?></label></p>
			</details>
		</div>
		<?php
	}

	/**
	 * Save SEO meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['kcs_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kcs_seo_nonce'] ), 'kcs_seo_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
		$in = isset( $_POST['kcs_seo'] ) && is_array( $_POST['kcs_seo'] ) ? wp_unslash( $_POST['kcs_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, '_kcs_seo_title', sanitize_text_field( $in['title'] ?? '' ) );
		update_post_meta( $post_id, '_kcs_seo_desc', sanitize_textarea_field( $in['desc'] ?? '' ) );
		update_post_meta( $post_id, '_kcs_seo_keyword', sanitize_text_field( $in['keyword'] ?? '' ) );
		update_post_meta( $post_id, '_kcs_seo_canonical', esc_url_raw( $in['canonical'] ?? '' ) );
		update_post_meta( $post_id, '_kcs_seo_image', esc_url_raw( $in['image'] ?? '' ) );
		update_post_meta( $post_id, '_kcs_seo_noindex', empty( $in['noindex'] ) ? '' : '1' );
		update_post_meta( $post_id, '_kcs_seo_nofollow', empty( $in['nofollow'] ) ? '' : '1' );
	}

	/* ---------------------------------------------------------------------
	 * Titles & meta
	 * ------------------------------------------------------------------- */

	/**
	 * Separator.
	 *
	 * @return string
	 */
	public static function separator() {
		return kcs_get_option( 'seo_separator', '|' );
	}

	/**
	 * Custom document title.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function document_title( $title ) {
		if ( is_front_page() && kcs_get_option( 'seo_home_title' ) ) {
			return kcs_get_option( 'seo_home_title' );
		}
		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_kcs_seo_title', true );
			if ( $custom ) {
				return $custom;
			}
		}
		return $title;
	}

	/**
	 * Meta description for the current request.
	 *
	 * @return string
	 */
	public static function description() {
		$desc = '';
		if ( is_front_page() ) {
			$desc = kcs_get_option( 'seo_home_desc', get_bloginfo( 'description' ) );
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			$desc = get_post_meta( $post->ID, '_kcs_seo_desc', true );
			if ( ! $desc ) {
				$desc = $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$desc = term_description();
		} elseif ( is_post_type_archive() ) {
			$obj  = get_queried_object();
			$desc = ! empty( $obj->description ) ? $obj->description : '';
		}
		$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $desc ) ) );
		if ( mb_strlen( $desc ) > 160 ) {
			$desc = rtrim( mb_substr( $desc, 0, 157 ), " ,.;:" ) . '…';
		}
		return apply_filters( 'kcs_seo_description', $desc );
	}

	/**
	 * Canonical URL.
	 *
	 * @return string
	 */
	public static function canonical() {
		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_kcs_seo_canonical', true );
			return $custom ? $custom : wp_get_canonical_url();
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? '' : $link;
		}
		if ( is_post_type_archive() ) {
			$pt = get_query_var( 'post_type' );
			return get_post_type_archive_link( is_array( $pt ) ? current( $pt ) : $pt );
		}
		if ( is_home() ) {
			return get_permalink( get_option( 'page_for_posts' ) );
		}
		return '';
	}

	/**
	 * Share image.
	 *
	 * @return string
	 */
	public static function image() {
		if ( is_singular() ) {
			$id     = get_queried_object_id();
			$custom = get_post_meta( $id, '_kcs_seo_image', true );
			if ( $custom ) {
				return $custom;
			}
			if ( has_post_thumbnail( $id ) ) {
				return get_the_post_thumbnail_url( $id, 'large' );
			}
			$yt = KCS_Post_Types::youtube_id( get_post_meta( $id, '_kcs_video_url', true ) );
			if ( $yt ) {
				return 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
			}
		}
		$default = kcs_get_option( 'seo_default_image' );
		if ( $default ) {
			return $default;
		}
		$logo_id = get_theme_mod( 'custom_logo' );
		return $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	}

	/**
	 * Robots directives.
	 *
	 * @param array $robots Robots.
	 * @return array
	 */
	public static function robots( $robots ) {
		$noindex = false;
		if ( is_singular() ) {
			$id      = get_queried_object_id();
			$noindex = '1' === get_post_meta( $id, '_kcs_seo_noindex', true );
			if ( '1' === get_post_meta( $id, '_kcs_seo_nofollow', true ) ) {
				$robots['nofollow'] = true;
			}
		} elseif ( is_search() && '1' === kcs_get_option( 'seo_noindex_search', '1' ) ) {
			$noindex = true;
		} elseif ( ( is_date() || is_author() ) && '1' === kcs_get_option( 'seo_noindex_archives', '1' ) ) {
			$noindex = true;
		} elseif ( is_404() ) {
			$noindex = true;
		}
		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = empty( $robots['nofollow'] );
		} else {
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = '-1';
			$robots['max-video-preview'] = '-1';
		}
		return $robots;
	}

	/**
	 * Output head tags.
	 */
	public static function head() {
		$title     = wp_get_document_title();
		$desc      = self::description();
		$canonical = self::canonical();
		$image     = self::image();
		$type      = is_singular( array( 'post', 'kcs_sermon' ) ) ? 'article' : 'website';
		if ( is_singular( 'kcs_sermon' ) && KCS_Post_Types::youtube_id( get_post_meta( get_queried_object_id(), '_kcs_video_url', true ) ) ) {
			$type = 'video.other';
		}

		echo "\n<!-- Kingdom Church Suite SEO -->\n";
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $canonical && ! is_404() && ! is_search() ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}
		$tags = array(
			'og:locale'      => get_locale(),
			'og:type'        => $type,
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => $canonical,
			'og:image'       => $image,
			'fb:app_id'      => kcs_get_option( 'fb_app_id' ),
		);
		foreach ( $tags as $prop => $content ) {
			if ( $content ) {
				echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
		if ( 'article' === $type ) {
			echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( DATE_ATOM ) ) . '">' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( DATE_ATOM ) ) . '">' . "\n";
		}
		$handle = ltrim( (string) kcs_get_option( 'twitter_handle' ), '@' );
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
		}
		if ( $handle ) {
			echo '<meta name="twitter:site" content="@' . esc_attr( $handle ) . '">' . "\n";
		}
		if ( kcs_get_option( 'verify_google' ) ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( kcs_get_option( 'verify_google' ) ) . '">' . "\n";
		}
		if ( kcs_get_option( 'verify_bing' ) ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( kcs_get_option( 'verify_bing' ) ) . '">' . "\n";
		}
		if ( kcs_get_option( 'geo_lat' ) && kcs_get_option( 'geo_lng' ) ) {
			echo '<meta name="geo.position" content="' . esc_attr( kcs_get_option( 'geo_lat' ) . ';' . kcs_get_option( 'geo_lng' ) ) . '">' . "\n";
			echo '<meta name="ICBM" content="' . esc_attr( kcs_get_option( 'geo_lat' ) . ', ' . kcs_get_option( 'geo_lng' ) ) . '">' . "\n";
		}
		echo '<script type="application/ld+json">' . wp_json_encode( self::schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
		echo "<!-- / Kingdom Church Suite SEO -->\n";
	}

	/* ---------------------------------------------------------------------
	 * Schema.org
	 * ------------------------------------------------------------------- */

	/**
	 * Local date-time meta → ISO 8601 with timezone.
	 *
	 * @param string $value Y-m-d\TH:i.
	 * @return string
	 */
	private static function iso( $value ) {
		if ( ! $value ) {
			return '';
		}
		try {
			return ( new DateTimeImmutable( $value, wp_timezone() ) )->format( DATE_ATOM );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Church entity.
	 *
	 * @return array
	 */
	public static function church_entity() {
		$home   = home_url( '/' );
		$same   = array_values( array_filter( array( kcs_get_option( 'social_facebook' ), kcs_get_option( 'social_instagram' ), kcs_get_option( 'social_youtube' ), kcs_get_option( 'social_x' ), kcs_get_option( 'social_tiktok' ) ) ) );
		$logo   = kcs_get_option( 'church_logo' );
		$logo   = $logo ? $logo : ( get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '' );
		$church = array(
			'@type' => 'Church',
			'@id'   => $home . '#church',
			'name'  => kcs_get_option( 'church_name', get_bloginfo( 'name' ) ),
			'url'   => $home,
		);
		if ( $logo ) {
			$church['logo']  = array( '@type' => 'ImageObject', 'url' => $logo );
			$church['image'] = $logo;
		}
		if ( kcs_get_option( 'church_phone' ) ) {
			$church['telephone'] = kcs_get_option( 'church_phone' );
		}
		if ( kcs_get_option( 'church_email' ) ) {
			$church['email'] = kcs_get_option( 'church_email' );
		}
		if ( kcs_get_option( 'church_address' ) ) {
			$church['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => kcs_get_option( 'church_address' ),
					'addressLocality' => kcs_get_option( 'church_city' ),
					'addressRegion'   => kcs_get_option( 'church_region' ),
					'postalCode'      => kcs_get_option( 'church_postcode' ),
					'addressCountry'  => kcs_get_option( 'church_country' ),
				)
			);
		}
		if ( kcs_get_option( 'geo_lat' ) && kcs_get_option( 'geo_lng' ) ) {
			$church['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) kcs_get_option( 'geo_lat' ),
				'longitude' => (float) kcs_get_option( 'geo_lng' ),
			);
		}
		if ( $same ) {
			$church['sameAs'] = $same;
		}
		$hours = array();
		foreach ( KCS_Livestream::schedule() as $s ) {
			$end     = gmdate( 'H:i', strtotime( $s['time'] ) + 2 * HOUR_IN_SECONDS );
			$hours[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . $s['day'],
				'opens'     => $s['time'],
				'closes'    => $end,
				'name'      => $s['name'],
			);
		}
		if ( $hours ) {
			$church['openingHoursSpecification'] = $hours;
		}
		return $church;
	}

	/**
	 * Full JSON-LD graph.
	 *
	 * @return array
	 */
	public static function schema() {
		$home  = home_url( '/' );
		$graph = array(
			self::church_entity(),
			array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => get_bloginfo( 'name' ),
				'description'     => get_bloginfo( 'description' ),
				'publisher'       => array( '@id' => $home . '#church' ),
				'inLanguage'      => get_bloginfo( 'language' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ),
					'query-input' => 'required name=search_term_string',
				),
			),
		);

		$crumbs = self::breadcrumb_items();
		if ( count( $crumbs ) > 1 ) {
			$list = array();
			foreach ( $crumbs as $i => $c ) {
				$list[] = array_filter(
					array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $c['label'],
						'item'     => $c['url'],
					)
				);
			}
			$graph[] = array( '@type' => 'BreadcrumbList', '@id' => self::canonical() . '#breadcrumb', 'itemListElement' => $list );
		}

		if ( is_singular() ) {
			$post    = get_queried_object();
			$url     = get_permalink( $post );
			$image   = self::image();
			$desc    = self::description();
			$graph[] = array(
				'@type'         => 'WebPage',
				'@id'           => $url . '#webpage',
				'url'           => $url,
				'name'          => wp_get_document_title(),
				'isPartOf'      => array( '@id' => $home . '#website' ),
				'datePublished' => get_the_date( DATE_ATOM, $post ),
				'dateModified'  => get_the_modified_date( DATE_ATOM, $post ),
				'description'   => $desc,
			);

			switch ( $post->post_type ) {
				case 'kcs_event':
					$start  = self::iso( get_post_meta( $post->ID, '_kcs_start', true ) );
					$end    = self::iso( get_post_meta( $post->ID, '_kcs_end', true ) );
					$venue  = get_post_meta( $post->ID, '_kcs_location', true );
					$addr   = get_post_meta( $post->ID, '_kcs_address', true );
					$online = get_post_meta( $post->ID, '_kcs_online_url', true );
					$loc    = array();
					if ( $venue || $addr ) {
						$loc[] = array(
							'@type'   => 'Place',
							'name'    => $venue ? $venue : kcs_get_option( 'church_name', get_bloginfo( 'name' ) ),
							'address' => $addr ? $addr : kcs_get_option( 'church_address' ),
						);
					}
					if ( $online ) {
						$loc[] = array( '@type' => 'VirtualLocation', 'url' => $online );
					}
					$mode  = $online && $loc && count( $loc ) > 1 ? 'MixedEventAttendanceMode' : ( $online ? 'OnlineEventAttendanceMode' : 'OfflineEventAttendanceMode' );
					$event = array(
						'@type'               => 'Event',
						'@id'                 => $url . '#event',
						'name'                => get_the_title( $post ),
						'description'         => $desc,
						'startDate'           => $start,
						'endDate'             => $end ? $end : $start,
						'eventStatus'         => 'https://schema.org/EventScheduled',
						'eventAttendanceMode' => 'https://schema.org/' . $mode,
						'location'            => 1 === count( $loc ) ? $loc[0] : $loc,
						'image'               => $image ? array( $image ) : array(),
						'organizer'           => array( '@id' => $home . '#church' ),
						'url'                 => $url,
					);
					$price = get_post_meta( $post->ID, '_kcs_price', true );
					$event['offers'] = array(
						'@type'         => 'Offer',
						'price'         => $price ? preg_replace( '/[^0-9.]/', '', $price ) : '0',
						'priceCurrency' => kcs_get_option( 'currency', 'USD' ),
						'availability'  => 'https://schema.org/InStock',
						'url'           => get_post_meta( $post->ID, '_kcs_register_url', true ) ? get_post_meta( $post->ID, '_kcs_register_url', true ) : $url,
						'validFrom'     => get_the_date( DATE_ATOM, $post ),
					);
					if ( $start ) {
						$graph[] = $event;
					}
					break;

				case 'kcs_sermon':
					$yt       = KCS_Post_Types::youtube_id( get_post_meta( $post->ID, '_kcs_video_url', true ) );
					$audio    = get_post_meta( $post->ID, '_kcs_audio_url', true );
					$speakers = wp_get_post_terms( $post->ID, 'kcs_speaker', array( 'fields' => 'names' ) );
					$date     = get_post_meta( $post->ID, '_kcs_sermon_date', true );
					$date     = $date ? self::iso( $date . 'T00:00' ) : get_the_date( DATE_ATOM, $post );
					if ( $yt ) {
						$graph[] = array(
							'@type'        => 'VideoObject',
							'@id'          => $url . '#video',
							'name'         => get_the_title( $post ),
							'description'  => $desc ? $desc : get_the_title( $post ),
							'thumbnailUrl' => array( 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' ),
							'uploadDate'   => $date,
							'embedUrl'     => 'https://www.youtube.com/embed/' . $yt,
							'contentUrl'   => 'https://www.youtube.com/watch?v=' . $yt,
							'publisher'    => array( '@id' => $home . '#church' ),
						);
					}
					$graph[] = array_filter(
						array(
							'@type'         => $audio ? 'PodcastEpisode' : 'CreativeWork',
							'@id'           => $url . '#sermon',
							'name'          => get_the_title( $post ),
							'description'   => $desc,
							'datePublished' => $date,
							'url'           => $url,
							'image'         => $image,
							'author'        => $speakers && ! is_wp_error( $speakers ) ? array( '@type' => 'Person', 'name' => $speakers[0] ) : array( '@id' => $home . '#church' ),
							'associatedMedia' => $audio ? array( '@type' => 'MediaObject', 'contentUrl' => $audio ) : null,
							'about'         => get_post_meta( $post->ID, '_kcs_scripture', true ),
						)
					);
					break;

				case 'kcs_leader':
					$graph[] = array_filter(
						array(
							'@type'    => 'Person',
							'name'     => get_the_title( $post ),
							'jobTitle' => get_post_meta( $post->ID, '_kcs_role', true ),
							'image'    => $image,
							'worksFor' => array( '@id' => $home . '#church' ),
							'url'      => $url,
							'sameAs'   => array_values( array_filter( array( get_post_meta( $post->ID, '_kcs_facebook', true ), get_post_meta( $post->ID, '_kcs_instagram', true ), get_post_meta( $post->ID, '_kcs_x', true ) ) ) ),
						)
					);
					break;

				case 'post':
					$graph[] = array_filter(
						array(
							'@type'            => 'BlogPosting',
							'@id'              => $url . '#article',
							'headline'         => get_the_title( $post ),
							'description'      => $desc,
							'image'            => $image,
							'datePublished'    => get_the_date( DATE_ATOM, $post ),
							'dateModified'     => get_the_modified_date( DATE_ATOM, $post ),
							'author'           => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ),
							'publisher'        => array( '@id' => $home . '#church' ),
							'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
							'keywords'         => get_post_meta( $post->ID, '_kcs_seo_keyword', true ),
						)
					);
					break;
			}
		}

		return apply_filters( 'kcs_schema_graph', array( '@context' => 'https://schema.org', '@graph' => array_values( $graph ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Breadcrumbs
	 * ------------------------------------------------------------------- */

	/**
	 * Breadcrumb items for the current page.
	 *
	 * @return array[] label, url.
	 */
	public static function breadcrumb_items() {
		$items = array( array( 'label' => __( 'Home', 'kingdom-church-suite' ), 'url' => home_url( '/' ) ) );
		if ( is_front_page() ) {
			return $items;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( 'page' === $post->post_type ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
					$items[] = array( 'label' => get_the_title( $ancestor ), 'url' => get_permalink( $ancestor ) );
				}
			} elseif ( 'post' === $post->post_type ) {
				$blog = get_option( 'page_for_posts' );
				if ( $blog ) {
					$items[] = array( 'label' => get_the_title( $blog ), 'url' => get_permalink( $blog ) );
				}
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$items[] = array( 'label' => $cats[0]->name, 'url' => get_category_link( $cats[0] ) );
				}
			} else {
				$obj = get_post_type_object( $post->post_type );
				if ( $obj && $obj->has_archive ) {
					$items[] = array( 'label' => $obj->labels->name, 'url' => get_post_type_archive_link( $post->post_type ) );
				}
			}
			$items[] = array( 'label' => get_the_title( $post ), 'url' => '' );
		} elseif ( is_post_type_archive() ) {
			$items[] = array( 'label' => post_type_archive_title( '', false ), 'url' => '' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$tax  = get_taxonomy( $term->taxonomy );
			if ( $tax && ! empty( $tax->object_type[0] ) && 'post' !== $tax->object_type[0] ) {
				$obj = get_post_type_object( $tax->object_type[0] );
				if ( $obj && $obj->has_archive ) {
					$items[] = array( 'label' => $obj->labels->name, 'url' => get_post_type_archive_link( $tax->object_type[0] ) );
				}
			}
			$items[] = array( 'label' => single_term_title( '', false ), 'url' => '' );
		} elseif ( is_search() ) {
			/* translators: %s: search query */
			$items[] = array( 'label' => sprintf( __( 'Search: %s', 'kingdom-church-suite' ), get_search_query() ), 'url' => '' );
		} elseif ( is_404() ) {
			$items[] = array( 'label' => __( 'Page not found', 'kingdom-church-suite' ), 'url' => '' );
		} elseif ( is_home() ) {
			$items[] = array( 'label' => get_the_title( get_option( 'page_for_posts' ) ), 'url' => '' );
		} elseif ( is_archive() ) {
			$items[] = array( 'label' => get_the_archive_title(), 'url' => '' );
		}
		return apply_filters( 'kcs_breadcrumb_items', $items );
	}

	/**
	 * Breadcrumb HTML.
	 *
	 * @return string
	 */
	public static function breadcrumbs() {
		$items = self::breadcrumb_items();
		if ( count( $items ) < 2 ) {
			return '';
		}
		$out  = '<nav class="kcs-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'kingdom-church-suite' ) . '"><ol>';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $item ) {
			$out .= '<li>' . ( $item['url'] && $i !== $last ? '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>' : '<span aria-current="page">' . esc_html( wp_strip_all_tags( $item['label'] ) ) . '</span>' ) . '</li>';
		}
		return $out . '</ol></nav>';
	}

	/* ---------------------------------------------------------------------
	 * Sitemaps, robots.txt, redirects
	 * ------------------------------------------------------------------- */

	/**
	 * Exclude noindex posts from core sitemaps.
	 *
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function sitemap_exclude( $args, $post_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array( 'key' => '_kcs_seo_noindex', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_kcs_seo_noindex', 'value' => '1', 'compare' => '!=' ),
		);
		return $args;
	}

	/**
	 * Remove the users sitemap (thin content).
	 *
	 * @param WP_Sitemaps_Provider $provider Provider.
	 * @param string               $name     Name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public static function sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Add sitemap to robots.txt.
	 *
	 * @param string $output Output.
	 * @param bool   $public Is public.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) {
		// Core already adds the wp-admin rules and (when sitemaps are on) the Sitemap line.
		if ( $public && false === stripos( $output, 'Sitemap:' ) ) {
			$output .= "\nSitemap: " . esc_url_raw( home_url( '/wp-sitemap.xml' ) ) . "\n";
		}
		return $output;
	}

	/**
	 * 301 redirects from settings ("/old-path => https://new-url" per line).
	 */
	public static function redirects() {
		$rules = (string) kcs_get_option( 'seo_redirects' );
		if ( ! $rules || is_admin() ) {
			return;
		}
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$path = '/' . trim( (string) $path, '/' );
		foreach ( explode( "\n", $rules ) as $line ) {
			$parts = array_map( 'trim', explode( '=>', $line, 2 ) );
			if ( 2 !== count( $parts ) || '' === $parts[0] ) {
				continue;
			}
			if ( '/' . trim( $parts[0], '/' ) === $path ) {
				wp_redirect( esc_url_raw( $parts[1] ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- admin-defined destinations.
				exit;
			}
		}
	}
}

/**
 * Template tag: print breadcrumbs.
 */
function kcs_breadcrumbs() {
	echo KCS_SEO::breadcrumbs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in method.
}
