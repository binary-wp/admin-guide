<?php
/**
 * Plugin Name: Admin Guide
 * Description: JSON-driven admin guide builder for WordPress. Install as a standalone plugin, or require as a Composer dependency from your own plugin/theme.
 * Version:     0.6.1
 * Author:      BinaryWP
 * License:     MIT
 * Text Domain: binary-wp-admin-guide
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If composer autoload lives next to this file (standalone install with
// `composer install` ran), use it. Otherwise assume the host has already
// required this package via its own vendor/autoload.php and classes are
// already available.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Manual fallback for "no composer" standalone installs — require each
// class file directly in dependency order.
if ( ! class_exists( '\\BinaryWP\\AdminGuide\\Plugin' ) ) {
	require_once __DIR__ . '/src/Context.php';
	require_once __DIR__ . '/src/Placeholders.php';
	require_once __DIR__ . '/src/Integrations.php';
	require_once __DIR__ . '/src/Config.php';
	require_once __DIR__ . '/src/Generator.php';
	require_once __DIR__ . '/src/Admin.php';
	require_once __DIR__ . '/src/Plugin.php';
}

/**
 * Standalone plugin self-boot.
 *
 * When this file is loaded as a WordPress plugin (detectable because we're
 * inside WP_PLUGIN_DIR and no host has booted an instance yet), auto-register
 * a default instance using this file's own path and URL.
 *
 * Hosts that bundle this package via Composer should call
 * `\BinaryWP\AdminGuide\Plugin::boot( $prefix, $args )` themselves BEFORE
 * `plugins_loaded` priority 5, and this auto-boot will be skipped.
 */
add_action( 'plugins_loaded', function () {
	if ( ! empty( \BinaryWP\AdminGuide\Plugin::all() ) ) {
		return; // Host already booted.
	}

	$plugin_dir = rtrim( str_replace( '\\', '/', __DIR__ ), '/' );
	$wp_plugin_dir = defined( 'WP_PLUGIN_DIR' ) ? rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' ) : '';
	$in_wp_plugin_dir = $wp_plugin_dir && strpos( $plugin_dir, $wp_plugin_dir . '/' ) === 0;

	if ( ! $in_wp_plugin_dir ) {
		return; // Not running as a standalone plugin — don't auto-boot.
	}

	\BinaryWP\AdminGuide\Plugin::boot( 'admin_guide', array(
		'package_path'    => __DIR__ . '/',
		'package_url'     => plugin_dir_url( __FILE__ ),
		'package_version' => '0.6.1',
		'menu'            => array(
			// By default the viewer/builder installs under its own top-level item.
			// Hosts can override via filter or by booting their own instance.
			'parent'        => '',
			'builder_label' => 'Admin Guide',
		),
	) );
}, 5 );
