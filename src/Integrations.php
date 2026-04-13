<?php
/**
 * Integration Registry (JSON-driven).
 *
 * Reads *.json from integrations/ directory. Each JSON defines:
 *   slug, name, requires, settings_url, docs_url, placeholders,
 *   system_tabs, tab_sources, tab_templates.
 *
 * Callback functions are lazy-loaded from integrations/functions/.
 * Settings-link placeholders are auto-generated (no callback needed).
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Integrations {

	/** @var Context */
	private $context;

	/** @var array slug => parsed JSON data */
	private $integrations = array();

	/** @var array slug => source directory (for lazy loading of functions) */
	private $integration_dirs = array();

	/** @var bool */
	private $discovered = false;

	/** @var string Bundled integrations directory inside the package. */
	private $bundled_dir;

	/** @var string[] Extra integration directories provided by host at boot. */
	private $extra_dirs;

	public function __construct( Context $context ) {
		$this->context     = $context;
		$this->bundled_dir = $context->bundled_integrations_dir();
		$this->extra_dirs  = array_map( 'trailingslashit', $context->integrations_dirs );
	}

	// ── Public API ──────────────────────────────────────────────────────

	/**
	 * Get all registered integrations.
	 */
	public function get_all() {
		$this->ensure_discovered();
		return $this->integrations;
	}

	/**
	 * Get only active integrations (requires met).
	 */
	public function get_active() {
		$this->ensure_discovered();
		$active = array();

		foreach ( $this->integrations as $slug => $data ) {
			if ( $this->check_requires( $data['requires'] ) ) {
				$active[ $slug ] = $data;
			}
		}

		return $active;
	}

	/**
	 * Check if a specific integration is active.
	 */
	public function is_active( $slug ) {
		$this->ensure_discovered();
		if ( ! isset( $this->integrations[ $slug ] ) ) {
			return false;
		}
		return $this->check_requires( $this->integrations[ $slug ]['requires'] );
	}

	/**
	 * Get active external connections (services that connect to outside platforms).
	 */
	public function get_external() {
		$external = array();
		foreach ( $this->get_active() as $slug => $data ) {
			if ( ! empty( $data['external'] ) ) {
				$external[ $slug ] = $data;
			}
		}
		return $external;
	}

	/**
	 * Check status of all external services.
	 *
	 * Returns array of: [ integration_slug => [ services: [ [ service, description, status, message ] ] ] ]
	 * Status: 'ok', 'warning', 'error', 'unknown'.
	 */
	public function check_external_status() {
		$this->ensure_discovered();
		$results = array();

		foreach ( $this->get_external() as $slug => $data ) {
			$services = array();

			// Load functions file (may contain status checkers).
			$this->load_functions( $slug );

			foreach ( $data['external'] as $ext ) {
				$check    = ! empty( $ext['check'] ) ? $ext['check'] : '';
				$fn_name  = 'guide_check_' . $check;
				$status   = 'unknown';
				$message  = '';

				if ( $check && is_callable( $fn_name ) ) {
					$result  = call_user_func( $fn_name );
					$status  = isset( $result['status'] ) ? $result['status'] : 'unknown';
					$message = isset( $result['message'] ) ? $result['message'] : '';
				}

				$services[] = array(
					'service'     => $ext['service'],
					'description' => isset( $ext['description'] ) ? $ext['description'] : '',
					'status'      => $status,
					'message'     => $message,
				);
			}

			$results[ $slug ] = array(
				'name'         => $data['name'],
				'settings_url' => $data['settings_url'],
				'services'     => $services,
			);
		}

		return $results;
	}

	/**
	 * Register all active integration placeholders into Placeholders.
	 */
	public function register_placeholders( $ph ) {
		$this->ensure_discovered();

		foreach ( $this->integrations as $slug => $data ) {
			if ( ! $this->check_requires( $data['requires'] ) ) {
				continue;
			}

			$name = $data['name'];
			$functions_loaded = false;

			$prefix = ! empty( $data['prefix'] ) ? $data['prefix'] : str_replace( '-', '_', $slug );

			// Auto-generate settings link if not explicitly defined.
			if ( ! empty( $data['settings_url'] ) ) {
				$token = '{{' . $prefix . '_settings_link}}';
				if ( empty( $data['placeholders'][ $token ] ) ) {
					$url   = $data['settings_url'];
					$label = $name . ' Settings';
					$ph->register( $token, function () use ( $url, $label ) {
						return '<a href="' . esc_url( admin_url( $url ) ) . '">' . esc_html( $label ) . '</a>';
					}, $name, 'Link to ' . $name . ' settings' );
				}
			}

			// Auto-generate docs link if present.
			if ( ! empty( $data['docs_url'] ) ) {
				$token = '{{' . $prefix . '_docs_link}}';
				$url   = $data['docs_url'];
				$ph->register( $token, function () use ( $url, $name ) {
					return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $name ) . ' Documentation</a>';
				}, $name, 'Link to ' . $name . ' documentation' );
			}

			// Auto-generate external status placeholder for integrations with external services.
			if ( ! empty( $data['external'] ) ) {
				$token     = '{{' . $prefix . '_external_status}}';
				$int_slug  = $slug;
				$ph->register( $token, function () use ( $int_slug, $name ) {
					return '<div class="guide-external-status" data-integration="' . esc_attr( $int_slug ) . '">'
						. '<em>Loading ' . esc_html( $name ) . ' status...</em></div>';
				}, $name, 'External service status with live check (AJAX)' );
			}

			// Explicit placeholders from JSON.
			if ( ! empty( $data['placeholders'] ) ) {
				foreach ( $data['placeholders'] as $token => $def ) {
					$type = isset( $def['type'] ) ? $def['type'] : 'callback';

					if ( $type === 'settings_link' ) {
						$url   = isset( $def['url'] ) ? $def['url'] : $data['settings_url'];
						$label = isset( $def['label'] ) ? $def['label'] : $name . ' Settings';
						$desc  = isset( $def['description'] ) ? $def['description'] : 'Link to ' . $name . ' settings';

						$ph->register( $token, function () use ( $url, $label ) {
							return '<a href="' . esc_url( admin_url( $url ) ) . '">' . esc_html( $label ) . '</a>';
						}, $name, $desc );

					} elseif ( $type === 'image' ) {
						$img_url = isset( $def['url'] ) ? $def['url'] : '';
						$img_alt = isset( $def['alt'] ) ? $def['alt'] : '';
						$desc    = isset( $def['description'] ) ? $def['description'] : $img_alt;

						$ph->register( $token, function () use ( $img_url, $img_alt ) {
							return '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $img_alt )
								. '" style="max-width:100%;margin:15px 0;border:1px solid #c3c4c7">';
						}, $name, $desc );

					} elseif ( ! empty( $def['callback'] ) ) {
						if ( ! $functions_loaded ) {
							$this->load_functions( $slug );
							$functions_loaded = true;
						}

						$callback = $def['callback'];
						$desc     = isset( $def['description'] ) ? $def['description'] : '';

						if ( is_callable( $callback ) ) {
							$ph->register( $token, $callback, $name, $desc );
						}
					}
				}
			}
		}
	}

	/**
	 * Get system tab options grouped by optgroup for the builder UI.
	 *
	 * @param array $existing Slugs already in config.
	 * @return array [ { group, items: [{ slug, label, source, disabled }] } ]
	 */
	public function get_system_tab_groups( $existing = array() ) {
		$this->ensure_discovered();
		$groups = array();

		foreach ( $this->integrations as $slug => $data ) {
			// System tabs (from default.json: General, Other).
			if ( ! empty( $data['system_tabs'] ) ) {
				$items = array();
				foreach ( $data['system_tabs'] as $st ) {
					$items[] = array(
						'slug'     => $st['slug'],
						'label'    => $st['label'],
						'source'   => 'system',
						'disabled' => (int) in_array( $st['slug'], $existing, true ),
					);
				}
				$groups[] = array( 'group' => 'System', 'items' => $items );
			}

			// Post types / taxonomies (from default.json: tab_sources).
			if ( ! empty( $data['tab_sources']['post_types'] ) ) {
				$items = array();
				if ( function_exists( 'guide_get_content_post_types' ) ) {
					foreach ( guide_get_content_post_types() as $obj ) {
						$items[] = array(
							'slug'     => $obj->name,
							'label'    => $obj->labels->name,
							'source'   => 'post_type',
							'disabled' => (int) in_array( $obj->name, $existing, true ),
						);
					}
				}
				if ( $items ) {
					$groups[] = array( 'group' => 'Post Types', 'items' => $items );
				}
			}

			if ( ! empty( $data['tab_sources']['taxonomies'] ) ) {
				$items = array();
				foreach ( get_taxonomies( array( 'show_ui' => true, 'public' => true ), 'objects' ) as $tax ) {
					$items[] = array(
						'slug'     => $tax->name,
						'label'    => $tax->labels->name,
						'source'   => 'taxonomy',
						'disabled' => (int) in_array( $tax->name, $existing, true ),
					);
				}
				if ( $items ) {
					$groups[] = array( 'group' => 'Taxonomies', 'items' => $items );
				}
			}

			// Integration tab templates.
			if ( ! empty( $data['tab_templates'] ) && $this->check_requires( $data['requires'] ) ) {
				$items = array();
				foreach ( $data['tab_templates'] as $tpl ) {
					$items[] = array(
						'slug'     => $tpl['slug'],
						'label'    => $tpl['label'],
						'source'   => 'platform',
						'disabled' => (int) in_array( $tpl['slug'], $existing, true ),
					);
				}
				if ( $items ) {
					// Theme-based integrations get "Theme" optgroup.
					$group_label = ! empty( $data['requires']['theme'] ) ? 'Theme' : $data['name'];
					$groups[] = array( 'group' => $group_label, 'items' => $items );
				}
			}
		}

		/**
		 * Filter system tab options.
		 */
		$groups = apply_filters( 'guide_builder/system_tabs', $groups, $existing );
		$groups = apply_filters( $this->context->prefix . '/guide_builder/system_tabs', $groups, $existing );
		return $groups;
	}

	/**
	 * Scaffold a tab template.
	 *
	 * Searches in order:
	 *   1. Source directory of a matching integration (package or host-provided)
	 *   2. Bundled templates/ directory
	 */
	public function scaffold_tab( $tab_slug ) {
		$this->ensure_discovered();

		// 1. Check the source dir of the integration that owns this slug.
		if ( isset( $this->integration_dirs[ $tab_slug ] ) ) {
			$file = $this->integration_dirs[ $tab_slug ] . 'templates/' . $tab_slug . '.html';
			if ( file_exists( $file ) ) {
				return file_get_contents( $file );
			}
		}

		// 2. Check bundled package templates dir.
		$file = $this->bundled_dir . 'templates/' . $tab_slug . '.html';
		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		}

		// 3. Search any source dir for a matching template file.
		$dirs = array_merge( array( $this->bundled_dir ), $this->extra_dirs );
		foreach ( $dirs as $dir ) {
			$file = $dir . 'templates/' . $tab_slug . '.html';
			if ( file_exists( $file ) ) {
				return file_get_contents( $file );
			}
		}

		// 4. Basic fallback.
		$name = $tab_slug;
		foreach ( $this->integrations as $slug => $data ) {
			if ( $slug === $tab_slug ) {
				$name = $data['name'];
				break;
			}
		}

		return '<h1>' . esc_html( $name ) . '</h1>';
	}

	/**
	 * Register an integration from external code (hook API — array format).
	 */
	public function register( $slug, $args ) {
		$this->integrations[ $slug ] = wp_parse_args( $args, array(
			'slug'          => $slug,
			'name'          => $slug,
			'requires'      => array(),
			'settings_url'  => '',
			'docs_url'      => '',
			'placeholders'  => array(),
			'system_tabs'   => array(),
			'tab_sources'   => array(),
			'tab_templates' => array(),
		) );
	}

	// ── Discovery ───────────────────────────────────────────────────────

	private function ensure_discovered() {
		if ( $this->discovered ) {
			return;
		}
		$this->discovered = true;

		// Discover in bundled dir first, then host-supplied extra dirs.
		$dirs = array_merge( array( $this->bundled_dir ), $this->extra_dirs );

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( glob( $dir . '*.json' ) as $file ) {
				$json = json_decode( file_get_contents( $file ), true );
				if ( ! is_array( $json ) || empty( $json['slug'] ) ) {
					continue;
				}

				$slug = $json['slug'];
				// Host-supplied extra dirs override bundled definitions if slug collides.
				$this->integrations[ $slug ] = wp_parse_args( $json, array(
					'slug'          => $slug,
					'name'          => $slug,
					'requires'      => array(),
					'settings_url'  => '',
					'docs_url'      => '',
					'placeholders'  => array(),
					'system_tabs'   => array(),
					'tab_sources'   => array(),
					'tab_templates' => array(),
				) );
				$this->integration_dirs[ $slug ] = $dir;
			}
		}

		/**
		 * Fires after JSON integrations are loaded.
		 * External code can register additional integrations here.
		 */
		do_action( 'guide_builder/integrations', $this );
		do_action( $this->context->prefix . '/guide_builder/integrations', $this );
	}

	// ── Requires Resolution ─────────────────────────────────────────────

	/**
	 * Check if requirements are met.
	 *
	 * Supports:
	 *   { "class": "ClassName" }
	 *   { "function": "fn_name" }
	 *   { "taxonomy": "tax_name" }
	 *   { "post_type": "type_name" }
	 *   { "plugin": "dir/file.php" }
	 *   { "constant": "CONST_NAME" }
	 *   { "option": "option_name" }
	 *   { "any": [ ...conditions ] }
	 *   { "all": [ ...conditions ] }
	 *   {} (empty = always active, e.g. default.json)
	 */
	private function check_requires( $requires ) {
		if ( empty( $requires ) ) {
			return true;
		}

		// "any" — OR logic.
		if ( isset( $requires['any'] ) ) {
			foreach ( $requires['any'] as $condition ) {
				if ( $this->check_requires( $condition ) ) {
					return true;
				}
			}
			return false;
		}

		// "all" — AND logic.
		if ( isset( $requires['all'] ) ) {
			foreach ( $requires['all'] as $condition ) {
				if ( ! $this->check_requires( $condition ) ) {
					return false;
				}
			}
			return true;
		}

		// Single condition.
		if ( isset( $requires['class'] ) ) {
			return class_exists( $requires['class'] );
		}
		if ( isset( $requires['function'] ) ) {
			return function_exists( $requires['function'] );
		}
		if ( isset( $requires['taxonomy'] ) ) {
			return taxonomy_exists( $requires['taxonomy'] );
		}
		if ( isset( $requires['post_type'] ) ) {
			return post_type_exists( $requires['post_type'] );
		}
		if ( isset( $requires['plugin'] ) ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return is_plugin_active( $requires['plugin'] );
		}
		if ( isset( $requires['theme'] ) ) {
			$theme = wp_get_theme();
			$slug  = $requires['theme'];
			return ( $theme->get_template() === $slug || $theme->get_stylesheet() === $slug );
		}
		if ( isset( $requires['constant'] ) ) {
			return defined( $requires['constant'] );
		}
		if ( isset( $requires['option'] ) ) {
			return get_option( $requires['option'] ) !== false;
		}

		return false;
	}

	// ── Functions Loader ────────────────────────────────────────────────

	/**
	 * Lazy-load callback functions for an integration.
	 * Looks in the source directory of the integration:
	 *   {source_dir}/functions/{slug}.php
	 *   (with 'wordpress' slug mapped to 'default.php' for historical reasons)
	 */
	private function load_functions( $slug ) {
		$source = isset( $this->integration_dirs[ $slug ] ) ? $this->integration_dirs[ $slug ] : $this->bundled_dir;
		$dir    = $source . 'functions/';

		if ( $slug === 'wordpress' ) {
			$file = $dir . 'default.php';
		} else {
			$file = $dir . $slug . '.php';
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
