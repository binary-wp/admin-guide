<?php
/**
 * Callback functions for built-in (WordPress) placeholders.
 * Registered via default.json — do NOT register here.
 *
 * NOTE: these functions are called from placeholder resolution context,
 * which is per-instance. Since callbacks are globally declared PHP
 * functions (not bound to a specific instance), we look up the currently
 * active instance via Plugin::first() — fine for the single-instance
 * case; when multi-instance rendering becomes real, this needs to switch
 * to a scoped "current instance" pointer set by the resolver.
 */

// ── Helpers ─────────────────────────────────────────────────────────

function guide_current_plugin() {
	return class_exists( '\\BinaryWP\\AdminGuide\\Plugin' ) ? \BinaryWP\AdminGuide\Plugin::first() : null;
}

function guide_current_config() {
	$plugin = guide_current_plugin();
	return $plugin ? $plugin->config : null;
}

function guide_current_integrations() {
	$plugin = guide_current_plugin();
	return $plugin ? $plugin->integrations : null;
}

// ── Content type pool ───────────────────────────────────────────────

function guide_get_content_post_types( $group = 'all' ) {
	static $cache = null;

	if ( $cache === null ) {
		$post_types    = get_post_types( array(), 'objects' );
		$crm_types     = array( 'wc_user_membership', 'wc_membership_plan', 'shop_subscription' );
		$special_slugs = array( 'page', 'attachment', 'product' );
		$cache         = array( 'content' => array(), 'special' => array() );

		foreach ( $post_types as $slug => $obj ) {
			$is_special = in_array( $slug, $special_slugs, true );

			if ( ! $is_special ) {
				if ( ! $obj->public || ! $obj->publicly_queryable || ! $obj->show_in_menu || ! $obj->show_in_nav_menus ) {
					continue;
				}
			} elseif ( ! $obj->show_in_menu ) {
				continue;
			}

			if ( in_array( $slug, $crm_types, true ) ) {
				continue;
			}

			$cache[ $is_special ? 'special' : 'content' ][] = $obj;
		}
	}

	if ( $group === 'content' ) return $cache['content'];
	if ( $group === 'special' ) return $cache['special'];
	return array_merge( $cache['content'], $cache['special'] );
}

// ── Registered CPTs ─────────────────────────────────────────────────

function guide_render_wp_content_types_table() {
	$registrar_map = array(
		'tribe_'     => 'The Events Calendar',
		'product'    => 'WooCommerce',
		'shop_'      => 'WooCommerce',
		'newsletter' => 'Heisey Functionality Pack',
	);

	$acf_cpt_slugs = array();
	if ( function_exists( 'acf_get_post_type_post' ) ) {
		$acf_cpts = get_posts( array( 'post_type' => 'acf-post-type', 'numberposts' => -1, 'post_status' => 'publish' ) );
		foreach ( $acf_cpts as $acf_cpt ) {
			$acf_settings = maybe_unserialize( $acf_cpt->post_content );
			if ( is_array( $acf_settings ) && ! empty( $acf_settings['post_type'] ) ) {
				$acf_cpt_slugs[] = $acf_settings['post_type'];
			}
		}
	}

	$content_types = array();
	$special       = array();

	foreach ( array( 'content', 'special' ) as $group ) {
		foreach ( guide_get_content_post_types( $group ) as $obj ) {
			$registrar = 'WordPress';
			if ( ! $obj->_builtin ) {
				if ( in_array( $obj->name, $acf_cpt_slugs, true ) ) {
					$registrar = 'Heisey CPT';
				} else {
					foreach ( $registrar_map as $prefix => $label ) {
						if ( strpos( $obj->name, $prefix ) === 0 || $obj->name === $prefix ) {
							$registrar = $label;
							break;
						}
					}
				}
			}
			$entry = array( 'obj' => $obj, 'registrar' => $registrar );
			if ( $group === 'special' ) { $special[] = $entry; } else { $content_types[] = $entry; }
		}
	}

	ob_start();
	echo '<h4 id="content-types">' . esc_html__( 'Content Types', 'binary-wp-admin-guide' ) . '</h4>';
	guide_render_cpt_table( $content_types );
	if ( $special ) {
		echo '<h4 id="special-content-types">' . esc_html__( 'Special Content Types', 'binary-wp-admin-guide' ) . '</h4>';
		guide_render_cpt_table( $special );
	}
	return ob_get_clean();
}

