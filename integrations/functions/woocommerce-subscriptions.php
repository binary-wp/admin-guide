<?php
function guide_render_woo_subscriptions_products_table() {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return '<p><em>WooCommerce not active.</em></p>';
	}

	$plan_product_ids = array();
	if ( function_exists( 'wc_memberships_get_membership_plans' ) ) {
		foreach ( wc_memberships_get_membership_plans() as $plan ) {
			foreach ( $plan->get_product_ids() as $pid ) {
				$plan_product_ids[ $pid ] = $plan->get_name();
			}
		}
	}

	$types = array(
		'variable-subscription' => 'Membership Subscription Products',
		'subscription'          => 'Subscription Addon Products',
	);

	ob_start();
	foreach ( $types as $type => $label ) {
		$products = wc_get_products( array( 'type' => $type, 'status' => 'publish', 'limit' => 100 ) );
		if ( empty( $products ) ) continue;

		echo '<h4>' . esc_html( $label ) . '</h4>';
		echo '<table class="widefat fixed striped" style="max-width:700px"><thead><tr>';
		echo '<th>Product</th><th>Price</th><th>Linked Plan</th></tr></thead><tbody>';
		foreach ( $products as $product ) {
			$pid  = $product->get_id();
			$url  = admin_url( 'post.php?post=' . $pid . '&action=edit' );
			$plan = isset( $plan_product_ids[ $pid ] ) ? $plan_product_ids[ $pid ] : '&mdash;';
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $product->get_name() ) . '</a></td>';
			echo '<td>' . wp_kses_post( $product->get_price_html() ) . '</td>';
			echo '<td>' . esc_html( $plan ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	return ob_get_clean();
}
