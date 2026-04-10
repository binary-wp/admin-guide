<?php
/**
 * Config (tab structure + templates).
 *
 * Stores in a single wp_option (key is prefix-scoped via Context):
 *   {prefix}_admin_guide_data = {
 *     version:   int,
 *     config:    { tabs: [...], include_general: bool, include_other: bool },
 *     templates: { slug: html, ... }
 *   }
 *
 * One-shot migrates from legacy non-prefixed option and from on-disk
 * guide/templates/*.html + config.json on first read.
 */

namespace BinaryWP\AdminGuide;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config {

	/** Current schema version. */
	const VERSION = 1;

	/** @var Context */
	private $context;

	/** @var Integrations */
	private $integrations;

	/** @var string wp_option key, derived from context prefix. */
	private $option_key;

	/** @var array|null Cached data. */
	private $data;

	public function __construct( Context $context, Integrations $integrations ) {
		$this->context      = $context;
		$this->integrations = $integrations;
		$this->option_key   = $context->option_key( 'data' );
	}

	// ── Public API: tabs ────────────────────────────────────────────────

	/**
	 * Get ordered tabs as [ slug => label ].
	 */
	public function get_tabs() {
		$tabs = array();
		foreach ( $this->read()['config']['tabs'] as $tab ) {
			$tabs[ $tab['slug'] ] = $tab['label'];
		}
		return $tabs;
	}

	/**
	 * Get full tab entries (slug, label, source).
	 */
	public function get_tab_entries() {
		return $this->read()['config']['tabs'];
	}

	public function includes_general() {
		return ! empty( $this->read()['config']['include_general'] );
	}

	public function includes_other() {
		return ! empty( $this->read()['config']['include_other'] );
	}

	/**
	 * Save tab structure.
	 *
	 * @param array $tabs    Array of [ 'slug' => '', 'label' => '', 'source' => '' ].
	 * @param array $options Optional: include_general, include_other.
	 */
	public function save_tabs( $tabs, $options = array() ) {
		$data = $this->read();
		$data['config']['tabs'] = $tabs;

		if ( isset( $options['include_general'] ) ) {
			$data['config']['include_general'] = (bool) $options['include_general'];
		}
		if ( isset( $options['include_other'] ) ) {
			$data['config']['include_other'] = (bool) $options['include_other'];
		}

		return $this->write( $data );
	}

	/**
	 * Add a new tab and scaffold its template if missing.
	 */
	public function add_tab( $slug, $label, $source = 'custom' ) {
		$data = $this->read();
		$slug = sanitize_key( $slug );

		// Prevent duplicates.
		foreach ( $data['config']['tabs'] as $tab ) {
			if ( $tab['slug'] === $slug ) {
				return false;
			}
		}

		$data['config']['tabs'][] = array(
			'slug'   => $slug,
			'label'  => sanitize_text_field( $label ),
			'source' => $source,
		);

		// Scaffold template content if none exists yet.
		if ( empty( $data['templates'][ $slug ] ) ) {
			if ( $source === 'platform' || $source === 'system' ) {
				$content = $this->integrations->scaffold_tab( $slug );
			} else {
				$content = $this->scaffold_template( $slug, $label, $source );
			}
			$data['templates'][ $slug ] = $content;
		}

		return $this->write( $data );
	}

	/**
	 * Remove a tab from config and drop its template.
	 */
	public function remove_tab( $slug ) {
		$data = $this->read();

		$data['config']['tabs'] = array_values( array_filter( $data['config']['tabs'], function ( $tab ) use ( $slug ) {
			return $tab['slug'] !== $slug;
		} ) );

		unset( $data['templates'][ $slug ] );

		return $this->write( $data );
	}

	/**
	 * Rename a tab (slug + label), moving the template under the new key.
	 */
	public function rename_tab( $old_slug, $new_slug, $new_label = '' ) {
		$data     = $this->read();
		$new_slug = sanitize_key( $new_slug );

		foreach ( $data['config']['tabs'] as &$tab ) {
			if ( $tab['slug'] === $old_slug ) {
				$tab['slug'] = $new_slug;
				if ( $new_label ) {
					$tab['label'] = sanitize_text_field( $new_label );
				}
				break;
			}
		}
		unset( $tab );

		if ( $old_slug !== $new_slug && isset( $data['templates'][ $old_slug ] ) ) {
			$data['templates'][ $new_slug ] = $data['templates'][ $old_slug ];
			unset( $data['templates'][ $old_slug ] );
		}

		return $this->write( $data );
	}

