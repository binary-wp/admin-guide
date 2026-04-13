<?php
/**
 * Admin Pages.
 *
 * Builder page: nav-menu style hierarchical sortable list.
 * Editor page: standalone TinyMCE with placeholder palette sidebar.
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

	/** @var string Builder page slug. */
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

		// AJAX handlers.
		$prefix = $context->prefix;
		add_action( 'wp_ajax_' . $context->action_name( 'save_order' ),    array( $this, 'ajax_save_order' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'add_guide' ),     array( $this, 'ajax_add_guide' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'remove_guide' ),  array( $this, 'ajax_remove_guide' ) );
		add_action( 'wp_ajax_' . $context->action_name( 'check_status' ),  array( $this, 'ajax_check_status' ) );

		// Form handlers (editor page save).
		add_action( 'admin_init', array( $this, 'handle_editor_save' ) );

		// Export/Import.
		add_action( 'admin_post_' . $context->action_name( 'export' ), array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . $context->action_name( 'import' ), array( $this, 'handle_import' ) );
	}

	// ── Menu ────────────────────────────────────────────────────────────

	public function register_menu() {
		$parent = ! empty( $this->context->menu_defaults['parent'] ) ? $this->context->menu_defaults['parent'] : 'tools.php';

		add_submenu_page(
			$parent,
			'Guide Builder',
			'Guide Builder',
			$this->context->capability,
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	// ── Page Router ─────────────────────────────────────────────────────

	public function render_page() {
		if ( isset( $_GET['edit'] ) ) {
			$this->render_editor_page( (int) $_GET['edit'] );
		} else {
			$this->render_builder_page();
		}
	}

	// ── Builder Page (nav-menu style) ───────────────────────────────────

	private function render_builder_page() {
		$guides = $this->config->get_all_guides();
		$assets = $this->context->package_url . 'assets/';
		$ver    = $this->context->package_version;

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'nav-menus' ); // WP core nav-menu styles for item blocks.
		wp_enqueue_script(
			$this->context->asset_handle( 'builder' ),
			$assets . 'guide-builder.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			$ver,
			true
		);
		wp_localize_script( $this->context->asset_handle( 'builder' ), 'guideBuilder', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( $this->context->nonce_action() ),
			'actions'    => array(
				'save_order'   => $this->context->action_name( 'save_order' ),
				'add_guide'    => $this->context->action_name( 'add_guide' ),
				'remove_guide' => $this->context->action_name( 'remove_guide' ),
				'check_status' => $this->context->action_name( 'check_status' ),
			),
			'systemTabs' => $this->integrations->get_system_tab_groups(
				array_column( $this->config->get_tab_entries(), 'slug' )
			),
			'editUrl'    => admin_url( 'admin.php?page=' . $this->page_slug . '&edit=' ),
			'maxDepth'   => 1,
		) );
		wp_enqueue_style(
			$this->context->asset_handle( 'builder' ),
			$assets . 'guide-builder.css',
			array(),
			$ver
		);

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $this->context->action_name( 'export' ) ),
			$this->context->nonce_action()
		);

		?>
		<div class="wrap nav-menus-php">
			<h1 style="display:flex;align-items:center;gap:15px">
				Guide Builder
				<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action">Export</a>
				<button type="button" id="guide-import-btn" class="page-title-action">Import</button>
			</h1>

			<form id="guide-import-form" method="post" enctype="multipart/form-data"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none">
				<?php wp_nonce_field( $this->context->nonce_action() ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $this->context->action_name( 'import' ) ); ?>">
				<input type="file" name="guide_import_file" accept=".json">
				<button type="submit" class="button">Upload</button>
			</form>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Guide updated and regenerated.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Guide imported successfully.</p></div>
			<?php endif; ?>

			<div class="guide-builder-layout">

				<!-- Main Column -->
				<div class="guide-builder-main">

					<h2 style="display:flex;align-items:center;gap:15px">
						Guides
						<button type="button" id="guide-save-order" class="button button-primary" style="display:none">Save Structure</button>
						<span id="guide-save-status" style="font-size:13px;font-weight:normal;color:#00a32a"></span>
					</h2>
					<p class="description">Drag to reorder and nest (drag right to make a child). Click title to edit content.</p>

					<div class="postbox">
				<div class="inside" style="margin:0;padding:0">
					<ul id="guide-sortable" class="menu ui-sortable">
						<?php foreach ( $guides as $guide ) :
							$edit_url = admin_url( 'admin.php?page=' . $this->page_slug . '&edit=' . $guide['id'] );
							$source_label = $this->resolve_source_label( $guide );
						?>
							<li id="guide-item-<?php echo (int) $guide['id']; ?>" class="menu-item menu-item-depth-<?php echo (int) $guide['depth']; ?> menu-item-edit-inactive"
								data-id="<?php echo (int) $guide['id']; ?>"
								data-depth="<?php echo (int) $guide['depth']; ?>">
								<div class="menu-item-bar">
									<div class="menu-item-handle">
										<label class="item-title">
											<span class="menu-item-title"><?php echo esc_html( $guide['label'] ); ?></span>
											<?php if ( $guide['depth'] > 0 ) : ?>
												<span class="is-submenu">sub item</span>
											<?php endif; ?>
										</label>
										<span class="item-controls">
											<span class="item-type"><?php echo esc_html( $source_label ); ?></span>
											<a class="item-edit" href="#guide-item-settings-<?php echo (int) $guide['id']; ?>"><span class="screen-reader-text">Toggle</span></a>
										</span>
									</div>
								</div>
								<div class="menu-item-settings wp-clearfix" id="guide-item-settings-<?php echo (int) $guide['id']; ?>" style="display:none">
									<p class="description description-wide">
										<label>Label<br>
											<input type="text" class="widefat guide-inline-label" value="<?php echo esc_attr( $guide['label'] ); ?>" data-id="<?php echo (int) $guide['id']; ?>">
										</label>
									</p>
									<div class="menu-item-actions description-wide submitbox">
										<a class="item-edit-link" href="<?php echo esc_url( $edit_url ); ?>">Edit Content</a>
										<span class="meta-sep"> | </span>
										<a class="item-delete submitdelete deletion" href="#" data-id="<?php echo (int) $guide['id']; ?>">Remove</a>
									</div>
								</div>
								<ul class="menu-item-transport"></ul>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

				</div>

				<!-- Sidebar -->
				<div class="guide-builder-sidebar">

					<!-- Add System Guide -->
					<div class="postbox">
						<div class="postbox-header"><h2>Add System Guide</h2></div>
						<div class="inside">
							<select id="guide-add-system" style="width:100%;margin-bottom:8px"><option value="">— Select source —</option></select>
							<button type="button" id="guide-add-system-btn" class="button" style="width:100%">Add to Guides</button>
						</div>
					</div>

					<!-- Add Custom Guide -->
					<div class="postbox">
						<div class="postbox-header"><h2>Add Custom Guide</h2></div>
						<div class="inside">
							<input type="text" id="guide-add-custom-slug" placeholder="slug" style="width:100%;margin-bottom:6px">
							<input type="text" id="guide-add-custom-label" placeholder="Label" style="width:100%;margin-bottom:6px">
							<label style="display:block;margin-bottom:8px"><input type="checkbox" id="guide-add-custom-group"> Group only (no own content)</label>
							<button type="button" id="guide-add-custom-btn" class="button" style="width:100%">Add to Guides</button>
						</div>
					</div>

					<!-- Placeholders -->
					<div class="postbox">
						<div class="postbox-header"><h2>Placeholders</h2></div>
						<div class="inside">
							<p class="description">Available for use in guide templates.</p>
							<div id="guide-placeholder-list"></div>
						</div>
					</div>

				</div>

			</div>
		</div>
		<?php
	}

	// ── Editor Page (separate, standalone TinyMCE) ──────────────────────

	private function render_editor_page( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== Plugin::get( $this->context->prefix )->get_post_type() ) {
			echo '<div class="wrap"><h1>Guide not found.</h1></div>';
			return;
		}

		$content = $post->post_content;
		$label   = $post->post_title;
		$slug    = $post->post_name;
		$source  = get_post_meta( $post_id, '_guide_source', true ) ?: 'custom';

		$assets = $this->context->package_url . 'assets/';
		$ver    = $this->context->package_version;

		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_script(
			$this->context->asset_handle( 'editor' ),
			$assets . 'guide-editor.js',
			array( 'jquery', 'jquery-ui-tooltip' ),
			$ver,
			true
		);
		wp_localize_script( $this->context->asset_handle( 'editor' ), 'guideEditor', array(
			'postId'       => $post_id,
			'placeholders' => $this->get_placeholder_palette(),
		) );
		wp_enqueue_style(
			$this->context->asset_handle( 'builder' ),
			$assets . 'guide-builder.css',
			array(),
			$ver
		);

		// Transform {{tokens}} to visual pills.
		$editor_content = preg_replace(
			'/\{\{([a-z_]+)\}\}/',
			'<span class="mceNonEditable guide-ph" data-ph="{{$1}}" contenteditable="false">{{$1}}</span>',
			$content
		);

		$editor_css = $this->context->package_url . 'assets/guide-tinymce.css';

		?>
		<div class="wrap">
			<h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug ) ); ?>">&larr; Guide Builder</a>
				&nbsp;/&nbsp; <?php echo esc_html( $label ); ?>
			</h1>

			<form method="post">
				<?php wp_nonce_field( $this->context->nonce_action() ); ?>
				<input type="hidden" name="guide_builder_action" value="save_guide">
				<input type="hidden" name="guide_post_id" value="<?php echo (int) $post_id; ?>">

				<div class="guide-tab-meta" style="margin:15px 0">
					<label>Label: <input type="text" name="guide_label" class="regular-text" value="<?php echo esc_attr( $label ); ?>"></label>
					<label>Slug: <input type="text" name="guide_slug" value="<?php echo esc_attr( $slug ); ?>" style="width:160px"<?php echo $source !== 'custom' ? ' readonly' : ''; ?>></label>
				</div>

				<div id="guide-editor-wrap" style="display:flex;gap:20px">
					<div style="flex:1">
						<?php
						wp_editor( $editor_content, 'guide_content', array(
							'textarea_name' => 'guide_content',
							'media_buttons' => true,
							'textarea_rows' => 25,
							'tinymce'       => array(
								'content_css' => $editor_css,
							),
						) );
						?>
					</div>

					<!-- Placeholder Palette -->
					<div id="guide-placeholder-palette" style="width:280px">
						<h3>Placeholders</h3>
						<p class="description">Click to insert at cursor, or drag into the editor.</p>
						<div id="guide-placeholder-list"></div>
					</div>
				</div>

				<?php submit_button( 'Save Guide' ); ?>
			</form>
		</div>
		<?php
	}

	// ── Editor Save (form POST) ─────────────────────────────────────────

	public function handle_editor_save() {
		if ( ! isset( $_POST['guide_builder_action'] ) || $_POST['guide_builder_action'] !== 'save_guide' ) {
			return;
		}
		if ( ! $this->verify_nonce() || ! current_user_can( $this->context->capability ) ) {
			return;
		}

		$post_id   = (int) $_POST['guide_post_id'];
		$post      = get_post( $post_id );
		$old_slug  = $post ? $post->post_name : '';
		$content   = wp_kses_post( wp_unslash( $_POST['guide_content'] ) );
		$new_label = sanitize_text_field( wp_unslash( $_POST['guide_label'] ?? '' ) );
		$new_slug  = sanitize_key( $_POST['guide_slug'] ?? '' );

		// Convert pill spans back to plain {{tokens}}.
		$content = preg_replace(
			'/<span[^>]*class="[^"]*guide-ph[^"]*"[^>]*data-ph="(\{\{[a-z_]+\}\})"[^>]*>.*?<\/span>/s',
			'$1',
			$content
		);

		$update = array(
			'ID'           => $post_id,
			'post_content' => $content,
		);
		if ( $new_label ) {
			$update['post_title'] = $new_label;
		}
		if ( $new_slug ) {
			$update['post_name'] = $new_slug;
		}

		// Clean up old generated files when slug changes.
		if ( $new_slug && $old_slug && $new_slug !== $old_slug ) {
			$this->generator->remove_files( $old_slug );
		}

		wp_update_post( $update );
		$this->generator->generate();

		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->page_slug . '&edit=' . $post_id . '&updated=1' ) );
		exit;
	}

	// ── AJAX Handlers ───────────────────────────────────────────────────

	public function ajax_save_order() {
		$this->verify_ajax();

		$items = isset( $_POST['items'] ) ? (array) $_POST['items'] : array();
		$clean = array();

		foreach ( $items as $item ) {
			$clean[] = array(
				'id'        => (int) $item['id'],
				'parent_id' => (int) $item['parent_id'],
				'position'  => (int) $item['position'],
			);
		}

		$this->config->save_order( $clean );
		$this->generator->generate();

		wp_send_json_success();
	}

	public function ajax_add_guide() {
		$this->verify_ajax();

		$slug   = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$label  = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : 'custom';
		$group  = ! empty( $_POST['group'] );

		if ( ! $slug || ! $label ) {
			wp_send_json_error( 'Slug and label are required.' );
		}

		$post_id = $this->config->add_tab( $slug, $label, $source );
		if ( ! $post_id ) {
			wp_send_json_error( 'Guide already exists.' );
		}

		// Group-only tab: clear content so it falls through to first child.
		if ( $group ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => '' ) );
			update_post_meta( $post_id, '_guide_group', 1 );
		}

		$this->generator->generate();

		wp_send_json_success( array(
			'id'     => $post_id,
			'slug'   => $slug,
			'label'  => $label,
			'source' => $source,
		) );
	}

	public function ajax_remove_guide() {
		$this->verify_ajax();

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id ) {
			wp_send_json_error( 'ID is required.' );
		}

		$post = get_post( $id );
		if ( $post ) {
			// Clean up generated files for this guide and its children.
			$this->generator->remove_files( $post->post_name );
			$children = get_posts( array(
				'post_type'   => $post->post_type,
				'post_parent' => $id,
				'fields'      => 'ids',
				'numberposts' => -1,
			) );
			foreach ( $children as $child_id ) {
				$child = get_post( $child_id );
				if ( $child ) {
					$this->generator->remove_files( $child->post_name );
				}
			}

			$this->config->remove_tab( $post->post_name );
		}

		$this->generator->generate();
		wp_send_json_success();
	}

	public function ajax_check_status() {
		$this->verify_ajax();
		wp_send_json_success( $this->integrations->check_external_status() );
	}

	// ── Export / Import ─────────────────────────────────────────────────

	public function handle_export() {
		if ( ! $this->verify_nonce() || ! current_user_can( $this->context->capability ) ) {
			wp_die( 'Unauthorized.' );
		}

		$bundle   = $this->config->export();
		$filename = $this->context->prefix . '-admin-guide-export-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function handle_import() {
		if ( ! $this->verify_nonce() || ! current_user_can( $this->context->capability ) ) {
			wp_die( 'Unauthorized.' );
		}

		if ( empty( $_FILES['guide_import_file']['tmp_name'] ) ) {
			wp_die( 'No file uploaded.' );
		}

		$json = file_get_contents( $_FILES['guide_import_file']['tmp_name'] );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			wp_die( 'Invalid JSON.' );
		}

		$result = $this->config->import( $data );
		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		$this->generator->generate();

		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->page_slug . '&imported=1' ) );
		exit;
	}

	// ── Helpers ─────────────────────────────────────────────────────────

	private function verify_ajax() {
		check_ajax_referer( $this->context->nonce_action(), 'nonce' );
		if ( ! current_user_can( $this->context->capability ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
	}

	private function verify_nonce() {
		return wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', $this->context->nonce_action() );
	}

	private function resolve_source_label( $guide ) {
		$source = $guide['source'];

		if ( $source === 'custom' )    return 'Custom';
		if ( $source === 'system' )    return 'System';
		if ( $source === 'post_type' ) return 'Post Type';
		if ( $source === 'taxonomy' )  return 'Taxonomy';

		if ( $source === 'platform' ) {
			foreach ( $this->integrations->get_all() as $int_slug => $data ) {
				if ( ! empty( $data['tab_templates'] ) ) {
					foreach ( $data['tab_templates'] as $tpl ) {
						if ( $tpl['slug'] === $guide['slug'] ) {
							return $data['name'];
						}
					}
				}
				if ( $int_slug === $guide['slug'] ) {
					return $data['name'];
				}
			}
			return 'Platform';
		}

		return ucwords( str_replace( '_', ' ', $source ) );
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
