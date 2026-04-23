<?php
/**
 * Plugin Name: Auto WebP
 * Plugin URI: https://dyuzhaev.com/
 * Description: Lightweight image-to-WebP converter. Auto-converts on upload, serves WebP to supporting browsers, bulk-converts existing images. Works on any hosting.
 * Version: 1.0.0
 * Author: Denys Dyuzhaev
 * Author URI: https://dyuzhaev.com/
 * License: GPL-2.0+
 * Requires PHP: 7.4
 * Text Domain: auto-webp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AWEBP_VERSION', '1.0.0' );
define( 'AWEBP_FILE', __FILE__ );
define( 'AWEBP_DIR', plugin_dir_path( __FILE__ ) );

require_once AWEBP_DIR . 'inc/converter.php';
require_once AWEBP_DIR . 'inc/hooks.php';
require_once AWEBP_DIR . 'inc/delivery.php';

if ( is_admin() ) {
	require_once AWEBP_DIR . 'inc/admin.php';
	require_once AWEBP_DIR . 'inc/bulk.php';
}

register_activation_hook( __FILE__, function () {
	if ( ! awebp_can_convert() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			'Auto WebP requires PHP GD with WebP support or Imagick with WebP support.',
			'Plugin Activation Error',
			[ 'back_link' => true ]
		);
	}
	add_option( 'awebp_quality', 82 );
	add_option( 'awebp_convert_on_upload', 1 );
	add_option( 'awebp_serve_webp', 1 );
	add_option( 'awebp_delete_on_media_delete', 1 );
} );
