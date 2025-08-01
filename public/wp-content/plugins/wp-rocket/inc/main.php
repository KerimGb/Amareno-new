<?php

use WP_Rocket\Addon\Cloudflare\Cloudflare;
use WP_Rocket\Dependencies\League\Container\Container;
use WP_Rocket\Plugin;

defined( 'ABSPATH' ) || exit;

// Composer autoload.
if ( file_exists( WP_ROCKET_PATH . 'vendor/autoload.php' ) ) {
	require WP_ROCKET_PATH . 'vendor/autoload.php';
}

require_once WP_ROCKET_FUNCTIONS_PATH . 'files.php';

Cloudflare::fix_cf_flexible_ssl();

require_once WP_ROCKET_INC_PATH . 'Dependencies' . DIRECTORY_SEPARATOR . 'ActionScheduler' . DIRECTORY_SEPARATOR . 'action-scheduler.php';

/**
 * Tell WP what to do when plugin is loaded.
 *
 * @since 1.0
 */
function rocket_init() {
	// Nothing to do if autosave.
	if ( defined( 'DOING_AUTOSAVE' ) ) {
		return;
	}

	/**
	 * Fires when WP Rocket starts to load.
	 */
	do_action( 'wp_rocket_before_load' );

	// Call defines and functions.
	require WP_ROCKET_FUNCTIONS_PATH . 'options.php';

	// Last constants.
	define( 'WP_ROCKET_PLUGIN_NAME', 'WP Rocket' );
	define( 'WP_ROCKET_PLUGIN_SLUG', sanitize_key( WP_ROCKET_PLUGIN_NAME ) );

	require WP_ROCKET_INC_PATH . '/API/bypass.php';

	$wp_rocket = new Plugin(
		WP_ROCKET_PATH . 'views',
		new Container()
	);
	$wp_rocket->load();

	// Call defines and functions.
	require_once WP_ROCKET_FUNCTIONS_PATH . 'api.php';
	require WP_ROCKET_FUNCTIONS_PATH . 'posts.php';
	require WP_ROCKET_FUNCTIONS_PATH . 'admin.php';
	require WP_ROCKET_FUNCTIONS_PATH . 'formatting.php';
	require WP_ROCKET_FUNCTIONS_PATH . 'i18n.php';
	require WP_ROCKET_FUNCTIONS_PATH . 'htaccess.php';
	require WP_ROCKET_DEPRECATED_PATH . 'deprecated.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.2.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.3.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.4.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.5.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.6.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.7.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.8.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.9.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.10.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.11.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.12.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.13.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.14.php';
	require WP_ROCKET_DEPRECATED_PATH . '3.15.php';
	require WP_ROCKET_3RD_PARTY_PATH . '3rd-party.php';
	require WP_ROCKET_COMMON_PATH . 'admin-bar.php';

	if ( rocket_valid_key() ) {
		require WP_ROCKET_COMMON_PATH . 'purge.php';

		if ( is_multisite() && defined( 'SUNRISE' ) && SUNRISE === 'on' && function_exists( 'domain_mapping_siteurl' ) ) {
			require WP_ROCKET_INC_PATH . '/domain-mapping.php';
		}
	}

	if ( is_admin() ) {
		require WP_ROCKET_ADMIN_PATH . 'upgrader.php';
		require WP_ROCKET_ADMIN_PATH . 'options.php';
		require WP_ROCKET_ADMIN_PATH . 'admin.php';
		require WP_ROCKET_ADMIN_UI_PATH . 'enqueue.php';
		require WP_ROCKET_ADMIN_UI_PATH . 'notices.php';
		require WP_ROCKET_ADMIN_UI_PATH . 'meta-boxes.php';
	} elseif ( rocket_valid_key() ) {
		require WP_ROCKET_FRONT_PATH . 'cookie.php';
		require WP_ROCKET_FRONT_PATH . 'dns-prefetch.php';
	}

	// You can hook this to trigger any action when WP Rocket is correctly loaded, so, not in AUTOSAVE mode.
	if ( rocket_valid_key() ) {
		/**
		 * Fires when WP Rocket is correctly loaded
		 *
		 * @since 1.0
		*/
		do_action( 'wp_rocket_loaded' );
	}
}
add_action( 'plugins_loaded', 'rocket_init' );

register_deactivation_hook( WP_ROCKET_FILE, [ 'WP_Rocket\Engine\Deactivation\Deactivation', 'deactivate_plugin' ] );
register_activation_hook( WP_ROCKET_FILE, [ 'WP_Rocket\Engine\Activation\Activation', 'activate_plugin' ] );
