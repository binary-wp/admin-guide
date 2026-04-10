<?php
/**
 * Status checker for miniOrange SharePoint/OneDrive integration.
 */

function guide_check_mo_sharepoint_connection() {
	// Main application config stored by the plugin.
	$config = get_option( 'mo_sps_application_config', array() );

	if ( empty( $config ) ) {
		return array( 'status' => 'warning', 'message' => 'Not configured' );
	}

	// Config exists — plugin has been set up.
	// Check if license key is present.
	$license = get_option( 'sps_lk', '' );

	if ( ! empty( $license ) && ! empty( $config ) ) {
		return array( 'status' => 'ok', 'message' => 'Connected' );
	}

	if ( ! empty( $config ) ) {
		return array( 'status' => 'ok', 'message' => 'Configured' );
	}

	return array( 'status' => 'warning', 'message' => 'Plugin active, configuration incomplete' );
}