function guide_render_cpt_table( $entries ) {
	$config = guide_current_config();
	$tabs   = $config ? $config->get_tabs() : array();

	echo '<table class="widefat fixed striped" style="max-width:800px;margin-bottom:20px"><thead><tr>';
	echo '<th>' . esc_html__( 'Content Type', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Taxonomies', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'User Guide', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Registered by', 'binary-wp-admin-guide' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $entries as $entry ) {
		$obj       = $entry['obj'];
		$registrar = $entry['registrar'];
		$slug      = $obj->name;
		$edit_url  = $slug === 'post' ? admin_url( 'edit.php' ) : admin_url( 'edit.php?post_type=' . $slug );

		$taxs = get_object_taxonomies( $slug, 'objects' );
		$tax_links = array();
		foreach ( $taxs as $tax ) {
			if ( ! $tax->show_ui ) continue;
			$tax_links[] = '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . $tax->name . '&post_type=' . $slug ) ) . '">' . esc_html( $tax->labels->name ) . '</a>';
		}

		$guide_link = '&mdash;';
		if ( isset( $tabs[ $slug ] ) && $config && $config->has_template( $slug ) ) {
			$guide_link = '<a href="' . esc_url( admin_url( 'admin.php?page=hfp-guide&tab=' . $slug ) ) . '">' . esc_html( $tabs[ $slug ] ) . '</a>';
		}

		echo '<tr>';
		echo '<td><a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $obj->labels->name ) . '</strong></a></td>';
		echo '<td>' . ( $tax_links ? implode( ', ', $tax_links ) : '&mdash;' ) . '</td>';
		echo '<td>' . $guide_link . '</td>';
		echo '<td>' . esc_html( $registrar ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

// ── Other CPTs ──────────────────────────────────────────────────────

function guide_render_wp_other_content_table() {
	$config = guide_current_config();
	$tabs   = $config ? $config->get_tabs() : array();
	$others = array();

	foreach ( guide_get_content_post_types( 'content' ) as $obj ) {
		// Skip CPTs that already have a dedicated tab with template content.
		if ( isset( $tabs[ $obj->name ] ) && $config && $config->has_template( $obj->name ) ) {
			continue;
		}
		$others[] = $obj;
	}

	if ( empty( $others ) ) {
		return '<p><em>' . esc_html__( 'All content types have dedicated guide tabs.', 'binary-wp-admin-guide' ) . '</em></p>';
	}

	ob_start();
	echo '<table class="widefat fixed striped" style="max-width:600px"><thead><tr>';
	echo '<th>' . esc_html__( 'Content Type', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Taxonomies', 'binary-wp-admin-guide' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $others as $obj ) {
		$edit_url = $obj->name === 'post' ? admin_url( 'edit.php' ) : admin_url( 'edit.php?post_type=' . $obj->name );
		$taxs = get_object_taxonomies( $obj->name, 'objects' );
		$tax_links = array();
		foreach ( $taxs as $tax ) {
			if ( ! $tax->show_ui ) continue;
			$tax_links[] = '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . $tax->name . '&post_type=' . $obj->name ) ) . '">' . esc_html( $tax->labels->name ) . '</a>';
		}
		echo '<tr>';
		echo '<td><a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $obj->labels->name ) . '</strong></a> <code>' . esc_html( $obj->name ) . '</code></td>';
		echo '<td>' . ( $tax_links ? implode( ', ', $tax_links ) : '&mdash;' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	return ob_get_clean();
}

// ── Editor detection ────────────────────────────────────────────────

function guide_detect_wp_editors() {
	static $result = null;
	if ( $result !== null ) return $result;

	$result = array(
		'block' => false, 'classic' => false, 'elementor' => false,
		'block_types' => array(), 'classic_types' => array(), 'elementor_types' => array(),
	);

	$elementor_cpts = array();
	if ( class_exists( 'Elementor\\Plugin' ) ) {
		$elementor_cpts = (array) get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
	}

	foreach ( guide_get_content_post_types() as $obj ) {
		$sample = get_posts( array( 'post_type' => $obj->name, 'numberposts' => 1, 'post_status' => 'any' ) );
		if ( ! $sample ) continue;

		$entry = array( 'slug' => $obj->name, 'label' => $obj->labels->name );

		if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $sample[0] ) ) {
			$result['block'] = true;
			$result['block_types'][] = $entry;
		} else {
			$result['classic'] = true;
			$result['classic_types'][] = $entry;
		}

		if ( in_array( $obj->name, $elementor_cpts, true ) ) {
			$result['elementor'] = true;
			$result['elementor_types'][] = $entry;
		}
	}

	return $result;
}

function guide_render_type_links( $entries ) {
	$links = array();
	foreach ( $entries as $e ) {
		$url = $e['slug'] === 'post' ? admin_url( 'edit.php' ) : admin_url( 'edit.php?post_type=' . $e['slug'] );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $e['label'] ) . '</a>';
	}
	return implode( ', ', $links );
}

function guide_render_wp_editors_list() {
	$detected = guide_detect_wp_editors();
	$items    = array();

	if ( $detected['classic'] ) {
		$items[] = '<li><strong>' . esc_html__( 'Classic Editor', 'binary-wp-admin-guide' ) . '</strong> — '
			. esc_html__( 'single rich-text area with formatting toolbar.', 'binary-wp-admin-guide' ) . ' '
			. '<a href="https://wordpress.org/documentation/article/classic-editor/" target="_blank">' . esc_html__( 'Documentation', 'binary-wp-admin-guide' ) . ' &rarr;</a>'
			. '<br><span class="description">' . esc_html__( 'Used by:', 'binary-wp-admin-guide' ) . ' ' . guide_render_type_links( $detected['classic_types'] ) . '</span></li>';
	}
	if ( $detected['block'] ) {
		$items[] = '<li><strong>' . esc_html__( 'Block Editor', 'binary-wp-admin-guide' ) . '</strong> (Gutenberg) — '
			. esc_html__( 'block-based editor with drag & drop.', 'binary-wp-admin-guide' ) . ' '
			. '<a href="https://wordpress.org/documentation/article/wordpress-block-editor/" target="_blank">' . esc_html__( 'Documentation', 'binary-wp-admin-guide' ) . ' &rarr;</a>'
			. '<br><span class="description">' . esc_html__( 'Used by:', 'binary-wp-admin-guide' ) . ' ' . guide_render_type_links( $detected['block_types'] ) . '</span></li>';
	}
	if ( $detected['elementor'] ) {
		$items[] = '<li><strong>Elementor</strong> — '
			. esc_html__( 'visual page builder for complex layouts.', 'binary-wp-admin-guide' ) . ' '
			. '<a href="https://elementor.com/help/" target="_blank">' . esc_html__( 'Documentation', 'binary-wp-admin-guide' ) . ' &rarr;</a>'
			. '<br><span class="description">' . esc_html__( 'Used by:', 'binary-wp-admin-guide' ) . ' ' . guide_render_type_links( $detected['elementor_types'] ) . '</span></li>';
	}

	return $items ? '<ul>' . implode( "\n", $items ) . '</ul>' : '<p><em>' . esc_html__( 'No editors detected.', 'binary-wp-admin-guide' ) . '</em></p>';
}

function guide_render_wp_editor_name_text() {
	$editors = guide_detect_wp_editors();
	$names   = array();
	if ( $editors['classic'] ) $names[] = __( 'Classic Editor', 'binary-wp-admin-guide' );
	if ( $editors['block'] )   $names[] = __( 'Block Editor', 'binary-wp-admin-guide' );
	return $names ? implode( ' ' . __( 'or', 'binary-wp-admin-guide' ) . ' ', $names ) : __( 'WordPress Editor', 'binary-wp-admin-guide' );
}

function guide_render_wp_allowed_editors_text() {
	$editors = guide_detect_wp_editors();
	$parts   = array();

	if ( $editors['classic'] ) {
		$parts[] = '<a href="https://wordpress.org/documentation/article/classic-editor/" target="_blank">' . esc_html__( 'Classic Editor', 'binary-wp-admin-guide' ) . '</a>';
	}
	if ( $editors['block'] ) {
		$parts[] = '<a href="https://wordpress.org/documentation/article/wordpress-block-editor/" target="_blank">' . esc_html__( 'Block Editor', 'binary-wp-admin-guide' ) . '</a>';
	}

	if ( count( $parts ) === 2 ) {
		return sprintf(
			/* translators: 1: first editor link, 2: second editor link */
			__( 'the %1$s or %2$s', 'binary-wp-admin-guide' ),
			$parts[0],
			$parts[1]
		);
	}
	if ( count( $parts ) === 1 ) {
		return sprintf( __( 'the %s', 'binary-wp-admin-guide' ), $parts[0] );
	}
	return __( 'the WordPress Editor', 'binary-wp-admin-guide' );
}

// ── General / misc ──────────────────────────────────────────────────

function guide_render_wp_posts_page_link() {
	$page_id = (int) get_option( 'page_for_posts' );
	if ( $page_id ) {
		return '<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( get_the_title( $page_id ) ) . '</a>';
	}
	return '<code>' . esc_url( home_url( '/' ) ) . '</code>';
}

function guide_render_wp_post_categories_list() {
	$cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'meta_value_num', 'meta_key' => 'order', 'order' => 'ASC' ) );
	if ( is_wp_error( $cats ) || empty( $cats ) ) {
		$cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'term_order', 'order' => 'ASC' ) );
	}
	if ( is_wp_error( $cats ) || empty( $cats ) ) {
		return '<p><em>' . esc_html__( 'No categories found.', 'binary-wp-admin-guide' ) . '</em></p>';
	}

	ob_start();
	echo '<ol>';
	foreach ( $cats as $cat ) {
		if ( $cat->slug === 'uncategorized' ) continue;
		echo '<li><a href="' . esc_url( add_query_arg( 'category_name', $cat->slug, admin_url( 'edit.php' ) ) ) . '">' . esc_html( $cat->name ) . '</a>';
		echo ' <span class="description">(' . (int) $cat->count . ')</span></li>';
	}
	echo '</ol>';
	return ob_get_clean();
}

