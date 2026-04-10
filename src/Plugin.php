<?php
/**
 * Plugin (multiton bootstrap).
 *
 * Each host calls Plugin::boot( $prefix, $args ) to register an instance.
 * Multiple instances can coexist side-by-side, scoped by prefix.
 *
 *   use BinaryWP\AdminGuide\Plugin;
 *
 *   Plugin::boot( 'hfp', array(
 *       'package_path'    => __DIR__ . '/vendor/binary-wp/admin-guide/',
 *       'package_url'     => plugin_dir_url( __FILE__ ) . 'vendor/binary-wp/admin-guide/',
 *       'package_version' => '0.1.0',
 *       'guide_dir'       => __DIR__ . '/guide/',
 *       'menu'            => array( 'viewer_label' => 'Admin Guide' ),
 *   ) );
 *
 *   Plugin::get( 'hfp' )->config->get_tabs();
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/** Text domain shared across all instances of the package. */
	const TEXT_DOMAIN = 'binary-wp-admin-guide';

	/** @var Plugin[] prefix => instance */
	private static $instances = array();

	/** @var bool Guard so textdomain is loaded once per request regardless of instance count. */
	private static $textdomain_loaded = false;

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

	// ── Boot / Registry ─────────────────────────────────────────────────

	/**
	 * Boot (or return existing) instance for the given prefix.
	 *
	 * @param string $prefix Instance identifier.
	 * @param array  $args   Context args. See Context::__construct().
	 * @return Plugin
	 */
	public static function boot( $prefix, array $args = array() ) {
		$prefix = Context::sanitize_prefix( $prefix );

		if ( isset( self::$instances[ $prefix ] ) ) {
			return self::$instances[ $prefix ];
		}

		$args['prefix']  = $prefix;
		$context         = new Context( $args );
		$instance        = new self( $context );
		self::$instances[ $prefix ] = $instance;

		// Register textdomain loader on `init` (required from WP 6.7+).
		if ( ! self::$textdomain_loaded ) {
			self::$textdomain_loaded = true;
			add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		}

		return $instance;
	}

	/**
	 * Load the shared text domain from the package's languages/ directory.
	 *
	 * Runs once per request regardless of how many instances are booted.
	 */
	public static function load_textdomain() {
		$first = self::first();
		if ( ! $first ) {
			return;
		}
		$mo_dir = $first->context->package_path . 'languages/';
		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, self::TEXT_DOMAIN );
		$mo     = $mo_dir . self::TEXT_DOMAIN . '-' . $locale . '.mo';

		if ( file_exists( $mo ) ) {
			load_textdomain( self::TEXT_DOMAIN, $mo );
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
		$this->context      = $context;
		$this->placeholders = new Placeholders( $context );
		$this->integrations = new Integrations( $context );
		$this->placeholders->set_integrations( $this->integrations );
		$this->config       = new Config( $context, $this->integrations );
		$this->generator    = new Generator( $context, $this->config, $this->placeholders );
		$this->admin        = new Admin( $context, $this->config, $this->generator, $this->placeholders, $this->integrations );
	}
}
