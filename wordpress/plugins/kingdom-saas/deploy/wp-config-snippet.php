<?php
/**
 * Add these lines to wp-config.php (above "That's all, stop editing!").
 * The network setup screen (Tools → Network Setup) gives you the first block.
 */
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', true );
define( 'DOMAIN_CURRENT_SITE', 'yourplatform.com' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );

// Custom domains (copy wp-content/plugins/kingdom-saas/deploy/sunrise.php to wp-content/sunrise.php).
define( 'SUNRISE', true );

// Run billing checks from a real cron job instead of page visits:
//   */15 * * * * curl -s https://yourplatform.com/wp-cron.php?doing_wp_cron >/dev/null
define( 'DISABLE_WP_CRON', true );