	/**
	 * Update just the label of a tab.
	 */
	public function update_tab_label( $slug, $label ) {
		$data = $this->read();

		foreach ( $data['config']['tabs'] as &$tab ) {
			if ( $tab['slug'] === $slug ) {
				$tab['label'] = sanitize_text_field( $label );
				break;
			}
		}
		unset( $tab );

		return $this->write( $data );
	}

	/**
	 * Reorder tabs by slug array.
	 */
	public function reorder_tabs( $slugs ) {
		$data    = $this->read();
		$indexed = array();

		foreach ( $data['config']['tabs'] as $tab ) {
			$indexed[ $tab['slug'] ] = $tab;
		}

		$reordered = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $indexed[ $slug ] ) ) {
				$reordered[] = $indexed[ $slug ];
				unset( $indexed[ $slug ] );
			}
		}
		// Append any tabs not in the slug list (safety).
		foreach ( $indexed as $tab ) {
			$reordered[] = $tab;
		}

		$data['config']['tabs'] = $reordered;
		return $this->write( $data );
	}

	// ── Public API: templates ───────────────────────────────────────────

	/**
	 * Get raw template HTML for a tab. Returns '' if not set.
	 */
	public function get_template( $slug ) {
		$data = $this->read();
		return isset( $data['templates'][ $slug ] ) ? $data['templates'][ $slug ] : '';
	}

	/**
	 * Save template HTML for a tab.
	 */
	public function save_template( $slug, $html ) {
		$data = $this->read();
		$data['templates'][ $slug ] = $html;
		return $this->write( $data );
	}

	/**
	 * Whether a template exists for the given slug.
	 */
	public function has_template( $slug ) {
		$data = $this->read();
		return isset( $data['templates'][ $slug ] ) && $data['templates'][ $slug ] !== '';
	}

	/**
	 * Get all templates as [ slug => html ].
	 */
	public function get_all_templates() {
		return $this->read()['templates'];
	}

	// ── Public API: export / import ─────────────────────────────────────

	/**
	 * Return full data bundle for export.
	 */
	public function export() {
		$data = $this->read();
		return array(
			'version'     => self::VERSION,
			'exported_at' => gmdate( 'c' ),
			'config'      => $data['config'],
			'templates'   => $data['templates'],
		);
	}

	/**
	 * Import a bundle, replacing all current data.
	 *
	 * @return true|WP_Error
	 */
	public function import( $bundle ) {
		if ( ! is_array( $bundle ) || ! isset( $bundle['version'] ) ) {
			return new WP_Error( 'invalid_bundle', __( 'Invalid bundle: missing version.', 'binary-wp-admin-guide' ) );
		}
		if ( (int) $bundle['version'] !== self::VERSION ) {
			return new WP_Error(
				'version_mismatch',
				sprintf(
					/* translators: 1: bundle version number, 2: expected version number */
					__( 'Unsupported bundle version %1$d (expected %2$d).', 'binary-wp-admin-guide' ),
					(int) $bundle['version'],
					self::VERSION
				)
			);
		}
		if ( ! isset( $bundle['config']['tabs'] ) || ! is_array( $bundle['config']['tabs'] ) ) {
			return new WP_Error( 'invalid_bundle', __( 'Invalid bundle: missing tabs.', 'binary-wp-admin-guide' ) );
		}

		$tabs = array();
		foreach ( $bundle['config']['tabs'] as $tab ) {
			if ( empty( $tab['slug'] ) || empty( $tab['label'] ) ) {
				continue;
			}
			$tabs[] = array(
				'slug'   => sanitize_key( $tab['slug'] ),
				'label'  => sanitize_text_field( $tab['label'] ),
				'source' => isset( $tab['source'] ) ? sanitize_key( $tab['source'] ) : 'custom',
			);
		}

		$templates = array();
		if ( isset( $bundle['templates'] ) && is_array( $bundle['templates'] ) ) {
			foreach ( $bundle['templates'] as $slug => $html ) {
				if ( ! is_string( $html ) ) {
					continue;
				}
				$templates[ sanitize_key( $slug ) ] = wp_kses_post( $html );
			}
		}

		$data = array(
			'version' => self::VERSION,
			'config'  => array(
				'tabs'            => $tabs,
				'include_general' => ! empty( $bundle['config']['include_general'] ),
				'include_other'   => ! empty( $bundle['config']['include_other'] ),
			),
			'templates' => $templates,
		);

		return $this->write( $data );
	}

	// ── Storage ─────────────────────────────────────────────────────────

	private function read() {
		if ( $this->data !== null ) {
			return $this->data;
		}

		$stored = get_option( $this->option_key );

		if ( is_array( $stored ) && isset( $stored['config']['tabs'] ) ) {
			// Ensure templates key exists even if older record is missing it.
			if ( ! isset( $stored['templates'] ) || ! is_array( $stored['templates'] ) ) {
				$stored['templates'] = array();
			}
			$this->data = $stored;
			return $this->data;
		}

		// Try legacy (pre-prefix) option key — carries over data from the
		// previous non-prefixed build. One-shot migration, then cleanup.
		$legacy_option = 'guide_builder_data';
		$legacy_stored = get_option( $legacy_option );
		if ( is_array( $legacy_stored ) && isset( $legacy_stored['config']['tabs'] ) ) {
			if ( ! isset( $legacy_stored['templates'] ) || ! is_array( $legacy_stored['templates'] ) ) {
				$legacy_stored['templates'] = array();
			}
			$this->data = $legacy_stored;
			$this->write( $this->data );
			delete_option( $legacy_option );
			return $this->data;
		}

		// Nothing in DB — try migration from legacy filesystem layout.
		$this->data = $this->migrate_from_files();
		$this->write( $this->data );
		return $this->data;
	}

	private function write( $data ) {
		$data['version'] = self::VERSION;
		if ( ! isset( $data['config'] ) || ! is_array( $data['config'] ) ) {
			$data['config'] = array( 'tabs' => array(), 'include_general' => true, 'include_other' => true );
		}
		if ( ! isset( $data['templates'] ) || ! is_array( $data['templates'] ) ) {
			$data['templates'] = array();
		}
		$this->data = $data;

		return update_option( $this->option_key, $data, false );
	}

	/**
	 * One-shot migration: load legacy guide/templates/config.json and *.html files.
	 */
	private function migrate_from_files() {
		$legacy_dir = $this->context->legacy_guide_dir;
		$legacy_dir = apply_filters( 'guide_builder/legacy_guide_dir', $legacy_dir, $this->context );
		$legacy_dir = apply_filters( $this->context->prefix . '/guide_builder/legacy_guide_dir', $legacy_dir, $this->context );

		if ( ! $legacy_dir ) {
			return array(
				'version'   => self::VERSION,
				'config'    => array( 'tabs' => array(), 'include_general' => true, 'include_other' => true ),
				'templates' => array(),
			);
		}

		$template_dir = trailingslashit( $legacy_dir ) . 'templates/';
		$config_path  = $template_dir . 'config.json';

		$config = array(
			'tabs'            => array(),
			'include_general' => true,
			'include_other'   => true,
		);
		$templates = array();

		// Legacy config.json → tab structure.
		if ( file_exists( $config_path ) ) {
			$json = json_decode( file_get_contents( $config_path ), true );
			if ( is_array( $json ) && isset( $json['tabs'] ) ) {
				$config['tabs'] = $json['tabs'];
				if ( isset( $json['include_general'] ) ) {
					$config['include_general'] = (bool) $json['include_general'];
				}
				if ( isset( $json['include_other'] ) ) {
					$config['include_other'] = (bool) $json['include_other'];
				}
			}
		}

		// Fallback: build tab list by scanning *.html if config.json was absent.
		if ( empty( $config['tabs'] ) && is_dir( $template_dir ) ) {
			$files = glob( $template_dir . '*.html' );
			if ( $files ) {
				foreach ( $files as $file ) {
					$basename = basename( $file, '.html' );
					if ( $basename === 'admin-guide' ) {
						continue; // Intro file, not a tab.
					}
					$content = file_get_contents( $file );
					$label   = ucwords( str_replace( array( '-', '_' ), ' ', $basename ) );
					if ( preg_match( '/<h1[^>]*>(.+?)<\/h1>/i', $content, $m ) ) {
						$label = strip_tags( trim( $m[1] ) );
					}
					$source = 'custom';
					if ( post_type_exists( $basename ) ) {
						$source = 'post_type';
					} elseif ( taxonomy_exists( $basename ) ) {
						$source = 'taxonomy';
					}
					$config['tabs'][] = array(
						'slug'   => $basename,
						'label'  => $label,
						'source' => $source,
					);
				}
			}
		}

		// Load all template *.html files found (including admin-guide intro).
		if ( is_dir( $template_dir ) ) {
			foreach ( glob( $template_dir . '*.html' ) as $file ) {
				$slug = basename( $file, '.html' );
				$templates[ $slug ] = file_get_contents( $file );
			}
		}

		return array(
			'version'   => self::VERSION,
			'config'    => $config,
			'templates' => $templates,
		);
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
