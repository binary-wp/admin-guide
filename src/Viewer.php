<?php
/**
 * Viewer — read-only admin page that renders the assembled guide to editors.
 *
 * While Admin builds the guide (nav-menu sortable + WYSIWYG editor), Viewer
 * is the complementary surface that end-user roles see: horizontal nav-tab
 * for top-level guides, WooCommerce-style `.subsubsub` for children, and
 * the generated HTML body underneath.
 *
 * Reads snapshots via Generator::read_tab() with an ensure_generated()
 * fallback so the page always shows something on first open. A "Regenerate"
 * button at the top lets admins force a rebuild after editing tabs.
 *
 * Controlled by the host via the `menu` boot arg:
 *
 *     Plugin::boot( 'myplugin', [
 *         'menu' => [
 *             'parent'       => 'myplugin-options',
 *             'viewer_label' => 'Admin Guide',   // default: 'Admin Guide'
 *             'viewer'       => true,            // set false to opt out
 *         ],
 *     ] );
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Viewer {

	/** @var Context */
	private $context;

	/** @var Config */
	private $config;

	/** @var Generator */
	private $generator;

	/** @var string */
	private $page_slug;

	public function __construct( Context $context, Config $config, Generator $generator ) {
		$this->context   = $context;
		$this->config    = $config;
		$this->generator = $generator;
		// URL: ?page={prefix}-admin-guide-viewer
		$this->page_slug = $context->page_slug( 'viewer' );

		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_post_' . $context->action_name( 'regenerate_viewer' ), array( $this, 'handle_regenerate' ) );
	}

	// ── Menu ────────────────────────────────────────────────────────────

	public function register_menu() {
		$menu   = $this->context->menu_defaults;
		$parent = ! empty( $menu['parent'] ) ? $menu['parent'] : 'tools.php';
		$label  = isset( $menu['viewer_label'] ) ? (string) $menu['viewer_label'] : __( 'Admin Guide', 'binary-wp-admin-guide' );

		add_submenu_page(
			$parent,
			$label,
			$label,
			$this->context->capability,
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	// ── Page render ─────────────────────────────────────────────────────

	public function render_page() {
		$this->generator->ensure_generated();

		$tabs = $this->config->get_tabs(); // top-level only: [ slug => label ]

		$active = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : '';
		if ( $active === '' || ! isset( $tabs[ $active ] ) ) {
			$active = (string) array_key_first( $tabs );
		}

		$regenerated = isset( $_GET['regen'] ) && '1' === (string) $_GET['regen'];
		$regen_url   = admin_url( 'admin-post.php' );
		?>
		<div class="wrap binary-wp-admin-guide-viewer">
			<h1 style="display:flex;align-items:center;gap:12px;">
				<?php
				$menu  = $this->context->menu_defaults;
				$title = isset( $menu['viewer_label'] ) ? (string) $menu['viewer_label'] : __( 'Admin Guide', 'binary-wp-admin-guide' );
				echo esc_html( $title );
				?>
				<form method="post" action="<?php echo esc_url( $regen_url ); ?>" style="margin:0;">
					<?php wp_nonce_field( $this->context->nonce_action( 'regenerate_viewer' ) ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( $this->context->action_name( 'regenerate_viewer' ) ); ?>">
					<?php if ( $active ) : ?>
						<input type="hidden" name="return_section" value="<?php echo esc_attr( $active ); ?>">
					<?php endif; ?>
					<button type="submit" class="button button-small">↻ <?php esc_html_e( 'Regenerate', 'binary-wp-admin-guide' ); ?></button>
				</form>
			</h1>

			<?php if ( $regenerated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Guide regenerated.', 'binary-wp-admin-guide' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $tabs ) ) : ?>
				<p>
					<em>
						<?php
						printf(
							/* translators: %s: link to the Guide Builder admin page */
							esc_html__( 'No guide tabs yet. Use %s to create some.', 'binary-wp-admin-guide' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->context->page_slug( 'builder' ) ) ) . '">' . esc_html__( 'Guide Builder', 'binary-wp-admin-guide' ) . '</a>'
						);
						?>
					</em>
				</p>
			<?php else : ?>

				<h2 class="nav-tab-wrapper" style="margin-bottom:0;">
					<?php foreach ( $tabs as $slug => $label ) :
						$url     = add_query_arg(
							array( 'page' => $this->page_slug, 'section' => $slug ),
							admin_url( 'admin.php' )
						);
						$classes = 'nav-tab' . ( $slug === $active ? ' nav-tab-active' : '' );
					?>
						<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $url ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</h2>

				<div class="binary-wp-admin-guide-viewer__body" style="background:#fff;border:1px solid #c3c4c7;border-top:0;padding:20px 24px;max-width:1000px;">
					<?php $this->render_active_tab( $active ); ?>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the active tab's content — children sub-nav if any, then the
	 * actual HTML generated by Generator::read_tab().
	 */
	private function render_active_tab( $active ) {
		$children = $this->config->get_children( $active );
		$target   = $active;

		if ( ! empty( $children ) ) {
			$requested = isset( $_GET['child'] ) ? sanitize_key( wp_unslash( (string) $_GET['child'] ) ) : '';
			if ( $requested === '' || ! isset( $children[ $requested ] ) ) {
				$requested = (string) array_key_first( $children );
			}
			$target = $requested;

			echo '<ul class="subsubsub" style="margin-bottom:1em;">';
			$last = array_key_last( $children );
			foreach ( $children as $cslug => $clabel ) {
				$curl = add_query_arg(
					array( 'page' => $this->page_slug, 'section' => $active, 'child' => $cslug ),
					admin_url( 'admin.php' )
				);
				$cls = $cslug === $target ? 'current' : '';
				printf(
					'<li><a href="%s" class="%s">%s</a>%s</li>',
					esc_url( $curl ),
					esc_attr( $cls ),
					esc_html( $clabel ),
					$cslug === $last ? '' : ' |'
				);
			}
			echo '</ul>';
		}

		$html = $this->generator->read_tab( $target );
		if ( $html === '' ) {
			echo '<p><em>' . esc_html__( 'No content generated for this tab yet. Click Regenerate.', 'binary-wp-admin-guide' ) . '</em></p>';
			return;
		}
		// Trusted — generated from stored CPT content + registered placeholder callbacks.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// ── Regenerate handler ──────────────────────────────────────────────

	public function handle_regenerate() {
		if ( ! current_user_can( $this->context->capability ) ) {
			wp_die( esc_html__( 'Forbidden', 'binary-wp-admin-guide' ), 403 );
		}
		check_admin_referer( $this->context->nonce_action( 'regenerate_viewer' ) );

		$this->generator->generate();

		$args = array( 'page' => $this->page_slug, 'regen' => '1' );
		if ( ! empty( $_POST['return_section'] ) ) {
			$args['section'] = sanitize_key( wp_unslash( (string) $_POST['return_section'] ) );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