function guide_render_wp_settings_dashboards_table() {
	$dashboards = array();

	if ( class_exists( 'WooCommerce' ) ) {
		$dashboards[] = array( 'WooCommerce', __( 'Store, payments, shipping, tax', 'binary-wp-admin-guide' ), admin_url( 'admin.php?page=wc-settings' ) );
	}
	if ( function_exists( 'bpfwp_setting' ) || defined( 'BPFWP_PLUGIN_DIR' ) ) {
		$dashboards[] = array( 'Business Profile', __( 'Business info, opening hours, contact', 'binary-wp-admin-guide' ), admin_url( 'admin.php?page=bpfwp-settings' ) );
	}

	if ( empty( $dashboards ) ) {
		return '<p><em>' . esc_html__( 'No settings dashboards detected.', 'binary-wp-admin-guide' ) . '</em></p>';
	}

	ob_start();
	echo '<table class="widefat fixed striped" style="max-width:750px"><thead><tr>';
	echo '<th>' . esc_html__( 'Dashboard', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Purpose', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Link', 'binary-wp-admin-guide' ) . '</th>';
	echo '</tr></thead><tbody>';
	foreach ( $dashboards as $d ) {
		echo '<tr><td><strong>' . esc_html( $d[0] ) . '</strong></td>';
		echo '<td>' . esc_html( $d[1] ) . '</td>';
		echo '<td><a href="' . esc_url( $d[2] ) . '">' . esc_html__( 'Open', 'binary-wp-admin-guide' ) . ' &rarr;</a></td></tr>';
	}
	echo '</tbody></table>';
	return ob_get_clean();
}

