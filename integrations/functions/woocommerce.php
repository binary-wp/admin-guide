<?php

function guide_render_woo_payment_methods_table() {
	if ( ! function_exists( 'WC' ) ) {
		return '<p><em>WooCommerce not active.</em></p>';
	}
	$gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( empty( $gateways ) ) {
		return '<p><em>No active payment methods.</em></p>';
	}
	ob_start();
	echo '<ul>';
	foreach ( $gateways as $gw ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gw->id );
		echo '<li><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $gw->get_title() ) . '</strong></a>';
		if ( $gw->get_description() ) {
			echo ' — ' . esc_html( wp_strip_all_tags( $gw->get_description() ) );
		}
		echo '</li>';
	}
	echo '</ul>';
	return ob_get_clean();
}

function guide_check_woo_payment_gateway() {
	if ( ! function_exists( 'WC' ) ) {
		return array( 'status' => 'error', 'message' => 'WooCommerce not active' );
	}

	// Check all registered gateways for enabled ones (works in admin context).
	$gateways = WC()->payment_gateways()->payment_gateways();
	$enabled  = array();

	foreach ( $gateways as $gw ) {
		if ( $gw->enabled === 'yes' ) {
			$enabled[] = $gw->get_title();
		}
	}

	if ( empty( $enabled ) ) {
		return array( 'status' => 'warning', 'message' => 'No payment methods enabled' );
	}

	return array( 'status' => 'ok', 'message' => implode( ', ', $enabled ) );
}
