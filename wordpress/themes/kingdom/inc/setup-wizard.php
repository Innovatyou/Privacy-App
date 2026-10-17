<?php
/**
 * Appearance → Kingdom Setup: one-click starter pages, menus and front page.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Starter pages: key => [title, template, content, plugin page key].
 *
 * @return array
 */
function kingdom_starter_pages() {
	return array(
		'home'        => array( __( 'Home', 'kingdom' ), 'template-home-classic.php', '', '' ),
		'about'       => array( __( 'About Us', 'kingdom' ), 'template-about.php', '<h2>Our story</h2><p>Tell your church story here — how it started, the vision God gave, and where you are going.</p><h2>Our mission</h2><p>To love God, love people and make disciples of all nations.</p>', '' ),
		'leadership'  => array( __( 'Leadership', 'kingdom' ), 'template-leadership.php', '<p>Meet the pastors and leaders who serve our church family.</p>', '' ),
		'beliefs'     => array( __( 'What We Believe', 'kingdom' ), 'template-beliefs.php', "<h3>The Bible</h3><p>We believe the Bible is the inspired, infallible Word of God.</p><h3>God</h3><p>We believe in one God, eternally existing in three persons: Father, Son and Holy Spirit.</p><h3>Jesus Christ</h3><p>We believe in the deity of Jesus, His virgin birth, sinless life, atoning death, bodily resurrection and soon return.</p><h3>Salvation</h3><p>We believe salvation is by grace through faith in Jesus Christ alone.</p><h3>The Holy Spirit</h3><p>We believe in the present ministry of the Holy Spirit who empowers believers for godly living and service.</p><h3>The Church</h3><p>We believe the Church is the body of Christ, called to worship, fellowship, discipleship and mission.</p>", '' ),
		'ministries'  => array( __( 'Ministries', 'kingdom' ), 'template-ministries.php', '', '' ),
		'sermons'     => array( __( 'Sermons', 'kingdom' ), 'template-sermons.php', '', '' ),
		'events'      => array( __( 'Events', 'kingdom' ), 'template-events.php', '', '' ),
		'watch'       => array( __( 'Watch Live', 'kingdom' ), 'template-watch-live.php', '', 'watch_live' ),
		'give'        => array( __( 'Give Online', 'kingdom' ), 'template-give.php', '', 'give' ),
		'partnership' => array( __( 'Partnership', 'kingdom' ), 'template-partnership.php', '', 'partnership' ),
		'portal'      => array( __( 'Partner Dashboard', 'kingdom' ), 'template-partner-portal.php', '[kcs_partner_dashboard]', 'partner_dashboard' ),
		'visit'       => array( __( 'Plan Your Visit', 'kingdom' ), 'template-plan-visit.php', '', '' ),
		'new'         => array( __( "I'm New", 'kingdom' ), 'template-new-here.php', '', '' ),
		'prayer'      => array( __( 'Prayer Request', 'kingdom' ), 'template-prayer.php', '', 'prayer' ),
		'testimonies' => array( __( 'Testimonies', 'kingdom' ), 'template-testimonies.php', '', '' ),
		'contact'     => array( __( 'Contact', 'kingdom' ), 'template-contact.php', '', '' ),
		'blog'        => array( __( 'News', 'kingdom' ), '', '', '' ),
	);
}

/**
 * Admin page.
 */
function kingdom_setup_menu() {
	add_theme_page( __( 'Kingdom Setup', 'kingdom' ), __( 'Kingdom Setup', 'kingdom' ), 'edit_theme_options', 'kingdom-setup', 'kingdom_setup_page' );
}
add_action( 'admin_menu', 'kingdom_setup_menu' );

/**
 * Render setup page.
 */
