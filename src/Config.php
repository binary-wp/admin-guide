<?php
/**
 * Config — CPT-based storage for guide pages.
 *
 * Each guide page is a `{prefix}_guide_page` post:
 *   post_title   = label
 *   post_name    = slug
 *   post_content = HTML template (with {{placeholders}})
 *   post_parent  = hierarchy (0 = top-level tab)
 *   menu_order   = sort position
 *   post_status  = publish | draft
 *   meta: _guide_source = system|post_type|taxonomy|platform|custom
 */

namespace BinaryWP\AdminGuide;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config {

	const VERSION = 2;

	/** @var Context */
	private $context;

	/** @var Integrations */
	private $integrations;

	/** @var string CPT slug. */
	private $post_type;

	/** @var string Option key for migration flag. */
	private $migration_key;

	public function __construct( Context $context, Integrations $integrations ) {
		$this->context      = $context;
		$this->integrations = $integrations;
		$this->post_type    = $context->prefix . '_guide_page';
		$this->migration_key = $context->option_key( 'cpt_migrated' );

		// One-shot migration from wp_option to CPT.
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
	}

	// ── Public API: tabs ────────────────────────────────────────────────

	/**
	 * Get ordered top-level tabs as [ slug => label ].
	 */
	public function get_tabs() {
		$tabs  = array();
		$posts = $this->query_guides( 0 );

		foreach ( $posts as $post ) {
			$tabs[ $post->post_name ] = $post->post_title;
		}

		return $tabs;
	}

	/**
	 * Get full tab entries (slug, label, source) for top-level guides.
	 */
	public function get_tab_entries() {
		$entries = array();
		$posts   = $this->query_guides( 0 );

		foreach ( $posts as $post ) {
			$entries[] = array(
				'slug'   => $post->post_name,
				'label'  => $post->post_title,
				'source' => get_post_meta( $post->ID, '_guide_source', true ) ?: 'custom',
			);
		}

		return $entries;
	}

	/**
	 * Get children of a guide by parent slug.
	 *
	 * @return array [ slug => label ]
	 */
	public function get_children( $parent_slug ) {
		$parent = $this->get_guide_by_slug( $parent_slug );
		if ( ! $parent ) {
			return array();
		}

		$children = array();
		foreach ( $this->query_guides( $parent->ID ) as $post ) {
			$children[ $post->post_name ] = $post->post_title;
		}

		return $children;
	}

	/**
	 * Get all guides as flat list with hierarchy info.
	 *
	 * @return array [ { id, slug, label, source, parent_id, depth, menu_order } ]
	 */
	public function get_all_guides() {
		$posts  = $this->query_all_guides();
		$guides = array();

		// Build parent map.
		$parent_map = array();
		foreach ( $posts as $post ) {
			$parent_map[ $post->ID ] = $post->post_parent;
		}

		foreach ( $posts as $post ) {
			$depth = 0;
			$pid   = $post->post_parent;
			while ( $pid > 0 && isset( $parent_map[ $pid ] ) ) {
				$depth++;
				$pid = $parent_map[ $pid ];
			}

			$guides[] = array(
				'id'         => $post->ID,
				'slug'       => $post->post_name,
				'label'      => $post->post_title,
				'source'     => get_post_meta( $post->ID, '_guide_source', true ) ?: 'custom',
				'parent_id'  => $post->post_parent,
				'depth'      => $depth,
				'menu_order' => $post->menu_order,
			);
		}

		return $guides;
	}

	/**
	 * Save ordered guide structure (position + parent).
	 *
	 * @param array $items [ { id, parent_id, position } ]
	 */
	public function save_order( $items ) {
		foreach ( $items as $item ) {
			wp_update_post( array(
				'ID'          => (int) $item['id'],
				'post_parent' => (int) $item['parent_id'],
				'menu_order'  => (int) $item['position'],
			) );
		}
	}

	/**
	 * Add a new guide page.
	 */
	public function add_tab( $slug, $label, $source = 'custom', $parent_id = 0 ) {
		$slug = sanitize_key( $slug );

		// Prevent duplicates.
		if ( $this->get_guide_by_slug( $slug ) ) {
			return false;
		}

		// Scaffold content.
		if ( $source === 'platform' || $source === 'system' ) {
			$content = $this->integrations->scaffold_tab( $slug );
		} else {
			$content = $this->scaffold_template( $slug, $label, $source );
		}

		// Determine menu_order — append at end.
		$siblings = $this->query_guides( $parent_id );
		$order    = count( $siblings );

		$post_id = wp_insert_post( array(
			'post_type'    => $this->post_type,
			'post_title'   => sanitize_text_field( $label ),
			'post_name'    => $slug,
			'post_content' => wp_kses_post( $content ),
			'post_status'  => 'publish',
			'post_parent'  => (int) $parent_id,
			'menu_order'   => $order,
		) );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, '_guide_source', sanitize_key( $source ) );

