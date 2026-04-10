<?php
/**
 * Context — holds host-provided identifiers and paths for a single booted instance.
 *
 * All runtime strings (option keys, AJAX actions, nonces, asset handles,
 * JS globals) are derived from this object so multiple instances can live
 * side-by-side without collisions.
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Context {

	/** @var string Instance prefix (e.g. 'hfp'). Sanitized to [a-z0-9_]. */
	public $prefix;

	/** @var string Filesystem path to the package root (trailing slash). */
	public $package_path;

	/** @var string URL to the package root (trailing slash). */
	public $package_url;

	/** @var string Package version (used for asset cache-busting). */
	public $package_version;

	/** @var string Filesystem path for generated guide output (trailing slash). */
	public $guide_dir;

	/** @var string Filesystem path to legacy templates dir for one-shot migration. */
	public $legacy_guide_dir;

	/** @var string WP capability required to access admin pages. */
	public $capability;

	/** @var string[] Additional integration JSON directories (host-provided). */
	public $integrations_dirs;

	/** @var array Menu defaults supplied by host at boot time. */
	public $menu_defaults;

	/** @var array Raw boot args, for future use. */
	public $raw_args;

	/**
	 * @param array $args {
	 *     @type string   $prefix            Required. Instance identifier.
	 *     @type string   $package_path      Required. Path to package root.
	 *     @type string   $package_url       Required. URL to package root.
	 *     @type string   $package_version   Optional. Default '1.0'.
	 *     @type string   $guide_dir         Optional. Output dir; auto-detected if omitted.
	 *     @type string   $legacy_guide_dir  Optional. Legacy templates dir for migration.
	 *     @type string   $capability        Optional. Default 'manage_options'.
	 *     @type string[] $integrations_dirs Optional. Extra JSON integration dirs.
	 *     @type array    $menu              Optional. Menu placement defaults.
	 * }
	 */
	public function __construct( array $args ) {
		$this->raw_args          = $args;
		$this->prefix            = self::sanitize_prefix( isset( $args['prefix'] ) ? $args['prefix'] : '' );
		$this->package_path      = trailingslashit( isset( $args['package_path'] ) ? $args['package_path'] : '' );
		$this->package_url       = trailingslashit( isset( $args['package_url'] ) ? $args['package_url'] : '' );
		$this->package_version   = isset( $args['package_version'] ) ? (string) $args['package_version'] : '1.0';
		$this->guide_dir         = trailingslashit( isset( $args['guide_dir'] ) ? $args['guide_dir'] : $this->detect_guide_dir() );
		$this->legacy_guide_dir  = trailingslashit( isset( $args['legacy_guide_dir'] ) ? $args['legacy_guide_dir'] : '' );
		$this->capability        = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
		$this->integrations_dirs = isset( $args['integrations_dirs'] ) ? (array) $args['integrations_dirs'] : array();
		$this->menu_defaults     = isset( $args['menu'] ) ? (array) $args['menu'] : array();
	}

	// ── Derived names ───────────────────────────────────────────────────

	/** wp_option key scoped to this instance. */
	public function option_key( $key ) {
		return $this->prefix . '_admin_guide_' . $key;
	}

	/** AJAX action name scoped to this instance. */
	public function action_name( $action ) {
		return $this->prefix . '_admin_guide_' . $action;
	}

	/** Nonce action name scoped to this instance. */
	public function nonce_action( $suffix = '' ) {
		$base = $this->prefix . '_admin_guide';
		return $suffix ? $base . '_' . $suffix : $base;
	}

	/** Asset handle (JS/CSS) scoped to this instance. */
	public function asset_handle( $name ) {
		return $this->prefix . '-admin-guide-' . $name;
	}

	/** JavaScript global variable name scoped to this instance. */
	public function js_global() {
		return 'adminGuide_' . $this->prefix;
	}

	/** Admin page slug scoped to this instance. */
	public function page_slug( $name = '' ) {
		$base = $this->prefix . '-admin-guide';
		return $name ? $base . '-' . $name : $base;
	}

	// ── Paths ───────────────────────────────────────────────────────────

	/** URL to an asset inside the package's assets/ directory. */
	public function asset_url( $sub = '' ) {
		return $this->package_url . 'assets/' . ltrim( $sub, '/' );
	}

	/** Path to the package-bundled integrations directory. */
	public function bundled_integrations_dir() {
		return $this->package_path . 'integrations/';
	}

	// ── Helpers ─────────────────────────────────────────────────────────

	public static function sanitize_prefix( $prefix ) {
		$prefix = strtolower( (string) $prefix );
		$prefix = preg_replace( '/[^a-z0-9_]/', '_', $prefix );
		$prefix = trim( $prefix, '_' );
		return $prefix ?: 'admin_guide';
	}

	/**
	 * Fallback output directory resolution when host doesn't specify guide_dir.
	 *
	 * Auto-detects based on package_path location:
	 *  - Under WP_PLUGIN_DIR → nearest plugin root + /guide/
	 *  - Under theme dir     → stylesheet dir + /guide/
	 *  - Otherwise           → package_path + guide/
	 */
	private function detect_guide_dir() {
		$pkg = rtrim( str_replace( '\\', '/', $this->package_path ), '/' );
		if ( ! $pkg ) {
			return '';
		}

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$plugin_root = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' );
			if ( $plugin_root && strpos( $pkg, $plugin_root . '/' ) === 0 ) {
				$relative = substr( $pkg, strlen( $plugin_root ) + 1 );
				$parts    = explode( '/', $relative );
				if ( ! empty( $parts[0] ) ) {
					return $plugin_root . '/' . $parts[0] . '/guide/';
				}
			}
		}

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$theme_root = rtrim( str_replace( '\\', '/', get_stylesheet_directory() ), '/' );
			if ( $theme_root && strpos( $pkg, $theme_root . '/' ) === 0 ) {
				return $theme_root . '/guide/';
			}
		}

		return $pkg . '/guide/';
	}
}
