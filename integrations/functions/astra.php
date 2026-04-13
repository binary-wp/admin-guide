<?php
/**
 * Render functions for Astra theme integration.
 *
 * Reads global color palette and typography from `astra-settings` option.
 * Astra stores palette as indexed array (0-8) mapped to CSS custom properties
 * --ast-global-color-0 through --ast-global-color-8.
 */

/**
 * Render the Astra global color palette as a visual swatch table.
 */
function guide_render_astra_color_palette() {
	$settings = get_option( 'astra-settings', array() );
	$palette  = isset( $settings['global-color-palette']['palette'] ) ? $settings['global-color-palette']['palette'] : array();

	if ( ! $palette ) {
		return '<p><em>No Astra color palette configured.</em></p>';
	}

	ob_start();
	echo '<table class="widefat fixed striped" style="max-width:600px">';
	echo '<thead><tr><th style="width:50px">Swatch</th><th>CSS Variable</th><th>Value</th></tr></thead>';
	echo '<tbody>';

	foreach ( $palette as $index => $hex ) {
		$var  = '--ast-global-color-' . (int) $index;
		$text_color = guide_astra_contrast_color( $hex );
		printf(
			'<tr>'
			. '<td><span style="display:inline-block;width:36px;height:24px;border-radius:3px;border:1px solid #dcdcde;background:%s;vertical-align:middle;text-align:center;line-height:24px;font-size:10px;color:%s">%d</span></td>'
			. '<td><code>%s</code></td>'
			. '<td><code>%s</code></td>'
			. '</tr>',
			esc_attr( $hex ),
			esc_attr( $text_color ),
			(int) $index,
			esc_html( $var ),
			esc_html( $hex )
		);
	}

	echo '</tbody></table>';
	return ob_get_clean();
}

/**
 * Render Astra typography settings (body + headings).
 */
function guide_render_astra_typography() {
	$settings = get_option( 'astra-settings', array() );

	$body_family    = ! empty( $settings['body-font-family'] ) ? $settings['body-font-family'] : 'System default';
	$body_weight    = ! empty( $settings['body-font-weight'] ) ? $settings['body-font-weight'] : 'normal';
	$heading_family = ! empty( $settings['headings-font-family'] ) ? $settings['headings-font-family'] : $body_family;
	$heading_weight = ! empty( $settings['headings-font-weight'] ) ? $settings['headings-font-weight'] : 'bold';

	ob_start();

	echo '<table class="widefat fixed striped" style="max-width:600px">';
	echo '<thead><tr><th>Element</th><th>Font Family</th><th>Weight</th><th>Size (desktop)</th></tr></thead>';
	echo '<tbody>';

	// Body.
	$body_size = guide_astra_font_size( $settings, 'body-font-size' );
	printf(
		'<tr><td><strong>Body</strong></td><td style="font-family:%s">%s</td><td>%s</td><td>%s</td></tr>',
		esc_attr( $body_family ),
		esc_html( $body_family ),
		esc_html( $body_weight ),
		esc_html( $body_size )
	);

	// Headings (H1–H6).
	for ( $i = 1; $i <= 6; $i++ ) {
		$h_family = ! empty( $settings[ 'font-family-h' . $i ] )
			? $settings[ 'font-family-h' . $i ]
			: $heading_family;
		$h_weight = ! empty( $settings[ 'font-weight-h' . $i ] )
			? $settings[ 'font-weight-h' . $i ]
			: $heading_weight;
		$h_size   = guide_astra_font_size( $settings, 'font-size-h' . $i );

		// Skip if identical to global heading and no per-heading size.
		$show_family = ( $h_family !== $heading_family ) ? $h_family : '';

		printf(
			'<tr><td><strong>H%d</strong></td><td style="font-family:%s">%s</td><td>%s</td><td>%s</td></tr>',
			$i,
			esc_attr( $h_family ),
			esc_html( $show_family ?: $h_family ),
			esc_html( $h_weight ),
			esc_html( $h_size )
		);
	}

	echo '</tbody></table>';
	return ob_get_clean();
}

/**
 * Extract desktop font size from Astra's responsive font-size array.
 *
 * @param array  $settings Astra settings.
 * @param string $key      Setting key (e.g. 'font-size-h1').
 * @return string Formatted size string or '—'.
 */
function guide_astra_font_size( $settings, $key ) {
	if ( empty( $settings[ $key ] ) ) {
		return '—';
	}

	$val = $settings[ $key ];

	// Responsive array format: { desktop, tablet, mobile, desktop-unit, ... }.
	if ( is_array( $val ) ) {
		$size = isset( $val['desktop'] ) ? $val['desktop'] : '';
		$unit = isset( $val['desktop-unit'] ) ? $val['desktop-unit'] : 'px';
		return $size ? $size . $unit : '—';
	}

	// Simple string/number.
	return (string) $val;
}

/**
 * Return black or white for contrast against a given hex color.
 *
 * @param string $hex Hex color (#RGB or #RRGGBB).
 * @return string '#000' or '#fff'.
 */
function guide_astra_contrast_color( $hex ) {
	$hex = ltrim( $hex, '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	// Relative luminance (simplified).
	$lum = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	return $lum > 0.5 ? '#000' : '#fff';
}
