<?php
/**
 * Render functions for PublishPress Future integration.
 */

/**
 * Editor-aware screenshot: shows Block Editor panel, Classic Editor metabox, or both.
 */
function guide_render_pp_future_metabox_screenshot() {
	$editors = function_exists( 'guide_detect_wp_editors' ) ? guide_detect_wp_editors() : array( 'block' => false, 'classic' => true );

	$block_img   = 'https://ps.w.org/post-expirator/assets/screenshot-1.png';
	$classic_img = 'https://publishpress.com/wp-content/uploads/2023/02/publishpress-future-pro-726x1024.png';

	$style = 'max-width:100%;margin:15px 0;border:1px solid #c3c4c7';
	$out   = '';

	if ( $editors['block'] ) {
		$out .= '<figure style="margin:15px 0">';
		$out .= '<img src="' . esc_url( $block_img ) . '" alt="PublishPress Future — Block Editor sidebar" style="' . $style . '">';
		$out .= '<figcaption style="font-size:12px;color:#8c8f94;margin-top:4px">Block Editor — Future Action panel in the sidebar</figcaption>';
		$out .= '</figure>';
	}

	if ( $editors['classic'] ) {
		$out .= '<figure style="margin:15px 0">';
		$out .= '<img src="' . esc_url( $classic_img ) . '" alt="PublishPress Future — Classic Editor metabox" style="' . $style . '">';
		$out .= '<figcaption style="font-size:12px;color:#8c8f94;margin-top:4px">Classic Editor — Future Action settings panel</figcaption>';
		$out .= '</figure>';
	}

	return $out ?: '<p><em>No editor detected for PublishPress Future screenshots.</em></p>';
}
