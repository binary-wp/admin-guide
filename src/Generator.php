<?php
/**
 * Generator.
 *
 * Reads templates from Config (DB-backed), resolves placeholders,
 * writes HTML snapshots to guide/html/*.html (consumed by the admin guide
 * renderer) and portable .md copies to guide/*.md.
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generator {

	/** @var Context */
	private $context;

	/** @var Config */
	private $config;

	/** @var Placeholders */
	private $placeholders;

	/** @var string Output directory for .md (guide/). */
	private $output_dir;

	/** @var string Output directory for .html snapshots (guide/html/). */
	private $html_dir;

	public function __construct( Context $context, Config $config, Placeholders $placeholders ) {
		$this->context      = $context;
		$this->config       = $config;
		$this->placeholders = $placeholders;

		$guide_dir = $context->guide_dir;
		$guide_dir = apply_filters( 'guide_builder/guide_dir', $guide_dir, $context );
		$guide_dir = apply_filters( $context->prefix . '/guide_builder/guide_dir', $guide_dir, $context );

		$this->output_dir = trailingslashit( $guide_dir );
		$this->html_dir   = $this->output_dir . 'html/';
	}

	// ── Public API ──────────────────────────────────────────────────────

	/**
	 * Generate all guide files from the stored templates.
	 *
	 * Pulls templates from DB (Config), resolves {{placeholders}},
	 * writes an HTML snapshot for fast admin rendering plus a .md copy
	 * for portable "prime" reading outside the admin.
	 */
	public function generate() {
		wp_mkdir_p( $this->output_dir );
		wp_mkdir_p( $this->html_dir );

		$templates = $this->config->get_all_templates();
		if ( ! $templates ) {
			return;
		}

		$converter = null;
		if ( class_exists( 'League\\HTMLToMarkdown\\HtmlConverter' ) ) {
			$converter = new \League\HTMLToMarkdown\HtmlConverter( array(
				'strip_tags' => false,
				'hard_break' => true,
			) );
		}

		foreach ( $templates as $slug => $html ) {
			// Resolve {{placeholders}} in HTML template.
			$resolved_html = $this->placeholders->resolve( $html );

			// Write .html snapshot for fast admin rendering.
			file_put_contents( $this->html_dir . $slug . '.html', $resolved_html );

			// Write .md (convert resolved HTML → Markdown) for portable reading.
			if ( $converter ) {
				$md = $converter->convert( $resolved_html );
			} else {
				$md = $resolved_html;
			}
			file_put_contents( $this->output_dir . $slug . '.md', $md );
		}
	}

	/**
	 * Check if generated guide files exist.
	 *
	 * @return bool
	 */
	public function is_generated() {
		return is_dir( $this->html_dir ) && count( glob( $this->html_dir . '*.html' ) ) > 0;
	}

	/**
	 * Auto-generate if not yet done.
	 */
	public function ensure_generated() {
		if ( ! $this->is_generated() ) {
			$this->generate();
		}
	}

	/**
	 * Get the output directory path.
	 */
	public function get_output_dir() {
		return $this->output_dir;
	}

	/**
	 * Read a rendered guide tab (from HTML snapshot).
	 *
	 * @param string $slug Tab slug (without extension).
	 * @return string HTML content or empty string.
	 */
	public function read_tab( $slug ) {
		$file = $this->html_dir . sanitize_file_name( $slug ) . '.html';
		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		}
		return '';
	}

	/**
	 * Read the intro file (admin-guide).
	 *
	 * @return string HTML content or empty string.
	 */
	public function read_intro() {
		return $this->read_tab( 'admin-guide' );
	}

	/**
	 * Remove generated files for a specific slug.
	 *
	 * Call this before generating when a slug changes,
	 * so the old files don't linger on disk.
	 *
	 * @param string $slug Old slug to clean up.
	 */
	public function remove_files( $slug ) {
		$safe = sanitize_file_name( $slug );
		if ( ! $safe ) {
			return;
		}

		$md   = $this->output_dir . $safe . '.md';
		$html = $this->html_dir . $safe . '.html';

		if ( file_exists( $md ) ) {
			wp_delete_file( $md );
		}
		if ( file_exists( $html ) ) {
			wp_delete_file( $html );
		}
	}
}
