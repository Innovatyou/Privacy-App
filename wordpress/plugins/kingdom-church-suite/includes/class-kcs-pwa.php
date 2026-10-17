<?php
/**
 * Church app (Progressive Web App) for Android and iPhone.
 *
 * - Web app manifest, icons (any + maskable), iPhone home-screen icon and launch screens.
 * - Service worker: works offline, loads instantly from cache, receives push notifications.
 * - Install prompts: Android "Install app" button, iPhone "Add to Home Screen" guide.
 * - App-like behaviour when opened from the home screen (see assets/js/pwa.js).
 *
 * Everything is served from the site's own address, so each church (including SaaS sites on
 * custom domains) gets its own app.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * PWA.
 */
class KCS_PWA {

	/**
	 * iPhone launch screen sizes: array( css width, css height, pixel ratio ).
	 *
	 * @var array
	 */
	const SPLASH = array( array( 440, 956, 3 ), array( 402, 874, 3 ), array( 430, 932, 3 ), array( 393, 852, 3 ), array( 428, 926, 3 ), array( 390, 844, 3 ), array( 375, 812, 3 ), array( 414, 896, 3 ), array( 414, 896, 2 ), array( 414, 736, 3 ), array( 375, 667, 2 ) );

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! self::enabled() ) {
			// App switched off: phones that installed it get a worker that removes itself.
			add_action( 'init', array( __CLASS__, 'retire' ), 0 );
			return;
		}
		add_action( 'init', array( __CLASS__, 'serve' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'send_headers', array( __CLASS__, 'cache_header' ) );
	}

	/**
	 * Is the app switched on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '1' === (string) kcs_get_option( 'pwa_enabled', '1' );
	}

	/**
	 * App name.
	 *
	 * @param bool $short Short name for under the icon.
	 * @return string
	 */
	public static function name( $short = false ) {
		$name = kcs_get_option( 'pwa_name', kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) );
		if ( ! $short ) {
			return $name;
		}
		$s = kcs_get_option( 'pwa_short_name', '' );
		if ( $s ) {
			return $s;
		}
		// Whole words that fit under the icon ("Grace Chapel International" → "Grace Chapel").
		$short = '';
		foreach ( preg_split( '/\s+/', trim( $name ) ) as $word ) {
			if ( mb_strlen( trim( $short . ' ' . $word ) ) > 14 ) {
				break;
			}
			$short = trim( $short . ' ' . $word );
		}
		return $short ? $short : mb_substr( $name, 0, 14 );
	}

	/**
	 * Brand colours from the theme (main, dark).
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function colours() {
		$c = function_exists( 'kingdom_brand_colors' ) ? kingdom_brand_colors() : array( '#5b2a86', '#f4a62a', '#16121c' );
		return (array) apply_filters( 'kcs_pwa_colours', array( $c[0] ? $c[0] : '#5b2a86', $c[2] ? $c[2] : '#16121c' ) );
	}

	/**
	 * URL of an app endpoint.
	 *
	 * @param string $what manifest|sw|offline|icon|splash.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $what, $args = array() ) {
		return add_query_arg( array_merge( array( 'kcs_pwa' => $what ), $args ), home_url( '/' ) );
	}

	/**
	 * Version string that changes whenever the app's settings or files change.
	 *
	 * @return string
	 */
	public static function version() {
		static $v = null;
		if ( null !== $v ) {
			return $v;
		}
		$v = substr( md5( KCS_VERSION . filemtime( KCS_PATH . 'assets/js/pwa.js' ) . filemtime( __FILE__ ) . wp_json_encode( array( self::name(), self::name( true ), self::colours(), self::logo_id(), self::logo_id() ? filemtime( get_attached_file( self::logo_id() ) ) : 0 ) ) ), 0, 10 );
		return $v;
	}

	/**
	 * Answer app requests (?kcs_pwa=…).
	 */
	public static function serve() {
		if ( empty( $_GET['kcs_pwa'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$what = sanitize_key( $_GET['kcs_pwa'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		switch ( $what ) {
			case 'manifest':
				self::send( 'application/manifest+json', wp_json_encode( self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), 3600 );
				break;
			case 'sw':
				header( 'Service-Worker-Allowed: /' );
				self::send( 'application/javascript', self::service_worker(), 0 );
				break;
			case 'offline':
				self::send( 'text/html; charset=utf-8', self::offline_page(), 3600 );
				break;
			case 'icon':
				$size = in_array( (int) ( $_GET['size'] ?? 0 ), array( 180, 192, 512 ), true ) ? (int) $_GET['size'] : 192; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				self::image( 'icon', $size, $size, ! empty( $_GET['maskable'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'splash':
				$w = absint( $_GET['w'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$h = absint( $_GET['h'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$ok = false;
				foreach ( self::SPLASH as $s ) {
					$ok = $ok || ( $s[0] * $s[2] === $w && $s[1] * $s[2] === $h );
				}
				if ( ! $ok ) {
					status_header( 404 );
					exit;
				}
				self::image( 'splash', $w, $h, false );
				break;
		}
	}

	/**
	 * Service worker that clears the app's caches and unregisters itself.
	 */
	public static function retire() {
		if ( 'sw' !== ( $_GET['kcs_pwa'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		header( 'Service-Worker-Allowed: /' );
		self::send( 'application/javascript', "self.addEventListener('install',function(){self.skipWaiting();});self.addEventListener('activate',function(e){e.waitUntil(caches.keys().then(function(k){return Promise.all(k.filter(function(n){return n.indexOf('kcs-')===0;}).map(function(n){return caches.delete(n);}));}).then(function(){return self.registration.unregister();}));});", 0 );
	}

	/**
	 * Send a response and stop.
	 *
	 * @param string $type    Content type.
	 * @param string $body    Body.
	 * @param int    $max_age Browser cache seconds.
	 */
	private static function send( $type, $body, $max_age ) {
		status_header( 200 );
		header( 'Content-Type: ' . $type );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: ' . ( $max_age ? 'public, max-age=' . $max_age : 'no-cache' ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Web app manifest.
	 *
	 * @return array
	 */
	public static function manifest() {
		list( $main, $dark ) = self::colours();
		$v                   = self::version();
		$shortcuts           = array();
		$pages               = array(
			'give'              => array( __( 'Give', 'kingdom-church-suite' ), __( 'Tithe, offering & seed', 'kingdom-church-suite' ) ),
			'watch_live'        => array( __( 'Watch live', 'kingdom-church-suite' ), __( 'Join the service online', 'kingdom-church-suite' ) ),
			'prayer'            => array( __( 'Prayer request', 'kingdom-church-suite' ), __( 'We will pray with you', 'kingdom-church-suite' ) ),
			'partner_dashboard' => array( __( 'My partnership', 'kingdom-church-suite' ), __( 'Partner dashboard', 'kingdom-church-suite' ) ),
		);
		foreach ( $pages as $key => $p ) {
			$id = (int) kcs_get_option( 'page_' . $key, 0 );
			if ( $id && 'publish' === get_post_status( $id ) ) {
				$shortcuts[] = array( 'name' => $p[0], 'short_name' => $p[0], 'description' => $p[1], 'url' => wp_make_link_relative( get_permalink( $id ) ) . '?utm_source=app', 'icons' => array( array( 'src' => self::url( 'icon', array( 'size' => 192, 'v' => $v ) ), 'sizes' => '192x192', 'type' => 'image/png' ) ) );
			}
		}
		$path = wp_make_link_relative( home_url( '/' ) );
		return apply_filters(
			'kcs_pwa_manifest',
			array(
				'id'               => $path,
				'name'             => self::name(),
				'short_name'       => self::name( true ),
				'description'      => get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : self::name(),
				'lang'             => get_bloginfo( 'language' ),
				'dir'              => is_rtl() ? 'rtl' : 'ltr',
				'start_url'        => $path . '?utm_source=app',
				'scope'            => $path,
				'display'          => 'standalone',
				'orientation'      => 'any',
				'theme_color'      => $main,
				'background_color' => $main,
				'categories'       => array( 'lifestyle', 'education', 'social' ),
				'icons'            => array(
					array( 'src' => self::url( 'icon', array( 'size' => 192, 'v' => $v ) ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
					array( 'src' => self::url( 'icon', array( 'size' => 512, 'v' => $v ) ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
					array( 'src' => self::url( 'icon', array( 'size' => 512, 'maskable' => 1, 'v' => $v ) ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
				),
				'shortcuts'        => $shortcuts,
			)
		);
	}

	/**
	 * Head tags: manifest, colours, iPhone home-screen app tags and launch screens.
	 */
	public static function head() {
		list( $main ) = self::colours();
		$v            = self::version();
		echo "\n" . '<link rel="manifest" href="' . esc_url( self::url( 'manifest', array( 'v' => $v ) ) ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( $main ) . '">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( self::name( true ) ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( self::url( 'icon', array( 'size' => 180, 'v' => $v ) ) ) . '">' . "\n";
		foreach ( self::SPLASH as $s ) {
			printf(
				'<link rel="apple-touch-startup-image" media="screen and (device-width: %1$dpx) and (device-height: %2$dpx) and (-webkit-device-pixel-ratio: %3$d) and (orientation: portrait)" href="%4$s">' . "\n",
				(int) $s[0],
				(int) $s[1],
				(int) $s[2],
				esc_url( self::url( 'splash', array( 'w' => $s[0] * $s[2], 'h' => $s[1] * $s[2], 'v' => $v ) ) )
			);
		}
	}

	/**
	 * Front-end app script and styles.
	 */
	public static function assets() {
		wp_enqueue_style( 'kcs-pwa', KCS_URL . 'assets/css/pwa.css', array(), KCS_VERSION );
		wp_enqueue_script( 'kcs-pwa', KCS_URL . 'assets/js/pwa.js', array(), filemtime( KCS_PATH . 'assets/js/pwa.js' ), true );
		$ios_steps = __( 'Tap the Share button, then “Add to Home Screen”.', 'kingdom-church-suite' );
		wp_localize_script(
			'kcs-pwa',
			'KCS_PWA',
			array(
				'sw'        => self::url( 'sw', array( 'v' => self::version() ) ),
				'scope'     => wp_make_link_relative( home_url( '/' ) ),
				'name'      => self::name(),
				'icon'      => self::url( 'icon', array( 'size' => 192, 'v' => self::version() ) ),
				'vapid'     => KCS_Push::keys()['public'],
				'subscribe' => esc_url_raw( rest_url( 'kcs/v1/push/subscribe' ) ),
				'unsub'     => esc_url_raw( rest_url( 'kcs/v1/push/unsubscribe' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'banner'    => '1' === (string) kcs_get_option( 'pwa_banner', '1' ),
				'i18n'      => array(
					/* translators: %s: church name */
					'install_title' => sprintf( __( 'Get the %s app', 'kingdom-church-suite' ), self::name() ),
					'install_text'  => __( 'Watch live, give and request prayer in one tap — even with a weak connection.', 'kingdom-church-suite' ),
					'install'       => __( 'Install app', 'kingdom-church-suite' ),
					'not_now'       => __( 'Not now', 'kingdom-church-suite' ),
					'ios_title'     => __( 'Add this app to your iPhone', 'kingdom-church-suite' ),
					'ios_steps'     => $ios_steps,
					'ios_step1'     => __( 'Tap the Share button', 'kingdom-church-suite' ),
					'ios_step2'     => __( 'Choose “Add to Home Screen”', 'kingdom-church-suite' ),
					'ios_step3'     => __( 'Tap “Add”', 'kingdom-church-suite' ),
					'got_it'        => __( 'Got it', 'kingdom-church-suite' ),
					'notify_title'  => __( 'Turn on notifications?', 'kingdom-church-suite' ),
					'notify_text'   => __( 'Know the moment we go live, plus important church news. You can turn them off any time.', 'kingdom-church-suite' ),
					'notify_on'     => __( 'Turn on', 'kingdom-church-suite' ),
					'notify_done'   => __( 'Notifications are on.', 'kingdom-church-suite' ),
					'notify_off'    => __( 'Notifications are off.', 'kingdom-church-suite' ),
					'notify_denied' => __( 'Notifications are blocked. Turn them on in your phone’s settings for this app.', 'kingdom-church-suite' ),
					'notify_ios'    => __( 'On iPhone, add the app to your Home Screen first, then turn on notifications from inside the app.', 'kingdom-church-suite' ),
					'offline'       => __( 'You are offline. Showing saved pages.', 'kingdom-church-suite' ),
					'online'        => __( 'Back online.', 'kingdom-church-suite' ),
					'update'        => __( 'A new version is ready.', 'kingdom-church-suite' ),
					'reload'        => __( 'Refresh', 'kingdom-church-suite' ),
					'back'          => __( 'Back', 'kingdom-church-suite' ),
					'pull'          => __( 'Release to refresh', 'kingdom-church-suite' ),
				),
			)
		);
	}

	/**
	 * Tell the service worker which pages may be saved for offline use: public pages for
	 * visitors who are not logged in (never dashboards, admin or checkout).
	 */
	public static function cache_header() {
		if ( is_admin() || is_user_logged_in() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		header( 'X-KCS-Offline: 1' );
	}

	/**
	 * Service worker script.
	 *
	 * @return string
	 */
	public static function service_worker() {
		$theme  = get_stylesheet_directory_uri();
		$assets = array_values(
			array_filter(
				array(
					self::url( 'offline', array( 'v' => self::version() ) ),
					self::url( 'icon', array( 'size' => 192, 'v' => self::version() ) ),
					KCS_URL . 'assets/css/public.css?ver=' . KCS_VERSION,
					KCS_URL . 'assets/js/public.js?ver=' . KCS_VERSION,
					file_exists( get_stylesheet_directory() . '/assets/css/main.css' ) ? $theme . '/assets/css/main.css' : '',
				)
			)
		);
		$config = array(
			'version' => self::version(),
			'offline' => self::url( 'offline', array( 'v' => self::version() ) ),
			'precache' => $assets,
			'never'   => array( '/wp-admin', '/wp-login.php', '/wp-json/', 'kcs_pwa=sw', 'preview=', 'wp-cron.php', 'xmlrpc.php', '/church-admin', '/partner-dashboard', '/thank-you', '?kcs_action', '&kcs_action', 'ks_login=' ),
		);
		return '/* ' . self::name() . ' app — service worker */' . "\nconst KCS = " . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n" . file_get_contents( KCS_PATH . 'assets/js/sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Page shown when the app is opened without a connection and the page was never saved.
	 *
	 * @return string
	 */
	public static function offline_page() {
		list( $main, $dark ) = self::colours();
		$times               = class_exists( 'KCS_Livestream' ) ? KCS_Livestream::schedule() : array();
		$list                = '';
		foreach ( array_slice( $times, 0, 4 ) as $t ) {
			$list .= '<li><strong>' . esc_html( $t['name'] ) . '</strong><span>' . esc_html( $t['day'] . ' · ' . $t['time'] ) . '</span></li>';
		}
		ob_start();
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="<?php echo esc_attr( $main ); ?>">
<title><?php echo esc_html( self::name() ); ?> — <?php esc_html_e( 'Offline', 'kingdom-church-suite' ); ?></title>
<style>
:root{--m:<?php echo esc_html( $main ); ?>;--d:<?php echo esc_html( $dark ); ?>;color-scheme:light dark}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:max(24px,env(safe-area-inset-top)) 20px max(24px,env(safe-area-inset-bottom));font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--m);color:#fff}
.card{width:min(440px,100%);background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:22px;padding:28px 22px;text-align:center;backdrop-filter:blur(8px)}
img{width:72px;height:72px;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}h1{font-size:1.4rem;margin:16px 0 6px}p{margin:0 0 18px;opacity:.85}
ul{list-style:none;padding:0;margin:0 0 20px;text-align:left;display:grid;gap:8px}li{display:flex;justify-content:space-between;gap:12px;padding:10px 14px;border-radius:12px;background:rgba(0,0,0,.18);font-size:.92rem}
.saved{display:grid;gap:8px;margin:0 0 20px;text-align:left}.saved a{color:#fff;padding:10px 14px;border-radius:12px;background:rgba(255,255,255,.12);text-decoration:none;font-weight:600}
button{border:0;border-radius:999px;padding:12px 22px;font:700 1rem inherit;background:#fff;color:var(--m);cursor:pointer}
</style>
</head>
<body>
<main class="card">
	<img src="<?php echo esc_url( self::url( 'icon', array( 'size' => 192, 'v' => self::version() ) ) ); ?>" alt="">
	<h1><?php esc_html_e( 'You are offline', 'kingdom-church-suite' ); ?></h1>
	<p><?php esc_html_e( 'Check your connection. Pages you opened before are still available below.', 'kingdom-church-suite' ); ?></p>
	<div class="saved" id="saved"></div>
	<?php if ( $list ) : ?><ul><?php echo $list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul><?php endif; ?>
	<button type="button" onclick="location.reload()"><?php esc_html_e( 'Try again', 'kingdom-church-suite' ); ?></button>
</main>
<script>
caches.open('kcs-pages').then(function (c) { return c.keys(); }).then(function (keys) {
	var box = document.getElementById('saved');
	keys.slice(0, 6).forEach(function (r) { var u = new URL(r.url); var a = document.createElement('a'); a.href = u.pathname + u.search; a.textContent = decodeURIComponent(u.pathname === '/' ? '<?php echo esc_js( __( 'Home', 'kingdom-church-suite' ) ); ?>' : u.pathname.replace(/\/$/, '').split('/').pop().replace(/-/g, ' ')); box.appendChild(a); });
}).catch(function () {});
window.addEventListener('online', function () { location.reload(); });
</script>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Draw (and cache) an app icon or iPhone launch screen.
	 *
	 * @param string $kind     icon|splash.
	 * @param int    $w        Width.
	 * @param int    $h        Height.
	 * @param bool   $maskable Leave Android's safe zone around the artwork.
	 */
	private static function image( $kind, $w, $h, $maskable ) {
		$dir  = wp_upload_dir();
		$base = trailingslashit( $dir['basedir'] ) . 'kcs-app';
		$file = $base . '/' . $kind . '-' . $w . 'x' . $h . ( $maskable ? '-m' : '' ) . '-' . self::version() . '.png';
		if ( ! file_exists( $file ) && function_exists( 'imagecreatetruecolor' ) ) {
			wp_mkdir_p( $base );
			// Logo or colours changed: remove images from older versions.
			foreach ( (array) glob( $base . '/*.png' ) as $old ) {
				if ( $old && false === strpos( $old, '-' . self::version() . '.png' ) ) {
					wp_delete_file( $old );
				}
			}
			list( $main ) = self::colours();
			$img          = imagecreatetruecolor( $w, $h );
			$rgb          = sscanf( ltrim( $main, '#' ), '%02x%02x%02x' );
			imagefill( $img, 0, 0, imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] ) );
			$art   = (int) round( 'splash' === $kind ? min( $w, $h ) * 0.34 : ( $maskable ? $w * 0.62 : $w * 0.78 ) );
			$logo  = self::source_image();
			$x     = (int) ( ( $w - $art ) / 2 );
			$y     = (int) ( ( $h - $art ) / ( 'splash' === $kind ? 2.3 : 2 ) );
			if ( $logo ) {
				// Fit the logo inside the artwork box, keeping its shape (wide logos stay wide).
				$lw    = imagesx( $logo );
				$lh    = imagesy( $logo );
				// Launch screens give wide logos more room across the screen.
				$bw    = 'splash' === $kind ? (int) round( $w * 0.64 ) : $art;
				$scale = min( $bw / $lw, $art / $lh );
				$dw    = (int) round( $lw * $scale );
				$dh    = (int) round( $lh * $scale );
				imagealphablending( $img, true );
				imagecopyresampled( $img, $logo, (int) ( ( $w - $dw ) / 2 ), $y + (int) ( ( $art - $dh ) / 2 ), 0, 0, $dw, $dh, $lw, $lh );
			} else {
				self::draw_cross( $img, $x, $y, $art );
			}
			imagepng( $img, $file, 9 );
		}
		if ( ! file_exists( $file ) ) {
			status_header( 404 );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: public, max-age=31536000, immutable' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Attachment ID of the church's artwork, in order: App icon setting, site icon, the theme
	 * logo, then the Church Suite logo. Each church's app therefore carries its own logo.
	 *
	 * @return int
	 */
	public static function logo_id() {
		foreach ( array( kcs_get_option( 'pwa_icon', '' ), (int) get_option( 'site_icon' ), (int) get_theme_mod( 'custom_logo' ), kcs_get_option( 'church_logo', '' ) ) as $src ) {
			$id = is_numeric( $src ) ? (int) $src : ( $src ? attachment_url_to_postid( (string) $src ) : 0 );
			if ( $id && get_attached_file( $id ) ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * The church's logo as an image (any shape: it is fitted, never cropped).
	 *
	 * @return GdImage|resource|null
	 */
	private static function source_image() {
		$id   = self::logo_id();
		$path = $id ? get_attached_file( $id ) : '';
		if ( ! $path || ! file_exists( $path ) ) {
			return null;
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $src ? $src : null;
	}

	/**
	 * Default artwork: a white cross on a softly lighter tile.
	 *
	 * @param GdImage|resource $img  Image.
	 * @param int              $x    Left.
	 * @param int              $y    Top.
	 * @param int              $size Size.
	 */
	private static function draw_cross( $img, $x, $y, $size ) {
		$tile  = imagecolorallocatealpha( $img, 255, 255, 255, 105 );
		$white = imagecolorallocate( $img, 255, 255, 255 );
		$r     = (int) ( $size * 0.22 );
		imagefilledrectangle( $img, $x + $r, $y, $x + $size - $r, $y + $size, $tile );
		imagefilledrectangle( $img, $x, $y + $r, $x + $size, $y + $size - $r, $tile );
		foreach ( array( array( $x + $r, $y + $r ), array( $x + $size - $r, $y + $r ), array( $x + $r, $y + $size - $r ), array( $x + $size - $r, $y + $size - $r ) ) as $c ) {
			imagefilledellipse( $img, $c[0], $c[1], $r * 2, $r * 2, $tile );
		}
		$t  = (int) ( $size * 0.13 );
		$cx = (int) ( $x + $size / 2 );
		imagefilledrectangle( $img, $cx - (int) ( $t / 2 ), $y + (int) ( $size * 0.18 ), $cx + (int) ( $t / 2 ), $y + (int) ( $size * 0.82 ), $white );
		imagefilledrectangle( $img, $x + (int) ( $size * 0.28 ), $y + (int) ( $size * 0.36 ), $x + (int) ( $size * 0.72 ), $y + (int) ( $size * 0.36 ) + $t, $white );
	}
}
