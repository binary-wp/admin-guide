<?php
function guide_render_woo_memberships_plans_section() {
	if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
		return '<p><em>WooCommerce Memberships not active.</em></p>';
	}
	$plans = wc_memberships_get_membership_plans();
	if ( empty( $plans ) ) {
		return '<p><em>No membership plans found.</em></p>';
	}

	ob_start();
	foreach ( $plans as $plan ) {
		$edit_url    = get_edit_post_link( $plan->get_id() );
		$product_ids = $plan->get_product_ids();

		echo '<h4><a href="' . esc_url( $edit_url ) . '">' . esc_html( $plan->get_name() ) . '</a></h4>';

		if ( $product_ids ) {
			echo '<p><strong>Products granting access:</strong></p><ul>';
			foreach ( $product_ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product ) continue;
				echo '<li><a href="' . esc_url( admin_url( 'post.php?post=' . $pid . '&action=edit' ) ) . '">' . esc_html( $product->get_name() ) . '</a> <code>' . esc_html( $product->get_type() ) . '</code></li>';
			}
			echo '</ul>';
		}

		$rules = $plan->get_content_restriction_rules();
		if ( $rules ) {
			echo '<p><strong>Restricted content:</strong></p><ul>';
			foreach ( $rules as $rule ) {
				$type_label = $rule->get_content_type();
				$type_name  = $rule->get_content_type_name();
				$object_ids = $rule->get_object_ids();

				if ( empty( $object_ids ) ) {
					$pt = get_post_type_object( $type_name );
					echo '<li>All <strong>' . esc_html( $pt ? $pt->labels->name : $type_name ) . '</strong></li>';
				} else {
					foreach ( $object_ids as $oid ) {
						if ( 'taxonomy' === $type_label ) {
							$term = get_term( $oid, $type_name );
							if ( $term && ! is_wp_error( $term ) ) {
								echo '<li>' . esc_html( $type_name ) . ': ' . esc_html( $term->name ) . '</li>';
							}
						} else {
							$title = get_the_title( $oid );
							if ( $title ) {
								echo '<li><a href="' . esc_url( get_edit_post_link( $oid ) ) . '">' . esc_html( $title ) . '</a> <code>' . esc_html( $type_name ) . '</code></li>';
							}
						}
					}
				}
			}
			echo '</ul>';
		}

		$sections = get_post_meta( $plan->get_id(), '_members_area_sections', true );
		if ( is_array( $sections ) && $sections ) {
			echo '<p><strong>Members Area sections:</strong> ' . esc_html( implode( ', ', $sections ) ) . '</p>';
		}
	}
	return ob_get_clean();
}
