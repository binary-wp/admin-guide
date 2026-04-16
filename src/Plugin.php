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

		// Register the guide page CPT.
		add_action( 'init', array( $this, 'register_post_type' ) );

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
	 * Register the guide_page CPT.
	 * Prefixed per-instance to support multiple instances.
	 */
	public function register_post_type() {
		$slug = $this->context->prefix . '_guide_page';

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
	 */
	public function get_post_type() {
		return $this->context->prefix . '_guide_page';
	}
}
