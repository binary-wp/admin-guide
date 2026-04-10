<?php
function guide_render_tec_categories_list() {
	if ( ! taxonomy_exists( 'tribe_events_cat' ) ) {
		return '<p><em>Event categories taxonomy not registered.</em></p>';
	}
	$terms = get_terms( array( 'taxonomy' => 'tribe_events_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '<p><em>No event categories found.</em></p>';
	}
	$admin_base = admin_url( 'edit.php?post_type=tribe_events' );
	ob_start();
	echo '<ul>';
	foreach ( $terms as $term ) {
		echo '<li><a href="' . esc_url( add_query_arg( 'tribe_events_cat', $term->slug, $admin_base ) ) . '">' . esc_html( $term->name ) . '</a>';
		echo ' <span class="description">(' . (int) $term->count . ')</span></li>';
	}
	echo '</ul>';
	return ob_get_clean();
}
