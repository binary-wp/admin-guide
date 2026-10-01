<?php
/**
 * Uninstall the standalone plugin: delete the guide and its snapshots.
 *
 * Runs only when the plugin is deleted from Plugins → Installed Plugins.
 * It touches the standalone instance (prefix `admin_guide`) and nothing
 * else — guides that other plugins booted under their own prefix belong to
 * those plugins.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the standalone instance's data. A function keeps the variables out
 * of the global scope.
 */
function admin_guide_builder_uninstall() {
	global $wpdb;

	$prefix = 'admin_guide';

	// Guide pages, including any not yet migrated from the pre-0.8 post type.
	$ids = get_posts(
		array(
			'post_type'      => array( $prefix . '_guide', $prefix . '_guide_page' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}

	// Options: every key the instance writes starts with {prefix}_admin_guide_.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup, nothing to cache.
	$options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix . '_admin_guide_' ) . '%'
		)
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Snapshot folder — same path admin-guide.php boots with.
	$uploads = wp_upload_dir( null, false );
	$dir     = trailingslashit( $uploads['basedir'] ) . 'admin-guide-builder-' . substr( wp_hash( 'admin-guide-builder' ), 0, 12 );
	if ( is_dir( $dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$wp_filesystem->delete( $dir, true );
		}
	}
}

admin_guide_builder_uninstall();