function guide_render_wp_external_services_table() {
	$integrations = guide_current_integrations();
	if ( ! $integrations ) {
		return '<p><em>' . esc_html__( 'Integration registry not available.', 'binary-wp-admin-guide' ) . '</em></p>';
	}

	$externals = $integrations->get_external();

	if ( empty( $externals ) ) {
		return '<p><em>' . esc_html__( 'No external service integrations detected.', 'binary-wp-admin-guide' ) . '</em></p>';
	}

	ob_start();
	echo '<div class="guide-external-status" data-integration="__all__">';
	echo '<table class="widefat fixed striped" style="max-width:750px"><thead><tr>';
	echo '<th>' . esc_html__( 'Service', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Connection', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Status', 'binary-wp-admin-guide' ) . '</th>';
	echo '<th>' . esc_html__( 'Settings', 'binary-wp-admin-guide' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $externals as $slug => $data ) {
		$ext_services = ! empty( $data['external'] ) ? $data['external'] : array();

		if ( empty( $ext_services ) ) {
			// Integration marked external but no services listed — show as single row.
			echo '<tr><td><strong>' . esc_html( $data['name'] ) . '</strong></td>';
			echo '<td>&mdash;</td>';
			echo '<td><span class="guide-status-badge" data-status="unknown">● ' . esc_html__( 'Unknown', 'binary-wp-admin-guide' ) . '</span></td>';
			if ( $data['settings_url'] ) {
				echo '<td><a href="' . esc_url( admin_url( $data['settings_url'] ) ) . '">' . esc_html__( 'Settings', 'binary-wp-admin-guide' ) . ' &rarr;</a></td>';
			} else {
				echo '<td>&mdash;</td>';
			}
			echo '</tr>';
			continue;
		}

		foreach ( $ext_services as $i => $ext ) {
			echo '<tr data-integration="' . esc_attr( $slug ) . '" data-service-index="' . $i . '">';
			// Show integration name only on first row.
			if ( $i === 0 ) {
				echo '<td rowspan="' . count( $ext_services ) . '"><strong>' . esc_html( $data['name'] ) . '</strong></td>';
			}
			echo '<td>' . esc_html( $ext['service'] );
			if ( ! empty( $ext['description'] ) ) {
				echo '<br><span class="description">' . esc_html( $ext['description'] ) . '</span>';
			}
			echo '</td>';
			echo '<td class="guide-status-cell"><em>' . esc_html__( 'Checking…', 'binary-wp-admin-guide' ) . '</em></td>';
			if ( $i === 0 ) {
				$settings = $data['settings_url']
					? '<a href="' . esc_url( admin_url( $data['settings_url'] ) ) . '">' . esc_html__( 'Settings', 'binary-wp-admin-guide' ) . ' &rarr;</a>'
					: '&mdash;';
				echo '<td rowspan="' . count( $ext_services ) . '">' . $settings . '</td>';
			}
			echo '</tr>';
		}
	}

	echo '</tbody></table>';
	echo '</div>';
	return ob_get_clean();
}
