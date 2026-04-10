<?php
/**
 * Placeholder Registry.
 *
 * Per-instance registry: register, resolve, get.
 * Placeholders registered via Integrations (JSON-driven) and via
 * the guide_builder/placeholders hook (modules/external).
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Placeholders {

	/** @var Context */
	private $context;

	/** @var array token => [ callback, group, description ] */
	private $placeholders = array();

	/** @var bool */
	private $initialized = false;

	/** @var Integrations|null Injected by Plugin after construction. */
	private $integrations;

	public function __construct( Context $context ) {
		$this->context = $context;

		// Let modules register via per-instance hook (fires early).
		do_action( 'guide_builder/placeholders', $this, $context );
		do_action( $context->prefix . '/guide_builder/placeholders', $this, $context );
	}

	/** Used by Plugin to inject the integrations instance. */
	public function set_integrations( Integrations $integrations ) {
		$this->integrations = $integrations;
	}

	// ── Public API ──────────────────────────────────────────────────────

	/**
	 * Register a placeholder.
	 *
	 * @param string   $token       Token including braces, e.g. '{{my_token}}'.
	 * @param callable $callback    Returns HTML string.
	 * @param string   $group       Group name for sidebar (e.g. 'WordPress', 'WooCommerce').
	 * @param string   $description Short description for builder UI.
	 */
	public function register( $token, $callback, $group = '', $description = '' ) {
		$this->placeholders[ $token ] = array(
			'callback'    => $callback,
			'group'       => $group ?: 'Custom',
			'description' => $description,
		);
	}

	/**
	 * Get all registered placeholders.
	 */
	public function get_all() {
		$this->ensure_initialized();
		return $this->placeholders;
	}

	/**
	 * Get placeholders grouped by group name.
	 */
	public function get_by_group() {
		$this->ensure_initialized();
		$grouped = array();

		foreach ( $this->placeholders as $token => $data ) {
			$group = $data['group'];
			if ( ! isset( $grouped[ $group ] ) ) {
				$grouped[ $group ] = array();
			}
			$grouped[ $group ][ $token ] = $data;
		}

		return $grouped;
	}

	/**
	 * Resolve all placeholders in content.
	 */
	public function resolve( $content ) {
		$this->ensure_initialized();

		foreach ( $this->placeholders as $token => $data ) {
			if ( strpos( $content, $token ) === false ) {
				continue;
			}

			$replacement = call_user_func( $data['callback'] );
			$block       = "\n\n" . $replacement . "\n\n";

			$content = str_replace( '<p>' . $token . '</p>', $replacement, $content );
			$content = str_replace( $token, $block, $content );
		}

		return $content;
	}

	// ── Deferred Init ───────────────────────────────────────────────────

	/**
	 * Load integration-provided placeholders on first use.
	 * By this point all plugins are loaded, so requires checks work.
	 */
	private function ensure_initialized() {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		if ( $this->integrations ) {
			$this->integrations->register_placeholders( $this );
		}
	}
}
