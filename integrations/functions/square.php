<?php
/**
 * Status checkers for Square integration (WooCommerce Square).
 *
 * Two services: Payments (connection + enabled) and POS/Sync (sync mode).
 */

function guide_check_square_connection() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'status' => 'error', 'message' => 'WooCommerce not active' );
	}

	$access_tokens = get_option( 'wc_square_access_tokens', array() );
	$settings      = get_option( 'woocommerce_square_settings', array() );

	if ( empty( $settings ) ) {
		$settings = get_option( 'wc_square_settings', array() );
	}

	// Not connected at all.
	if ( empty( $access_tokens ) ) {
		$visited = get_option( 'wc_square_connected_page_visited', false );
		if ( $visited ) {
			return array( 'status' => 'warning', 'message' => 'Setup started but not connected' );
		}
		return array( 'status' => 'error', 'message' => 'Not connected' );
	}

	// Connected — check environment.
	$sandbox = isset( $settings['enable_sandbox'] ) && $settings['enable_sandbox'] === 'yes';

	// Check if payment gateway is enabled.
	$gw_settings = get_option( 'woocommerce_square_credit_card_settings', array() );
	$gw_enabled  = isset( $gw_settings['enabled'] ) && $gw_settings['enabled'] === 'yes';

	if ( $sandbox ) {
		return array( 'status' => 'warning', 'message' => 'Connected — Sandbox' );
	}

	if ( ! $gw_enabled ) {
		return array( 'status' => 'warning', 'message' => 'Connected — payments disabled' );
	}

	return array( 'status' => 'ok', 'message' => 'Connected — Production' );
}

function guide_check_square_sync() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'status' => 'error', 'message' => 'WooCommerce not active' );
	}

	$access_tokens = get_option( 'wc_square_access_tokens', array() );
	if ( empty( $access_tokens ) ) {
		return array( 'status' => 'error', 'message' => 'Not connected' );
	}

	$settings = get_option( 'woocommerce_square_settings', array() );
	if ( empty( $settings ) ) {
		$settings = get_option( 'wc_square_settings', array() );
	}

	$sor = isset( $settings['system_of_record'] ) ? $settings['system_of_record'] : 'disabled';

	if ( $sor === 'disabled' ) {
		return array( 'status' => 'warning', 'message' => 'Sync disabled' );
	}

	$sor_label = $sor === 'square' ? 'Square → WooCommerce' : 'WooCommerce → Square';

	// Check inventory sync.
	$inv_sync = isset( $settings['enable_inventory_sync'] ) && $settings['enable_inventory_sync'] === 'yes';

	$details = 'Sync: ' . $sor_label;
	if ( $inv_sync ) {
		$details .= ', inventory sync on';
	}

	return array( 'status' => 'ok', 'message' => $details );
}
