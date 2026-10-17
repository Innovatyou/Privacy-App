<?php
/**
 * Custom post types, taxonomies and meta boxes.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types.
 */
class KCS_Post_Types {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_kcs_event_posts_columns', array( __CLASS__, 'event_columns' ) );
		add_action( 'manage_kcs_event_posts_custom_column', array( __CLASS__, 'event_column' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_events' ) );
	}

	/**
	 * Meta field definitions per post type.
	 *
	 * @return array
	 */
	public static function meta_fields() {
		return apply_filters(
			'kcs_meta_fields',
			array(
				'kcs_sermon'    => array(
					'_kcs_sermon_date' => array( __( 'Date preached', 'kingdom-church-suite' ), 'date' ),
					'_kcs_video_url'   => array( __( 'Video URL (YouTube / Vimeo)', 'kingdom-church-suite' ), 'url' ),
					'_kcs_audio_url'   => array( __( 'Audio URL (MP3)', 'kingdom-church-suite' ), 'url' ),
					'_kcs_duration'    => array( __( 'Duration (e.g. 45:12)', 'kingdom-church-suite' ), 'text' ),
					'_kcs_scripture'   => array( __( 'Scripture reference', 'kingdom-church-suite' ), 'text' ),
					'_kcs_notes_url'   => array( __( 'Sermon notes / PDF URL', 'kingdom-church-suite' ), 'url' ),
				),
				'kcs_event'     => array(
					'_kcs_start'        => array( __( 'Starts', 'kingdom-church-suite' ), 'datetime-local' ),
					'_kcs_end'          => array( __( 'Ends', 'kingdom-church-suite' ), 'datetime-local' ),
					'_kcs_location'     => array( __( 'Venue name', 'kingdom-church-suite' ), 'text' ),
					'_kcs_address'      => array( __( 'Venue address', 'kingdom-church-suite' ), 'text' ),
					'_kcs_online_url'   => array( __( 'Online / livestream URL', 'kingdom-church-suite' ), 'url' ),
					'_kcs_register_url' => array( __( 'Registration URL', 'kingdom-church-suite' ), 'url' ),
					'_kcs_price'        => array( __( 'Price (leave empty for free)', 'kingdom-church-suite' ), 'text' ),
					'_kcs_recurrence'   => array( __( 'Recurrence note (e.g. Every Friday)', 'kingdom-church-suite' ), 'text' ),
				),
				'kcs_ministry'  => array(
					'_kcs_leader_name'   => array( __( 'Ministry leader', 'kingdom-church-suite' ), 'text' ),
					'_kcs_meeting_time'  => array( __( 'Meeting time', 'kingdom-church-suite' ), 'text' ),
					'_kcs_meeting_place' => array( __( 'Meeting place', 'kingdom-church-suite' ), 'text' ),
					'_kcs_contact_email' => array( __( 'Contact email', 'kingdom-church-suite' ), 'email' ),
					'_kcs_icon'          => array( __( 'Icon (emoji)', 'kingdom-church-suite' ), 'text' ),
				),
				'kcs_leader'    => array(
					'_kcs_role'      => array( __( 'Role / title', 'kingdom-church-suite' ), 'text' ),
					'_kcs_email'     => array( __( 'Email', 'kingdom-church-suite' ), 'email' ),
					'_kcs_facebook'  => array( 'Facebook URL', 'url' ),
					'_kcs_instagram' => array( 'Instagram URL', 'url' ),
					'_kcs_x'         => array( 'X URL', 'url' ),
				),
				'kcs_testimony' => array(
					'_kcs_person'    => array( __( 'Name', 'kingdom-church-suite' ), 'text' ),
					'_kcs_location'  => array( __( 'Location', 'kingdom-church-suite' ), 'text' ),
					'_kcs_video_url' => array( __( 'Video URL', 'kingdom-church-suite' ), 'url' ),
				),
			)
		);
	}

	/**
	 * Register post types and taxonomies.
	 */
	public static function register() {
		$types = array(
			'kcs_sermon'    => array( __( 'Sermons', 'kingdom-church-suite' ), __( 'Sermon', 'kingdom-church-suite' ), 'sermons', 'dashicons-microphone', array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ) ),
			'kcs_event'     => array( __( 'Events', 'kingdom-church-suite' ), __( 'Event', 'kingdom-church-suite' ), 'events', 'dashicons-calendar-alt', array( 'title', 'editor', 'thumbnail', 'excerpt' ) ),
			'kcs_ministry'  => array( __( 'Ministries', 'kingdom-church-suite' ), __( 'Ministry', 'kingdom-church-suite' ), 'ministries', 'dashicons-groups', array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ) ),
			'kcs_leader'    => array( __( 'Leadership', 'kingdom-church-suite' ), __( 'Leader', 'kingdom-church-suite' ), 'leadership', 'dashicons-businessperson', array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ) ),
			'kcs_testimony' => array( __( 'Testimonies', 'kingdom-church-suite' ), __( 'Testimony', 'kingdom-church-suite' ), 'testimonies', 'dashicons-format-quote', array( 'title', 'editor', 'thumbnail' ) ),
		);

		foreach ( $types as $type => $t ) {
			register_post_type(
				$type,
				array(
					'labels'        => array(
						'name'          => $t[0],
						'singular_name' => $t[1],
						/* translators: %s: singular post type name */
						'add_new_item'  => sprintf( __( 'Add New %s', 'kingdom-church-suite' ), $t[1] ),
						/* translators: %s: singular post type name */
						'edit_item'     => sprintf( __( 'Edit %s', 'kingdom-church-suite' ), $t[1] ),
						'all_items'     => $t[0],
					),
					'public'        => true,
					'has_archive'   => true,
					'show_in_rest'  => true,
					'menu_icon'     => $t[3],
					'menu_position' => 25,
					'supports'      => $t[4],
					'rewrite'       => array( 'slug' => $t[2], 'with_front' => false ),
				)
			);
		}

		$taxes = array(
			'kcs_series'    => array( 'kcs_sermon', __( 'Series', 'kingdom-church-suite' ), 'sermon-series', true ),
			'kcs_speaker'   => array( 'kcs_sermon', __( 'Speakers', 'kingdom-church-suite' ), 'speaker', true ),
			'kcs_topic'     => array( 'kcs_sermon', __( 'Topics', 'kingdom-church-suite' ), 'sermon-topic', false ),
			'kcs_event_cat' => array( 'kcs_event', __( 'Event Categories', 'kingdom-church-suite' ), 'event-category', true ),
		);
		foreach ( $taxes as $tax => $t ) {
			register_taxonomy(
				$tax,
				$t[0],
				array(
					'label'             => $t[1],
					'hierarchical'      => $t[3],
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => $t[2], 'with_front' => false ),
				)
			);
		}

		foreach ( self::meta_fields() as $type => $fields ) {
			foreach ( $fields as $key => $f ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'          => 'string',
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}
	}

	/**
	 * Register meta boxes.
	 */
	public static function add_meta_boxes() {
		foreach ( self::meta_fields() as $type => $fields ) {
			add_meta_box( 'kcs_details', __( 'Details', 'kingdom-church-suite' ), array( __CLASS__, 'render_meta_box' ), $type, 'normal', 'high' );
		}
	}

	/**
	 * Render a meta box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$fields = self::meta_fields();
		if ( empty( $fields[ $post->post_type ] ) ) {
			return;
		}
		wp_nonce_field( 'kcs_save_meta', 'kcs_meta_nonce' );
		echo '<table class="form-table kcs-meta">';
		foreach ( $fields[ $post->post_type ] as $key => $f ) {
			$value = get_post_meta( $post->ID, $key, true );
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%1$s" value="%4$s" class="widefat"></td></tr>',
				esc_attr( $key ),
				esc_html( $f[0] ),
				esc_attr( $f[1] ),
				esc_attr( $value )
			);
		}
		echo '</table>';
	}

	/**
	 * Save meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['kcs_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kcs_meta_nonce'] ), 'kcs_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$fields = self::meta_fields();
		if ( empty( $fields[ $post->post_type ] ) ) {
			return;
		}
		foreach ( $fields[ $post->post_type ] as $key => $f ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			switch ( $f[1] ) {
				case 'url':
					$value = esc_url_raw( $raw );
					break;
				case 'email':
					$value = sanitize_email( $raw );
					break;
				default:
					$value = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Event admin columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function event_columns( $cols ) {
		$cols['kcs_start'] = __( 'Starts', 'kingdom-church-suite' );
		return $cols;
	}

	/**
	 * Event column content.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function event_column( $col, $post_id ) {
		if ( 'kcs_start' === $col ) {
			echo esc_html( self::format_event_date( $post_id ) );
		}
	}

	/**
	 * Show upcoming events first on the events archive.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function order_events( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( 'kcs_event' ) || $q->is_tax( 'kcs_event_cat' ) ) {
			$q->set( 'meta_key', '_kcs_start' );
			$q->set( 'orderby', 'meta_value' );
			$q->set( 'order', 'ASC' );
			if ( empty( $_GET['past'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$q->set(
					'meta_query',
					array(
						array(
							'key'     => '_kcs_start',
							'value'   => current_time( 'Y-m-d\TH:i' ),
							'compare' => '>=',
						),
					)
				);
			}
		}
		if ( $q->is_post_type_archive( array( 'kcs_ministry', 'kcs_leader' ) ) ) {
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
		}
	}

	/**
	 * Human readable event date.
	 *
	 * @param int $post_id Event ID.
	 * @return string
	 */
	public static function format_event_date( $post_id ) {
		$start = get_post_meta( $post_id, '_kcs_start', true );
		$end   = get_post_meta( $post_id, '_kcs_end', true );
		if ( ! $start ) {
			return '';
		}
		$fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$out = date_i18n( $fmt, strtotime( $start ) );
		if ( $end ) {
			$same_day = substr( $start, 0, 10 ) === substr( $end, 0, 10 );
			$out     .= ' – ' . date_i18n( $same_day ? get_option( 'time_format' ) : $fmt, strtotime( $end ) );
		}
		return $out;
	}

	/**
	 * Extract a YouTube video ID from a URL or bare ID.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function youtube_id( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $url ) ) {
			return $url;
		}
		if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|live/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}
}