function kingdom_setup_page() {
	$templates = wp_get_theme()->get_page_templates();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Kingdom Church — Setup', 'kingdom' ); ?></h1>
		<?php if ( isset( $_GET['done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Starter site created! Visit your homepage, then customise everything under Appearance → Customize → Kingdom Church Theme.', 'kingdom' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View site →', 'kingdom' ); ?></a></p></div>
		<?php endif; ?>
		<div class="card" style="max-width:780px">
			<h2><?php esc_html_e( '1. Install the companion plugin', 'kingdom' ); ?></h2>
			<p><?php echo kingdom_plugin_active() ? '✅ ' . esc_html__( 'Kingdom Church Suite is active.', 'kingdom' ) : '⚠️ ' . esc_html__( 'Upload and activate the Kingdom Church Suite plugin for partnerships, giving, live streaming, sermons, events, newsletter and SEO.', 'kingdom' ); ?></p>
			<h2><?php esc_html_e( '2. Create the starter site', 'kingdom' ); ?></h2>
			<p><?php esc_html_e( 'Creates (or reuses) all key church pages with their templates, builds the main and footer menus, and sets the homepage and blog page. Existing content is never deleted.', 'kingdom' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kingdom_setup">
				<?php wp_nonce_field( 'kingdom_setup' ); ?>
				<p><label><?php esc_html_e( 'Homepage layout', 'kingdom' ); ?>
					<select name="home_template">
						<?php foreach ( $templates as $file => $name ) : ?>
							<?php if ( false !== strpos( $file, 'template-home-' ) ) : ?>
								<option value="<?php echo esc_attr( basename( $file ) ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select></label></p>
				<?php submit_button( __( 'Create starter site', 'kingdom' ) ); ?>
			</form>
			<h2><?php esc_html_e( '3. Available page templates', 'kingdom' ); ?></h2>
			<p><?php esc_html_e( 'Choose any of these from Page → Template in the editor:', 'kingdom' ); ?></p>
			<ul style="columns:2">
				<?php foreach ( $templates as $name ) : ?>
					<li><?php echo esc_html( $name ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php
}

/**
 * Create the starter site.
 */
function kingdom_setup_run() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'kingdom' ) );
	}
	check_admin_referer( 'kingdom_setup' );
	$home_tpl = isset( $_POST['home_template'] ) ? sanitize_file_name( wp_unslash( $_POST['home_template'] ) ) : 'template-home-classic.php';
	if ( ! file_exists( KINGDOM_DIR . '/page-templates/' . $home_tpl ) ) {
		$home_tpl = 'template-home-classic.php';
	}

	// When the plugin provides an archive for these, link to it instead of creating a page with the same slug.
	$archives = array(
		'sermons'     => 'kcs_sermon',
		'events'      => 'kcs_event',
		'ministries'  => 'kcs_ministry',
		'leadership'  => 'kcs_leader',
		'testimonies' => 'kcs_testimony',
	);
	$ids      = array();
	foreach ( kingdom_starter_pages() as $key => $p ) {
		if ( isset( $archives[ $key ] ) && post_type_exists( $archives[ $key ] ) ) {
			continue;
		}
		$id = 0;
		// Reuse pages created by the plugin.
		if ( $p[3] && function_exists( 'kcs_get_option' ) ) {
			$plugin_id = (int) kcs_get_option( 'page_' . $p[3], 0 );
			if ( $plugin_id && get_post_status( $plugin_id ) ) {
				$id = $plugin_id;
			}
		}
		if ( ! $id ) {
			$existing = get_page_by_path( sanitize_title( $p[0] ) );
			$id       = $existing ? $existing->ID : 0;
		}
		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_title'   => $p[0],
					'post_content' => $p[2],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}
		if ( $id && ! is_wp_error( $id ) ) {
			$template = 'home' === $key ? $home_tpl : $p[1];
			if ( $template ) {
				update_post_meta( $id, '_wp_page_template', 'page-templates/' . $template );
			}
			$ids[ $key ] = $id;
		}
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['home'] );
	update_option( 'page_for_posts', $ids['blog'] );

	// Menus.
	$primary = array( 'home', 'about' => array( 'leadership', 'beliefs', 'testimonies' ), 'ministries', 'sermons', 'events', 'watch', 'partnership', 'contact' );
	$menus   = array(
		'primary' => array( __( 'Main Menu', 'kingdom' ), $primary ),
		'footer'  => array( __( 'Footer Menu', 'kingdom' ), array( 'new', 'visit', 'prayer', 'give', 'portal', 'blog' ) ),
	);
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( $menus as $location => $menu ) {
		$obj     = wp_get_nav_menu_object( $menu[0] );
		$menu_id = $obj ? $obj->term_id : wp_create_nav_menu( $menu[0] );
		if ( is_wp_error( $menu_id ) ) {
			continue;
		}
		if ( ! $obj ) {
			$item = function ( $key, $parent = 0 ) use ( $menu_id, $ids, $archives ) {
				if ( isset( $ids[ $key ] ) ) {
					$args = array( 'menu-item-object-id' => $ids[ $key ], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type' );
				} elseif ( isset( $archives[ $key ] ) && post_type_exists( $archives[ $key ] ) ) {
					$args = array( 'menu-item-object' => $archives[ $key ], 'menu-item-type' => 'post_type_archive', 'menu-item-title' => get_post_type_object( $archives[ $key ] )->labels->name );
				} else {
					return 0;
				}
				return wp_update_nav_menu_item( $menu_id, 0, array_merge( $args, array( 'menu-item-parent-id' => $parent, 'menu-item-status' => 'publish' ) ) );
			};
			foreach ( $menu[1] as $k => $v ) {
				$parent = $item( is_array( $v ) ? $k : $v );
				if ( is_array( $v ) ) {
					foreach ( $v as $child ) {
						$item( $child, $parent );
					}
				}
			}
		}
		$locations[ $location ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
	if ( ! get_theme_mod( 'kingdom_header_cta_url' ) ) {
		set_theme_mod( 'kingdom_header_cta_url', get_permalink( $ids['give'] ) );
	}

	wp_safe_redirect( admin_url( 'themes.php?page=kingdom-setup&done=1' ) );
	exit;
}
add_action( 'admin_post_kingdom_setup', 'kingdom_setup_run' );

/**
 * Point to the setup screen after activation.
 */
function kingdom_after_switch() {
	set_transient( 'kingdom_activated', 1, 60 );
}
add_action( 'after_switch_theme', 'kingdom_after_switch' );

/**
 * Activation notice.
 */
function kingdom_activation_notice() {
	if ( ! get_transient( 'kingdom_activated' ) ) {
		return;
	}
	delete_transient( 'kingdom_activated' );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Thanks for choosing Kingdom Church!', 'kingdom' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'themes.php?page=kingdom-setup' ) ) . '">' . esc_html__( 'Run the one-click setup', 'kingdom' ) . '</a></p></div>';
}
add_action( 'admin_notices', 'kingdom_activation_notice' );