		return $post_id;
	}

	/**
	 * Remove a guide page.
	 */
	public function remove_tab( $slug ) {
		$post = $this->get_guide_by_slug( $slug );
		if ( ! $post ) {
			return false;
		}

		// Also delete children.
		$children = get_posts( array(
			'post_type'   => $this->post_type,
			'post_parent' => $post->ID,
			'numberposts' => -1,
			'post_status' => 'any',
		) );
		foreach ( $children as $child ) {
			wp_delete_post( $child->ID, true );
		}

		return wp_delete_post( $post->ID, true );
	}

	/**
	 * Rename a guide (slug + label).
	 */
	public function rename_tab( $old_slug, $new_slug, $new_label = '' ) {
		$post = $this->get_guide_by_slug( $old_slug );
		if ( ! $post ) {
			return false;
		}

		$update = array( 'ID' => $post->ID );

		if ( $new_slug && $new_slug !== $old_slug ) {
			$update['post_name'] = sanitize_key( $new_slug );
		}
		if ( $new_label ) {
			$update['post_title'] = sanitize_text_field( $new_label );
		}

		return wp_update_post( $update );
	}

	/**
	 * Update just the label.
	 */
	public function update_tab_label( $slug, $label ) {
		return $this->rename_tab( $slug, '', $label );
	}

	/**
	 * Reorder tabs by slug array (flat, top-level only).
	 */
	public function reorder_tabs( $slugs ) {
		foreach ( $slugs as $order => $slug ) {
			$post = $this->get_guide_by_slug( $slug );
			if ( $post ) {
				wp_update_post( array(
					'ID'         => $post->ID,
					'menu_order' => $order,
				) );
			}
		}
	}

	// ── Public API: templates ───────────────────────────────────────────

	/**
	 * Get raw template HTML for a guide.
	 */
	public function get_template( $slug ) {
		$post = $this->get_guide_by_slug( $slug );
		return $post ? $post->post_content : '';
	}

	/**
	 * Save template HTML for a guide.
	 */
	public function save_template( $slug, $html ) {
		$post = $this->get_guide_by_slug( $slug );
		if ( ! $post ) {
			return false;
		}

		return wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => wp_kses_post( $html ),
		) );
	}

	/**
	 * Whether a template exists for the given slug.
	 */
	public function has_template( $slug ) {
		$post = $this->get_guide_by_slug( $slug );
		return $post && $post->post_content !== '';
	}

	/**
	 * Get all templates as [ slug => html ].
	 */
	public function get_all_templates() {
		$templates = array();
		$posts     = $this->query_all_guides();

		foreach ( $posts as $post ) {
			$templates[ $post->post_name ] = $post->post_content;
		}

		return $templates;
	}

	// ── Public API: export / import ─────────────────────────────────────

	/**
	 * Return full data bundle for export.
	 */
	public function export() {
		$tabs      = array();
		$templates = array();
		$posts     = $this->query_all_guides();

		foreach ( $posts as $post ) {
			$tabs[] = array(
				'slug'       => $post->post_name,
				'label'      => $post->post_title,
				'source'     => get_post_meta( $post->ID, '_guide_source', true ) ?: 'custom',
				'parent'     => $post->post_parent ? get_post_field( 'post_name', $post->post_parent ) : '',
				'menu_order' => $post->menu_order,
			);
			$templates[ $post->post_name ] = $post->post_content;
		}

		return array(
			'version'     => self::VERSION,
			'exported_at' => gmdate( 'c' ),
			'config'      => array( 'tabs' => $tabs ),
			'templates'   => $templates,
		);
	}

	/**
	 * Import a bundle, replacing all current data.
	 *
	 * @return true|WP_Error
	 */
	public function import( $bundle ) {
		if ( ! is_array( $bundle ) || ! isset( $bundle['config']['tabs'] ) ) {
			return new WP_Error( 'invalid_bundle', 'Invalid bundle: missing tabs.' );
		}

		// Delete all existing guide pages.
		$existing = get_posts( array(
			'post_type'   => $this->post_type,
			'numberposts' => -1,
			'post_status' => 'any',
		) );
		foreach ( $existing as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Insert from bundle.
		$slug_to_id = array();

		// First pass: create all posts (without parent).
		foreach ( $bundle['config']['tabs'] as $order => $tab ) {
			$slug    = sanitize_key( $tab['slug'] );
			$content = isset( $bundle['templates'][ $slug ] ) ? wp_kses_post( $bundle['templates'][ $slug ] ) : '';

			$post_id = wp_insert_post( array(
				'post_type'    => $this->post_type,
				'post_title'   => sanitize_text_field( $tab['label'] ),
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'menu_order'   => isset( $tab['menu_order'] ) ? (int) $tab['menu_order'] : $order,
			) );

			if ( ! is_wp_error( $post_id ) ) {
				$slug_to_id[ $slug ] = $post_id;
				update_post_meta( $post_id, '_guide_source', isset( $tab['source'] ) ? sanitize_key( $tab['source'] ) : 'custom' );
			}
		}

		// Second pass: set parents.
		foreach ( $bundle['config']['tabs'] as $tab ) {
			if ( ! empty( $tab['parent'] ) && isset( $slug_to_id[ $tab['slug'] ] ) && isset( $slug_to_id[ $tab['parent'] ] ) ) {
				wp_update_post( array(
					'ID'          => $slug_to_id[ $tab['slug'] ],
					'post_parent' => $slug_to_id[ $tab['parent'] ],
				) );
			}
		}

		return true;
	}

	// ── Migration ───────────────────────────────────────────────────────

	/**
	 * One-shot migration from wp_option to CPT.
	 */
	public function maybe_migrate() {
		if ( get_option( $this->migration_key ) ) {
			return; // Already migrated.
		}

		$option_key = $this->context->option_key( 'data' );
		$data       = get_option( $option_key );

		if ( ! is_array( $data ) || empty( $data['config']['tabs'] ) ) {
			// Also check legacy key.
			$data = get_option( 'guide_builder_data' );
		}

		if ( ! is_array( $data ) || empty( $data['config']['tabs'] ) ) {
			// Nothing to migrate — mark as done.
			update_option( $this->migration_key, 1 );
			return;
		}

		// Migrate tabs.
		foreach ( $data['config']['tabs'] as $order => $tab ) {
			$slug    = sanitize_key( $tab['slug'] );
			$label   = sanitize_text_field( $tab['label'] );
			$source  = isset( $tab['source'] ) ? sanitize_key( $tab['source'] ) : 'custom';
			$content = isset( $data['templates'][ $slug ] ) ? wp_kses_post( $data['templates'][ $slug ] ) : '';

			// Skip if already exists (re-entrant safety).
			if ( $this->get_guide_by_slug( $slug ) ) {
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'    => $this->post_type,
				'post_title'   => $label,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'menu_order'   => $order,
			) );

			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_guide_source', $source );
			}
		}

		// Mark migrated and clean up old option.
		update_option( $this->migration_key, 1 );
		delete_option( $option_key );
		delete_option( 'guide_builder_data' );
	}

	// ── Queries ─────────────────────────────────────────────────────────

	/**
	 * Get guide posts by parent, sorted by menu_order.
	 */
	private function query_guides( $parent_id = 0 ) {
		return get_posts( array(
			'post_type'   => $this->post_type,
			'post_parent' => $parent_id,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'numberposts' => -1,
			'post_status' => 'publish',
		) );
	}

	/**
	 * Get ALL guide posts, sorted hierarchically: parent → children → next parent.
	 */
	private function query_all_guides() {
		// Get all posts flat.
		$all = get_posts( array(
			'post_type'   => $this->post_type,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'numberposts' => -1,
			'post_status' => 'any',
		) );

		// Group by parent.
		$by_parent = array();
		foreach ( $all as $post ) {
			$by_parent[ $post->post_parent ][] = $post;
		}

		// Walk tree: parent → children → next parent.
		$sorted = array();
		$this->walk_tree( $by_parent, 0, $sorted );

		return $sorted;
	}

	/**
	 * Recursive tree walker for hierarchical sort.
	 */
	private function walk_tree( &$by_parent, $parent_id, &$sorted ) {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent_id ] as $post ) {
			$sorted[] = $post;
			$this->walk_tree( $by_parent, $post->ID, $sorted );
		}
	}

	/**
	 * Get a guide post by slug.
	 */
	private function get_guide_by_slug( $slug ) {
		$posts = get_posts( array(
			'post_type'   => $this->post_type,
			'name'        => sanitize_key( $slug ),
			'numberposts' => 1,
			'post_status' => 'any',
		) );

		return $posts ? $posts[0] : null;
	}

	/**
	 * Scaffold a new template for a custom / post-type / taxonomy tab.
	 */
	private function scaffold_template( $slug, $label, $source ) {
		$html = '<h1>' . esc_html( $label ) . '</h1>' . "\n";

		if ( $source === 'post_type' && post_type_exists( $slug ) ) {
			$obj      = get_post_type_object( $slug );
			$edit_url = $slug === 'post' ? 'edit.php' : 'edit.php?post_type=' . $slug;
			$html .= '<p><a href="' . esc_url( $edit_url ) . '">' . esc_html( $obj->labels->name ) . '</a>'
				. ' (<code>' . esc_html( $slug ) . '</code>) — content management instructions.</p>' . "\n";

			$taxs     = get_object_taxonomies( $slug, 'objects' );
			$tax_html = array();
			foreach ( $taxs as $tax ) {
				if ( $tax->show_ui ) {
					$tax_url    = 'edit-tags.php?taxonomy=' . $tax->name . '&post_type=' . $slug;
					$tax_html[] = '<li><a href="' . esc_url( $tax_url ) . '">' . esc_html( $tax->labels->name ) . '</a></li>';
				}
			}
			if ( $tax_html ) {
				$html .= '<h2>Taxonomies</h2>' . "\n" . '<ul>' . implode( "\n", $tax_html ) . '</ul>' . "\n";
			}
		} elseif ( $source === 'taxonomy' && taxonomy_exists( $slug ) ) {
			$tax  = get_taxonomy( $slug );
			$html .= '<p><a href="edit-tags.php?taxonomy=' . esc_attr( $slug ) . '">' . esc_html( $tax->labels->name ) . '</a>'
				. ' — taxonomy management instructions.</p>' . "\n";
		}

		return $html;
	}
}
