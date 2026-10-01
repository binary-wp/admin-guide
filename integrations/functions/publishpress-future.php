<?php
/**
 * Render functions for PublishPress Future integration.
 */

/**
 * Editor-aware screenshot: shows Block Editor panel, Classic Editor metabox, or both.
 */
function admin_guide_builder_render_pp_future_metabox_screenshot() {
	$editors = function_exists( 'admin_guide_builder_detect_wp_editors' ) ? admin_guide_builder_detect_wp_editors() : array( 'block' => false, 'classic' => true );

	// Text, not screenshots: the guide must not load images from third-party
	// servers (WordPress.org forbids hotlinking external assets).
	$where = array();
	if ( $editors['block'] ) {
		$where[] = 'in the Block Editor, open the <strong>Future Action</strong> panel in the post sidebar';
	}
	if ( $editors['classic'] ) {
		$where[] = 'in the Classic Editor, use the <strong>Future Action</strong> box beside the content';
	}
	if ( ! $where ) {
		return '<p><em>No editor detected for PublishPress Future.</em></p>';
	}

	return '<p>To schedule a change, ' . implode( '; ', $where ) . '. '
		. '<a href="' . esc_url( 'https://wordpress.org/plugins/post-expirator/#screenshots' ) . '" target="_blank" rel="noopener">See screenshots on WordPress.org &rarr;</a></p>';
}
