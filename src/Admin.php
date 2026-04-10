<?php
/**
 * Admin Page.
 *
 * Single-page builder: sortable tab list with inline accordion editors
 * and a shared placeholder palette sidebar.
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/** @var Context */
	private $context;

	/** @var Config */
	private $config;

	/** @var Generator */
	private $generator;

	/** @var Placeholders */
	private $placeholders;

	/** @var Integrations */
	private $integrations;

	/** @var string Admin page slug, derived from context prefix. */
	private $page_slug;

	public function __construct(
		Context $context,
		Config $config,
		Generator $generator,
		Placeholders $placeholders,
		Integrations $integrations
	) {
		$this->context      = $context;
		$this->config       = $config;
		$this->generator    = $generator;
		$this->placeholders = $placeholders;
		$this->integrations = $integrations;
		$this->page_slug    = $context->page_slug( 'builder' );

		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
		add_action( 'wp_ajax_' . $context->action_name( 'reorder' ),      array( $this, 'ajax_reorder' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'add_tab' ),      array( $this, 'ajax_add_tab' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'remove_tab' ),   array( $this, 'ajax_remove_tab' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'save_tab' ),     array( $this, 'ajax_save_tab' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'load_tab' ),     array( $this, 'ajax_load_tab' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'check_status' ), array( $this, 'ajax_check_status' ) );
		add_action( 'admin_post_' . $context->action_name( 'export' ),    array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . $context->action_name( 'import' ),    array( $this, 'handle_import' ) );
	}

	// ── Menu ────────────────────────────────────────────────────────────

	public function register_menu() {
		$default_parent = ! empty( $this->context->menu_defaults['parent'] ) ? $this->context->menu_defaults['parent'] : '';
		$parent         = apply_filters( 'guide_builder/parent_menu', $default_parent, $this->context );
		$parent         = apply_filters( $this->context->prefix . '/guide_builder/parent_menu', $parent, $this->context );

		if ( ! $parent ) {
			return; // No parent configured — skip builder menu registration for now.
		}

		// Label default is translatable; hosts can still override via menu_defaults.
		$label = isset( $this->context->menu_defaults['builder_label'] )
			? $this->context->menu_defaults['builder_label']
			: __( 'Guide Builder', 'binary-wp-admin-guide' );

		add_submenu_page(
			$parent,
			$label,
			$label,
			$this->context->capability,
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	// ── Render ──────────────────────────────────────────────────────────

	public function render_page() {
		$tabs          = $this->config->get_tab_entries();
		$script_handle = $this->context->asset_handle( 'builder' );
		$style_handle  = $this->context->asset_handle( 'builder' );
		$version       = $this->context->package_version;

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_editor();

		wp_enqueue_script(
			$script_handle,
			$this->context->asset_url( 'guide-builder.js' ),
			array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-tooltip' ),
			$version,
			true
		);
		// Only one builder page renders per request, so a fixed JS global is safe.
		wp_localize_script( $script_handle, 'guideBuilder', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( $this->context->nonce_action() ),
			'systemTabs'   => $this->get_system_tab_options(),
			'placeholders' => $this->get_placeholder_palette(),
			'tinymceCss'   => $this->context->asset_url( 'guide-tinymce.css' ),
			'actions'      => array(
				'reorder'     => $this->context->action_name( 'reorder' ),
				'addTab'      => $this->context->action_name( 'add_tab' ),
				'removeTab'   => $this->context->action_name( 'remove_tab' ),
				'saveTab'     => $this->context->action_name( 'save_tab' ),
				'loadTab'     => $this->context->action_name( 'load_tab' ),
				'checkStatus' => $this->context->action_name( 'check_status' ),
			),
		) );
		wp_enqueue_style(
			$style_handle,
			$this->context->asset_url( 'guide-builder.css' ),
			array(),
			$version
		);

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . rawurlencode( $this->context->action_name( 'export' ) ) ),
			$this->context->nonce_action( 'export' )
		);
		$import_action    = $this->context->action_name( 'import' );
		$import_nonce     = $this->context->nonce_action( 'import' );
		$import_form_id   = $this->context->page_slug( 'import-form' );
		$import_btn_id    = $this->context->page_slug( 'import-btn' );
		$import_cancel_id = $this->context->page_slug( 'import-cancel' );

		$builder_label = isset( $this->context->menu_defaults['builder_label'] )
			? $this->context->menu_defaults['builder_label']
			: __( 'Guide Builder', 'binary-wp-admin-guide' );
		?>
		<div class="wrap">
			<h1 style="display:flex;align-items:center;gap:10px">
				<?php echo esc_html( $builder_label ); ?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export', 'binary-wp-admin-guide' ); ?></a>
				<button type="button" class="page-title-action" id="<?php echo esc_attr( $import_btn_id ); ?>"><?php esc_html_e( 'Import', 'binary-wp-admin-guide' ); ?></button>
			</h1>

			<form id="<?php echo esc_attr( $import_form_id ); ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;margin:10px 0;padding:12px;background:#fff;border:1px solid #c3c4c7;max-width:600px">
				<input type="hidden" name="action" value="<?php echo esc_attr( $import_action ); ?>">
				<?php wp_nonce_field( $import_nonce ); ?>
				<p style="margin-top:0"><strong><?php esc_html_e( 'Import guide bundle', 'binary-wp-admin-guide' ); ?></strong> — <?php esc_html_e( 'this will replace all current tabs and templates.', 'binary-wp-admin-guide' ); ?></p>
				<p>
					<input type="file" name="bundle" accept="application/json,.json" required>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Import &amp; Replace', 'binary-wp-admin-guide' ); ?></button>
					<button type="button" class="button" id="<?php echo esc_attr( $import_cancel_id ); ?>"><?php esc_html_e( 'Cancel', 'binary-wp-admin-guide' ); ?></button>
				</p>
			</form>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Guide updated and regenerated.', 'binary-wp-admin-guide' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Guide bundle imported.', 'binary-wp-admin-guide' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['import_error'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( wp_unslash( $_GET['import_error'] ) ); ?></p></div>
			<?php endif; ?>

			<div id="guide-builder-app">
				<div class="guide-builder-layout">

					<!-- Main Column -->
					<div class="guide-builder-main">

						<h2 style="display:flex;align-items:center;gap:15px">
							<?php esc_html_e( 'Guides', 'binary-wp-admin-guide' ); ?>
							<button type="button" id="guide-builder-save-all" class="button button-primary" style="display:none"><?php esc_html_e( 'Save Changes', 'binary-wp-admin-guide' ); ?></button>
							<span id="guide-builder-save-status" style="font-size:13px;font-weight:normal;color:#00a32a"></span>
						</h2>
						<p class="description"><?php esc_html_e( 'Drag to reorder. Click to expand and edit content.', 'binary-wp-admin-guide' ); ?></p>

						<ul id="guide-tabs-sortable" class="guide-tabs-list">
							<?php foreach ( $tabs as $tab ) :
								$content = $this->config->get_template( $tab['slug'] );
								$content = preg_replace(
									'/\{\{([a-z_]+)\}\}/',
									'<span class="mceNonEditable guide-ph" data-ph="{{$1}}" contenteditable="false">{{$1}}</span>',
									$content
								);
							?>
								<li data-slug="<?php echo esc_attr( $tab['slug'] ); ?>" data-source="<?php echo esc_attr( $tab['source'] ); ?>" class="guide-tab-item">
									<div class="guide-tab-header">
										<span class="guide-tab-handle dashicons dashicons-menu"></span>
										<span class="guide-tab-label"><?php echo esc_html( $tab['label'] ); ?></span>
										<span class="guide-tab-source guide-tab-source--<?php echo esc_attr( $tab['source'] ); ?>"><?php echo esc_html( $this->resolve_source_label( $tab ) ); ?></span>
										<span class="guide-tab-toggle dashicons dashicons-arrow-right-alt2"></span>
										<button type="button" class="guide-tab-remove button-link" data-slug="<?php echo esc_attr( $tab['slug'] ); ?>" title="<?php esc_attr_e( 'Remove', 'binary-wp-admin-guide' ); ?>">
											<span class="dashicons dashicons-no-alt"></span>
										</button>
									</div>
									<div class="guide-tab-editor" style="display:none">
										<div class="guide-tab-meta">
											<label><?php esc_html_e( 'Label:', 'binary-wp-admin-guide' ); ?> <input type="text" class="guide-tab-meta-label regular-text" value="<?php echo esc_attr( $tab['label'] ); ?>"></label>
											<label><?php esc_html_e( 'Slug:', 'binary-wp-admin-guide' ); ?> <input type="text" class="guide-tab-meta-slug" value="<?php echo esc_attr( $tab['slug'] ); ?>" style="width:160px"<?php echo $tab['source'] !== 'custom' ? ' readonly' : ''; ?>></label>
										</div>
										<div class="guide-tab-editor-area">
											<textarea class="guide-tab-textarea" id="guide-editor-<?php echo esc_attr( $tab['slug'] ); ?>"><?php echo esc_textarea( $content ); ?></textarea>
										</div>
										<div class="guide-tab-actions">
											<button type="button" class="button button-primary guide-tab-save" data-slug="<?php echo esc_attr( $tab['slug'] ); ?>"><?php esc_html_e( 'Save', 'binary-wp-admin-guide' ); ?></button>
											<span class="guide-tab-save-status"></span>
										</div>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>

						<hr>

						<h2><?php esc_html_e( 'Add Guide', 'binary-wp-admin-guide' ); ?></h2>
						<fieldset class="guide-add-fieldset">
							<legend><?php esc_html_e( 'System Guide', 'binary-wp-admin-guide' ); ?></legend>
							<div class="guide-add-inline">
								<select id="guide-add-system"><option value=""><?php esc_html_e( '— Select source —', 'binary-wp-admin-guide' ); ?></option></select>
								<button type="button" id="guide-add-system-btn" class="button"><?php esc_html_e( 'Add', 'binary-wp-admin-guide' ); ?></button>
							</div>
						</fieldset>
						<fieldset class="guide-add-fieldset">
							<legend><?php esc_html_e( 'Custom Guide', 'binary-wp-admin-guide' ); ?></legend>
							<div class="guide-add-inline">
								<input type="text" id="guide-add-custom-slug" placeholder="<?php esc_attr_e( 'slug', 'binary-wp-admin-guide' ); ?>" style="width:120px">
								<input type="text" id="guide-add-custom-label" placeholder="<?php esc_attr_e( 'Label', 'binary-wp-admin-guide' ); ?>" style="width:180px">
								<button type="button" id="guide-add-custom-btn" class="button"><?php esc_html_e( 'Add', 'binary-wp-admin-guide' ); ?></button>
							</div>
						</fieldset>

					</div>

					<!-- Sidebar: Placeholder Palette -->
					<div class="guide-builder-sidebar" id="guide-placeholder-palette">
						<h3><?php esc_html_e( 'Placeholders', 'binary-wp-admin-guide' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Click to insert at cursor, or drag into the editor.', 'binary-wp-admin-guide' ); ?></p>
						<div id="guide-placeholder-list"></div>
					</div>

				</div>
			</div>
		</div>
		<script>
		jQuery(function($){
			var $form = $('#<?php echo esc_js( $import_form_id ); ?>');
			$('#<?php echo esc_js( $import_btn_id ); ?>').on('click', function(){ $form.show(); });
			$('#<?php echo esc_js( $import_cancel_id ); ?>').on('click', function(){ $form.hide(); });
		});
		</script>
		<?php
	}

	// ── AJAX Handlers ───────────────────────────────────────────────────

	/**
	 * Verify AJAX nonce + capability. Bails with JSON error on failure.
	 */
	private function verify_ajax() {
		check_ajax_referer( $this->context->nonce_action(), 'nonce' );
		if ( ! current_user_can( $this->context->capability ) ) {
			wp_send_json_error( __( 'Permission denied.', 'binary-wp-admin-guide' ) );
		}
	}

	public function ajax_reorder() {
		$this->verify_ajax();

		$slugs = isset( $_POST['slugs'] ) ? array_map( 'sanitize_key', (array) $_POST['slugs'] ) : array();
		$this->config->reorder_tabs( $slugs );
		$this->generator->generate();
		wp_send_json_success();
	}

	public function ajax_add_tab() {
		$this->verify_ajax();

		$slug   = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$label  = isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : 'custom';

		if ( ! $slug || ! $label ) wp_send_json_error( __( 'Slug and label are required.', 'binary-wp-admin-guide' ) );

		$result = $this->config->add_tab( $slug, $label, $source );
		if ( ! $result ) wp_send_json_error( __( 'Tab already exists.', 'binary-wp-admin-guide' ) );

		$this->generator->generate();
		wp_send_json_success( array( 'slug' => $slug, 'label' => $label, 'source' => $source ) );
	}

	public function ajax_remove_tab() {
		$this->verify_ajax();

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		if ( ! $slug ) wp_send_json_error( __( 'Slug is required.', 'binary-wp-admin-guide' ) );

		$this->config->remove_tab( $slug );
		$this->generator->generate();
		wp_send_json_success();
	}

	/**
	 * Load tab template content for the inline editor.
	 */
	public function ajax_load_tab() {
		$this->verify_ajax();

		$slug    = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$content = $this->config->get_template( $slug );

		// Transform {{tokens}} to pills for TinyMCE.
		$content = preg_replace(
			'/\{\{([a-z_]+)\}\}/',
			'<span class="mceNonEditable guide-ph" data-ph="{{$1}}" contenteditable="false">{{$1}}</span>',
			$content
		);

		wp_send_json_success( array( 'content' => $content ) );
	}

	/**
	 * Save tab content from inline editor via AJAX.
	 */
	public function ajax_save_tab() {
		$this->verify_ajax();

		$old_slug  = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$new_slug  = isset( $_POST['new_slug'] ) ? sanitize_key( $_POST['new_slug'] ) : $old_slug;
		$new_label = isset( $_POST['new_label'] ) ? sanitize_text_field( wp_unslash( $_POST['new_label'] ) ) : '';
		$content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( ! $old_slug ) wp_send_json_error( __( 'Slug is required.', 'binary-wp-admin-guide' ) );

		// Convert pill spans back to plain {{tokens}}.
		$content = preg_replace(
			'/<span[^>]*class="[^"]*guide-ph[^"]*"[^>]*data-ph="(\{\{[a-z_]+\}\})"[^>]*>.*?<\/span>/s',
			'$1',
			$content
		);

		// Handle slug rename (moves template under new key in DB).
		if ( $new_slug && $new_slug !== $old_slug ) {
			$this->config->rename_tab( $old_slug, $new_slug, $new_label );
		} elseif ( $new_label ) {
			$this->config->update_tab_label( $old_slug, $new_label );
		}

		$save_slug = $new_slug ?: $old_slug;
		$this->config->save_template( $save_slug, $content );

		$this->generator->generate();

		wp_send_json_success( array( 'slug' => $save_slug, 'label' => $new_label ) );
	}

	/**
	 * Handle export — streams a JSON bundle as a file download.
	 */
	public function handle_export() {
		if ( ! current_user_can( $this->context->capability ) ) {
			wp_die( esc_html__( 'Permission denied.', 'binary-wp-admin-guide' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $this->context->nonce_action( 'export' ) );

		$bundle   = $this->config->export();
		$filename = $this->context->prefix . '-admin-guide-export-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Handle import — reads uploaded JSON, replaces the current bundle.
	 */
	public function handle_import() {
		if ( ! current_user_can( $this->context->capability ) ) {
			wp_die( esc_html__( 'Permission denied.', 'binary-wp-admin-guide' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $this->context->nonce_action( 'import' ) );

		$redirect_base = admin_url( 'admin.php?page=' . $this->page_slug );

		if ( empty( $_FILES['bundle'] ) || ! empty( $_FILES['bundle']['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'No file uploaded.', 'binary-wp-admin-guide' ) ), $redirect_base ) );
			exit;
		}

		$tmp_name = isset( $_FILES['bundle']['tmp_name'] ) ? $_FILES['bundle']['tmp_name'] : '';
		if ( ! $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'Invalid upload.', 'binary-wp-admin-guide' ) ), $redirect_base ) );
			exit;
		}

		$json   = file_get_contents( $tmp_name );
		$bundle = json_decode( $json, true );

		if ( ! is_array( $bundle ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'File is not valid JSON.', 'binary-wp-admin-guide' ) ), $redirect_base ) );
			exit;
		}

		$result = $this->config->import( $bundle );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( $result->get_error_message() ), $redirect_base ) );
			exit;
		}

		// Regenerate html/md snapshots from the new data.
		$this->generator->generate();

		wp_safe_redirect( add_query_arg( 'imported', '1', $redirect_base ) );
		exit;
	}

	/**
	 * AJAX: check external service status.
	 * Pass ?integration=slug for single, omit for all.
	 */
	public function ajax_check_status() {
		$this->verify_ajax();

		$results = $this->integrations->check_external_status();

		// Filter to single integration if requested.
		$slug = isset( $_POST['integration'] ) ? sanitize_key( $_POST['integration'] ) : '';
		if ( $slug && isset( $results[ $slug ] ) ) {
			$results = array( $slug => $results[ $slug ] );
		}

		wp_send_json_success( $results );
	}

	// ── Data Helpers ────────────────────────────────────────────────────

	/**
	 * Resolve source label for display — show integration/plugin name instead of raw source type.
	 */
	private function resolve_source_label( $tab ) {
		$source = $tab['source'];
		$slug   = $tab['slug'];

		if ( $source === 'custom' ) {
			return __( 'Custom', 'binary-wp-admin-guide' );
		}
		if ( $source === 'system' ) {
			return __( 'System', 'binary-wp-admin-guide' );
		}
		if ( $source === 'post_type' ) {
			return __( 'Post Type', 'binary-wp-admin-guide' );
		}
		if ( $source === 'taxonomy' ) {
			return __( 'Taxonomy', 'binary-wp-admin-guide' );
		}
		if ( $source === 'platform' ) {
			// Find integration name by matching tab slug against tab_templates.
			foreach ( $this->integrations->get_all() as $int_slug => $data ) {
				if ( ! empty( $data['tab_templates'] ) ) {
					foreach ( $data['tab_templates'] as $tpl ) {
						if ( $tpl['slug'] === $slug ) {
							return $data['name'];
						}
					}
				}
				// Fallback: slug matches integration slug.
				if ( $int_slug === $slug ) {
					return $data['name'];
				}
			}
			return 'platform';
		}

		return $source;
	}

	private function get_system_tab_options() {
		$existing = array_column( $this->config->get_tab_entries(), 'slug' );
		return $this->integrations->get_system_tab_groups( $existing );
	}

	private function get_placeholder_palette() {
		$palette = array();
		foreach ( $this->placeholders->get_by_group() as $group => $items ) {
			foreach ( $items as $token => $data ) {
				$palette[] = array( 'token' => $token, 'group' => $group, 'description' => $data['description'] );
			}
		}
		return $palette;
	}
}
