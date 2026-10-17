<?php
/**
 * Theme supports, menus, widgets and assets.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme setup.
 */
function kingdom_setup() {
	load_theme_textdomain( 'kingdom', KINGDOM_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 260, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'kingdom-card', 720, 450, true );
	add_image_size( 'kingdom-hero', 1920, 1080, true );
	add_image_size( 'kingdom-portrait', 600, 720, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'kingdom' ),
			'topbar'  => __( 'Top bar menu', 'kingdom' ),
			'footer'  => __( 'Footer menu', 'kingdom' ),
		)
	);
}
add_action( 'after_setup_theme', 'kingdom_setup' );

/**
 * Content width.
 */
function kingdom_content_width() {
	$GLOBALS['content_width'] = 780; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}
add_action( 'after_setup_theme', 'kingdom_content_width', 0 );

/**
 * Widget areas.
 */
function kingdom_widgets() {
	$common = array(
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	);
	register_sidebar( array_merge( $common, array( 'id' => 'sidebar-1', 'name' => __( 'Sidebar', 'kingdom' ) ) ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: column number */
		register_sidebar( array_merge( $common, array( 'id' => 'footer-' . $i, 'name' => sprintf( __( 'Footer column %d', 'kingdom' ), $i ) ) ) );
	}
}
add_action( 'widgets_init', 'kingdom_widgets' );

/**
 * Google font choices.
 *
 * @return array
 */
function kingdom_fonts() {
	return array(
		'Inter'            => 'Inter:wght@400;500;600;700;800',
		'Poppins'          => 'Poppins:wght@400;500;600;700;800',
		'Montserrat'       => 'Montserrat:wght@400;500;600;700;800',
		'DM Sans'          => 'DM+Sans:wght@400;500;700',
		'Plus Jakarta Sans' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800',
		'Playfair Display' => 'Playfair+Display:wght@500;600;700;800',
		'Cormorant Garamond' => 'Cormorant+Garamond:wght@500;600;700',
		'Lora'             => 'Lora:wght@400;500;600;700',
		'System'           => '',
	);
}

/**
 * Front-end assets.
 */
function kingdom_assets() {
	$fonts    = kingdom_fonts();
	$heading  = get_theme_mod( 'kingdom_font_heading', 'Playfair Display' );
	$body     = get_theme_mod( 'kingdom_font_body', 'Inter' );
	$families = array_filter( array_unique( array( $fonts[ $heading ] ?? '', $fonts[ $body ] ?? '' ) ) );
	if ( $families ) {
		wp_enqueue_style( 'kingdom-fonts', 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $families ) . '&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
	wp_enqueue_style( 'kingdom-main', KINGDOM_URI . '/assets/css/main.css', array(), KINGDOM_VERSION );
	wp_add_inline_style( 'kingdom-main', kingdom_dynamic_css() );
	wp_enqueue_script( 'kingdom-main', KINGDOM_URI . '/assets/js/main.js', array(), KINGDOM_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'kingdom_assets' );

/**
 * Preconnect to Google Fonts.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Relation.
 * @return array
 */
function kingdom_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && wp_style_is( 'kingdom-fonts', 'queue' ) ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'kingdom_resource_hints', 10, 2 );

/**
 * CSS variables from the Customizer.
 *
 * @return string
 */
function kingdom_dynamic_css() {
	$primary = sanitize_hex_color( get_theme_mod( 'kingdom_color_primary', '#5b2a86' ) );
	$accent  = sanitize_hex_color( get_theme_mod( 'kingdom_color_accent', '#f4a62a' ) );
	$dark    = sanitize_hex_color( get_theme_mod( 'kingdom_color_dark', '#16121c' ) );
	$heading = get_theme_mod( 'kingdom_font_heading', 'Playfair Display' );
	$body    = get_theme_mod( 'kingdom_font_body', 'Inter' );
	$radius  = array(
		'sharp'   => '4px',
		'rounded' => '14px',
		'pill'    => '24px',
	);
	$r       = $radius[ get_theme_mod( 'kingdom_radius', 'rounded' ) ] ?? '14px';
	$width   = absint( get_theme_mod( 'kingdom_container', 1200 ) );
	$stack   = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	$hfont   = 'System' === $heading ? $stack : '"' . $heading . '", ' . ( in_array( $heading, array( 'Playfair Display', 'Cormorant Garamond', 'Lora' ), true ) ? 'Georgia, serif' : $stack );
	$bfont   = 'System' === $body ? $stack : '"' . $body . '", ' . $stack;

	return ':root{--k-primary:' . $primary . ';--k-accent:' . $accent . ';--k-dark:' . $dark . ';--k-radius:' . $r . ';--k-container:' . $width . 'px;--k-font-heading:' . $hfont . ';--k-font-body:' . $bfont . ';}';
}

/**
 * Body classes for layout options.
 *
 * @param array $classes Classes.
 * @return array
 */
function kingdom_body_classes( $classes ) {
	$classes[] = 'header-' . sanitize_html_class( get_theme_mod( 'kingdom_header_style', 'transparent' ) );
	if ( get_theme_mod( 'kingdom_header_sticky', true ) ) {
		$classes[] = 'has-sticky-header';
	}
	if ( kingdom_has_transparent_header() ) {
		$classes[] = 'has-transparent-header';
	}
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}
	return $classes;
}
add_filter( 'body_class', 'kingdom_body_classes' );

/**
 * Transparent header on pages that start with a hero.
 *
 * @return bool
 */
function kingdom_has_transparent_header() {
	if ( 'transparent' !== get_theme_mod( 'kingdom_header_style', 'transparent' ) ) {
		return false;
	}
	$hero_templates = array( 'page-templates/template-home-classic.php', 'page-templates/template-home-modern.php', 'page-templates/template-home-mega.php', 'page-templates/template-home-live.php', 'page-templates/template-home-split.php' );
	return is_front_page() || ( is_page() && in_array( get_page_template_slug(), $hero_templates, true ) );
}

/**
 * Apply the saved colour scheme before paint to avoid a flash.
 */
function kingdom_scheme_script() {
	$default = get_theme_mod( 'kingdom_color_scheme', 'light' );
	?>
	<script>(function(){try{var s=localStorage.getItem('kingdom-scheme')||'<?php echo esc_js( $default ); ?>';if(s==='auto'){s=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
	<?php
}
add_action( 'wp_head', 'kingdom_scheme_script', 0 );

/**
 * Drop the "Archives:" / "Category:" prefix from archive titles.
 *
 * @return string
 */
function kingdom_archive_title_prefix() {
	return '';
}
add_filter( 'get_the_archive_title_prefix', 'kingdom_archive_title_prefix' );

/**
 * Excerpt length.
 *
 * @return int
 */
function kingdom_excerpt_length() {
	return 24;
}
add_filter( 'excerpt_length', 'kingdom_excerpt_length' );

/**
 * Excerpt more.
 *
 * @return string
 */
function kingdom_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'kingdom_excerpt_more' );

/**
 * Lazy-load embeds in content and add rel to external links.
 *
 * @param string $html HTML.
 * @return string
 */
function kingdom_lazy_iframes( $html ) {
	return str_replace( '<iframe ', '<iframe loading="lazy" ', $html );
}
add_filter( 'embed_oembed_html', 'kingdom_lazy_iframes' );

/**
 * Admin notice recommending the companion plugin.
 */
function kingdom_plugin_notice() {
	if ( kingdom_plugin_active() || ! current_user_can( 'activate_plugins' ) || get_user_meta( get_current_user_id(), 'kingdom_dismiss_plugin', true ) ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( 'kingdom_dismiss_plugin', 1 ), 'kingdom_dismiss' );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Kingdom Church theme:', 'kingdom' ) . '</strong> ' . esc_html__( 'activate the Kingdom Church Suite plugin to enable partnerships, online giving, live streaming, sermons, events, newsletters and SEO.', 'kingdom' ) . ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go to plugins', 'kingdom' ) . '</a> · <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'kingdom' ) . '</a></p></div>';
}
add_action( 'admin_notices', 'kingdom_plugin_notice' );

/**
 * Handle notice dismissal.
 */
function kingdom_dismiss_notice() {
	if ( isset( $_GET['kingdom_dismiss_plugin'] ) && check_admin_referer( 'kingdom_dismiss' ) ) {
		update_user_meta( get_current_user_id(), 'kingdom_dismiss_plugin', 1 );
	}
}
add_action( 'admin_init', 'kingdom_dismiss_notice' );
