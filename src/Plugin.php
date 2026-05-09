<?php
/**
 * Plugin (multiton bootstrap).
 *
 * Each host calls Plugin::boot( $prefix, $args ) to register an instance.
 * Multiple instances can coexist side-by-side, scoped by prefix.
 *
 *   use BinaryWP\AdminGuide\Plugin;
 *
 *   // Minimal — paths auto-detected:
 *   Plugin::boot( 'hfp' );
 *
 *   // With overrides:
 *   Plugin::boot( 'hfp', array(
 *       'guide_dir' => __DIR__ . '/guide/',
 *       'menu'      => array( 'parent' => 'tools.php' ),
 *   ) );
 *
 *   Plugin::get( 'hfp' )->config->get_tabs();
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/** @var Plugin[] prefix => instance */
	private static $instances = array();

	/** @var Context */
	public $context;

	/** @var Placeholders */
	public $placeholders;

	/** @var Integrations */
	public $integrations;

	/** @var Config */
	public $config;

	/** @var Generator */
	public $generator;

	/** @var Admin */
	public $admin;

	/** @var Viewer|null  null when opted out via menu.viewer = false */
	public $viewer;

	// ── Boot / Registry ─────────────────────────────────────────────────

	/**
	 * Boot (or return existing) instance for the given prefix.
	 *
	 * @param string $prefix Instance identifier.
	 * @param array  $args   Context args. See Context::__construct().
	 * @return Plugin
	 */
	public static function boot( $prefix = 'admin_guide', array $args = array() ) {
		$prefix = Context::sanitize_prefix( $prefix );

		if ( isset( self::$instances[ $prefix ] ) ) {
			return self::$instances[ $prefix ];
		}

		$args['prefix']  = $prefix;
		$context         = new Context( $args );
		$instance        = new self( $context );
		self::$instances[ $prefix ] = $instance;

		return $instance;
	}

	/**
	 * Retrieve a booted instance by prefix.
	 *
	 * @param string $prefix
	 * @return Plugin|null
	 */
	public static function get( $prefix ) {
		$prefix = Context::sanitize_prefix( $prefix );
		return isset( self::$instances[ $prefix ] ) ? self::$instances[ $prefix ] : null;
	}

	/**
	 * All booted instances.
	 *
	 * @return Plugin[]
	 */
	public static function all() {
		return self::$instances;
	}

	/**
	 * First-booted instance — used by back-compat single-instance helpers
	 * (e.g. callback functions in integrations/functions/ that can't know
	 * which instance is currently resolving).
	 *
	 * @return Plugin|null
	 */
	public static function first() {
		$all = self::$instances;
		return $all ? reset( $all ) : null;
	}

	// ── Constructor: wires the component graph ─────────────────────────

	private function __construct( Context $context ) {
		$this->context = $context;

		// Register the guide CPT, then run one-time legacy post_type
		// migration ({prefix}_guide_page → {prefix}_guide). The migration
		// marker option short-circuits subsequent boots.
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'migrate_legacy_post_type' ), 11 );

		$this->placeholders = new Placeholders( $context );
		$this->integrations = new Integrations( $context );
		$this->placeholders->set_integrations( $this->integrations );
		$this->config       = new Config( $context, $this->integrations );
		$this->generator    = new Generator( $context, $this->config, $this->placeholders );
		$this->admin        = new Admin( $context, $this->config, $this->generator, $this->placeholders, $this->integrations );

		// Viewer — end-user read-only surface. Opt out via menu.viewer = false.
		$menu            = $context->menu_defaults;
		$viewer_enabled  = ! isset( $menu['viewer'] ) || false !== $menu['viewer'];
		if ( $viewer_enabled ) {
			$this->viewer = new Viewer( $context, $this->config, $this->generator );
		}
	}

	/**
	 * Register the guide CPT.
	 * Prefixed per-instance to support multiple instances.
	 *
	 * Renamed from `{prefix}_guide_page` to `{prefix}_guide` in v0.8.0 to free
	 * up the unprefixed `guide` namespace for content CPTs registered by host
	 * sites (e.g. palmetto-migration's content `guide` CPT). Backward-compat
	 * record-type migration runs once via `migrate_legacy_post_type()` below
	 * — see Plugin::__construct().
	 */
	public function register_post_type() {
		$slug = $this->context->prefix . '_guide';

		if ( post_type_exists( $slug ) ) {
			return;
		}

		register_post_type( $slug, array(
			'labels'       => array(
				'name'          => 'Guide Pages',
				'singular_name' => 'Guide Page',
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'hierarchical' => true,
			'supports'     => array( 'title', 'editor', 'page-attributes', 'revisions' ),
			'show_in_rest' => true,
		) );
	}

	/**
	 * Get the CPT slug for this instance.
	 *
	 * Returns the new `{prefix}_guide` slug. Existing records under the
	 * legacy `{prefix}_guide_page` post_type are migrated by
	 * `migrate_legacy_post_type()` — code paths reading via this getter pick
	 * up the new slug automatically.
	 */
	public function get_post_type() {
		return $this->context->prefix . '_guide';
	}

	/**
	 * One-time backward-compat migration: convert any existing posts of the
	 * legacy `{prefix}_guide_page` post_type to the new `{prefix}_guide`
	 * type. Idempotent — re-runs are no-ops.
	 *
	 * Triggered from Plugin::__construct() on init. Stores a completion
	 * marker option so subsequent boots short-circuit.
	 */
	public function migrate_legacy_post_type() {
		$marker_key = $this->context->prefix . '_admin_guide_pt_migration_v0_8';
		if ( get_option( $marker_key ) === '1' ) {
			return;
		}
		global $wpdb;
		$old = $this->context->prefix . '_guide_page';
		$new = $this->context->prefix . '_guide';
		// Use direct UPDATE — preserves post-id, postmeta, revisions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->update(
			$wpdb->posts,
			array( 'post_type' => $new ),
			array( 'post_type' => $old ),
			array( '%s' ),
			array( '%s' )
		);
		if ( $count > 0 ) {
			// Bust caches — post_type changes affect get_posts results.
			wp_cache_flush();
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules( false );
			}
		}
		update_option( $marker_key, '1' );
	}
}
