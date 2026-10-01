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

	/** Text domain — equals the WordPress.org slug, so language packs load for it. */
	const TEXT_DOMAIN = 'admin-guide-builder';

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

		if ( ! self::$instances ) {
			add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		}

		$args['prefix']  = $prefix;
		$context         = new Context( $args );
		$instance        = new self( $context );
		self::$instances[ $prefix ] = $instance;

		/**
		 * Fires once an instance is fully wired and registered, so
		 * Plugin::get( $prefix ) works inside the callback. Extensions that
		 * need the component graph (config, generator, admin, …) start here.
		 *
		 * @param Plugin  $plugin
		 * @param Context $context
		 */
		Hooks::action( $context, 'booted', $instance, $context );

		return $instance;
	}

	/**
	 * Load translations: a WordPress.org language pack first (it wins on
	 * conflicts), then the .mo bundled in languages/. The bundled file is the
	 * only source when the package ships inside another plugin via Composer,
	 * where WordPress's just-in-time loader never looks.
	 *
	 * @internal Hooked on init.
	 */
	public static function load_textdomain() {
		$locale = determine_locale();
		$file   = self::TEXT_DOMAIN . '-' . $locale . '.mo';

		if ( is_readable( WP_LANG_DIR . '/plugins/' . $file ) ) {
			load_textdomain( self::TEXT_DOMAIN, WP_LANG_DIR . '/plugins/' . $file, $locale );
		}
		$bundled = dirname( __DIR__ ) . '/languages/' . $file;
		if ( is_readable( $bundled ) ) {
			load_textdomain( self::TEXT_DOMAIN, $bundled, $locale );
		}
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

		/**
		 * Filter the capability required for every guide screen and handler.
		 * Hook it before this instance boots (at file load is fine).
		 *
		 * @param string  $capability Default from the `capability` boot arg ('manage_options').
		 * @param Context $context
		 */
		$context->capability = (string) Hooks::filter( $context, 'capability', $context->capability, $context );

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

		// Site Compare moved to its own package in 0.13.0 and plugs in through
		// the public hooks. Tell hosts still passing the old boot arg.
		if ( isset( $context->raw_args['compare'] ) ) {
			_doing_it_wrong(
				__CLASS__ . '::boot',
				'The `compare` boot arg was removed in 0.13.0. Require binary-wp/site-compare and call \\BinaryWP\\SiteCompare\\SiteCompare::attach( $prefix, $args ) instead.',
				'0.13.0'
			);
		}

		// Public trigger — see regenerate().
		add_action( Hooks::NS . '/regenerate', array( $this, 'handle_regenerate_action' ) );
	}

	/**
	 * Rebuild this instance's snapshots now.
	 *
	 * Snapshots are what the Viewer shows; they only change when something
	 * calls this (the builder does after each edit). Extensions whose
	 * placeholders read live data call it when that data changes:
	 *
	 *     do_action( 'admin_guide_builder/regenerate' );          // every instance
	 *     do_action( 'admin_guide_builder/regenerate', 'myplugin' ); // one instance
	 */
	public function regenerate() {
		$this->generator->generate();
	}

	/** @internal Listener for the `admin_guide_builder/regenerate` action. */
	public function handle_regenerate_action( $prefix = '' ) {
		if ( '' === (string) $prefix || Context::sanitize_prefix( $prefix ) === $this->context->prefix ) {
			$this->regenerate();
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration; caches are flushed below.
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
