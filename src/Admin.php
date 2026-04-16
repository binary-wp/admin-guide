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
		$menu   = $this->context->menu_defaults;
		$parent = ! empty( $menu['parent'] ) ? $menu['parent'] : 'tools.php';
		$label  = isset( $menu['builder_label'] ) ? (string) $menu['builder_label'] : __( 'Guide Builder', 'binary-wp-admin-guide' );

		add_submenu_page(
			$parent,
			$label,
			$label,
			$this->context->capability,
			$this->page_slug,
			array( $this, 'render_page' )
		);

		add_submenu_page(
			$parent,
			'Guide Instructions',
			'&nbsp;&nbsp;Instructions',
			$this->context->capability,
			$this->context->page_slug( 'instructions' ),
			array( $this, 'render_instructions_page' )
		);

		add_submenu_page(
			$parent,
			'Guide Settings & Tools',
			'&nbsp;&nbsp;Settings & Tools',
			$this->context->capability,
			$this->context->page_slug( 'settings' ),
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Get the internal tab nav for builder sub-pages.
	 */
	private function get_builder_tabs() {
		return array(
			$this->page_slug                            => 'Builder',
			$this->context->page_slug( 'instructions' ) => 'Instructions',
			$this->context->page_slug( 'settings' )     => 'Settings & Tools',
		);
	}

	/**
	 * Render the in-page tab navigation below the page title.
	 */
	private function render_builder_nav( $current_slug ) {
		$tabs = $this->get_builder_tabs();
		echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
		foreach ( $tabs as $slug => $label ) {
			$class = ( $slug === $current_slug ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = admin_url( 'admin.php?page=' . $slug );
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}
		echo '</nav>';
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

		?>
		<div class="wrap nav-menus-php">
			<h1>Guide Builder</h1>
			<?php $this->render_builder_nav( $this->page_slug ); ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Guide updated and regenerated.</p></div>
			<?php endif; ?>

			<div class="guide-builder-layout">

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
												<a class="guide-item-delete submitdelete deletion" href="#" data-id="<?php echo (int) $guide['id']; ?>">Remove</a>
											</div>
										</div>
										<ul class="menu-item-transport"></ul>
									</li>
								<?php endforeach; ?>
							</ul>
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

				<div class="guide-builder-layout">

					<!-- Sidebar: Placeholder Palette -->
					<div class="guide-builder-sidebar">
						<div class="postbox">
							<div class="postbox-header"><h2>Placeholders</h2></div>
							<div class="inside">
								<p class="description">Click to insert at cursor, or drag into the editor.</p>
								<div id="guide-placeholder-list"></div>
							</div>
						</div>
					</div>

					<!-- Main: Editor -->
					<div class="guide-builder-main">
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

		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->context->page_slug( 'settings' ) . '&imported=1' ) );
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

	// ── Instructions Page ──────────────────────────────────────────────

	public function render_instructions_page() {
		$assets = $this->context->package_url . 'assets/';
		$ver    = $this->context->package_version;

		wp_enqueue_style(
			$this->context->asset_handle( 'builder' ),
			$assets . 'guide-builder.css',
			array(),
			$ver
		);

		$integrations = $this->integrations->get_all();

		?>
		<div class="wrap">
			<h1>Guide Builder</h1>
			<?php $this->render_builder_nav( $this->context->page_slug( 'instructions' ) ); ?>

			<!-- Usage Guide -->
			<div class="guide-instructions-section" style="max-width:900px">

				<h2>How the Guide Builder Works</h2>

				<div class="card" style="max-width:none;margin-bottom:20px">
					<h3>Templates &amp; Placeholders</h3>
					<p>Each guide page stores an HTML template in the database. Templates can contain <strong>placeholders</strong> — special tokens like <code>{{token_name}}</code> — that are resolved to live data at generation time.</p>
					<p>When a guide is saved (via the editor or the builder), all templates are processed:</p>
					<ol>
						<li>Placeholder tokens are replaced with output from PHP callbacks (database queries, option lookups, plugin detection)</li>
						<li>The resolved HTML is written to <code>guide/html/*.html</code> for fast admin rendering</li>
						<li>A Markdown copy is written to <code>guide/*.md</code> for portable reading</li>
					</ol>
					<p>This means guide content is always up-to-date — it reflects the current state of the site every time it's regenerated.</p>
				</div>

				<div class="card" style="max-width:none;margin-bottom:20px">
					<h3>Guide Structure</h3>
					<p>Guides are organized as a <strong>two-level hierarchy</strong>:</p>
					<ul>
						<li><strong>Top-level tabs</strong> appear as horizontal navigation on the guide page</li>
						<li><strong>Child tabs</strong> appear as sub-navigation within their parent tab</li>
						<li><strong>Group-only tabs</strong> are organizational parents with no content of their own — they automatically show their first child</li>
					</ul>
					<p>Drag items in the builder to reorder. Drag right to nest (max 1 level deep).</p>
				</div>

				<div class="card" style="max-width:none;margin-bottom:20px">
					<h3>System vs Custom Guides</h3>
					<ul>
						<li><strong>System guides</strong> are provided by integrations (plugins, themes). Their slugs are fixed and their templates may include pre-defined placeholders. Add them from the "Add System Guide" panel in the builder.</li>
						<li><strong>Custom guides</strong> are free-form pages you create. You choose the slug, label, and write any content (including placeholders from any active integration).</li>
					</ul>
				</div>

				<div class="card" style="max-width:none;margin-bottom:20px">
					<h3>Using Placeholders in the Editor</h3>
					<p>On the template editor page, the Placeholders sidebar lists all available tokens grouped by integration.</p>
					<ul>
						<li><strong>Click</strong> a placeholder to insert it at the cursor position</li>
						<li><strong>Drag</strong> a placeholder into the editor</li>
					</ul>
					<p>In the editor, placeholders appear as colored pills (non-editable). In the database, they are stored as plain <code>{{token}}</code> text.</p>
				</div>

			</div>

			<!-- Integrations -->
			<div class="guide-instructions-section" style="max-width:900px">

				<h2>Integrations</h2>
				<p class="description" style="margin-bottom:15px">
					Integrations connect the guide builder to plugins, themes, and WordPress core.
					An integration is <strong style="color:#00a32a">active</strong> when its requirements are met (plugin/theme installed and activated),
					or <strong style="color:#d63638">inactive</strong> when they are not.
				</p>

				<?php foreach ( $integrations as $slug => $data ) :
					$is_default = ( empty( $data['name'] ) || $data['name'] === 'Default' );
					$display_name = $is_default ? 'WordPress Core' : $data['name'];
					$active = $is_default ? true : $this->integrations->is_active( $slug );
					$icon_class = $is_default ? 'dashicons-wordpress' : ( $active ? 'dashicons-yes-alt' : 'dashicons-marker' );
					$icon_color = $is_default ? '#3858e9' : ( $active ? '#00a32a' : '#d63638' );

					$has_placeholders  = ! empty( $data['placeholders'] );
					$has_tab_templates = ! empty( $data['tab_templates'] );
					$has_system_tabs   = ! empty( $data['system_tabs'] );
					$has_external      = ! empty( $data['external'] );
					$has_settings      = ! empty( $data['settings_url'] );
					$has_docs          = ! empty( $data['docs_url'] );
				?>
					<div class="card" style="max-width:none;margin-bottom:15px;<?php echo ( ! $active && ! $is_default ) ? 'opacity:0.7' : ''; ?>">
						<h3 style="display:flex;align-items:center;gap:8px;margin-top:0">
							<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" style="color:<?php echo esc_attr( $icon_color ); ?>"></span>
							<?php echo esc_html( $display_name ); ?>
							<?php if ( ! $is_default ) : ?>
								<span style="font-size:12px;font-weight:normal;color:<?php echo $active ? '#00a32a' : '#d63638'; ?>"><?php echo $active ? 'Active' : 'Inactive'; ?></span>
							<?php endif; ?>
						</h3>

						<?php if ( $has_settings || $has_docs ) : ?>
							<p style="margin:4px 0 10px">
								<?php if ( $has_settings ) : ?>
									<a href="<?php echo esc_url( admin_url( $data['settings_url'] ) ); ?>">Settings</a>
								<?php endif; ?>
								<?php if ( $has_settings && $has_docs ) echo ' · '; ?>
								<?php if ( $has_docs ) : ?>
									<a href="<?php echo esc_url( $data['docs_url'] ); ?>" target="_blank">Documentation ↗</a>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<?php if ( $has_tab_templates || $has_system_tabs ) : ?>
							<h4 style="margin:12px 0 6px;font-size:13px">System guide pages</h4>
							<ul style="margin:0 0 8px 18px">
								<?php if ( $has_system_tabs ) :
									foreach ( $data['system_tabs'] as $st ) : ?>
										<li><?php echo esc_html( $st['label'] ); ?> <code style="font-size:11px"><?php echo esc_html( $st['slug'] ); ?></code></li>
									<?php endforeach;
								endif; ?>
								<?php if ( $has_tab_templates ) :
									foreach ( $data['tab_templates'] as $tpl ) : ?>
										<li><?php echo esc_html( $tpl['label'] ); ?> <code style="font-size:11px"><?php echo esc_html( $tpl['slug'] ); ?></code></li>
									<?php endforeach;
								endif; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $has_external ) : ?>
							<h4 style="margin:12px 0 6px;font-size:13px">External service checks</h4>
							<ul style="margin:0 0 8px 18px">
								<?php foreach ( $data['external'] as $ext ) : ?>
									<li><?php echo esc_html( $ext['service'] ?? $ext['name'] ?? '' ); ?> — <em><?php echo esc_html( $ext['description'] ?? '' ); ?></em></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $has_placeholders ) : ?>
							<h4 style="margin:12px 0 6px;font-size:13px">Placeholders</h4>
							<table class="widefat fixed striped" style="margin-top:0">
								<thead>
									<tr>
										<th style="width:35%">Token</th>
										<th>Description</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $data['placeholders'] as $token => $ph_data ) : ?>
										<tr>
											<td><code style="font-size:11px"><?php echo esc_html( $token ); ?></code></td>
											<td><?php echo esc_html( $ph_data['description'] ?? '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>

						<?php if ( ! $has_placeholders && ! $has_tab_templates && ! $has_system_tabs && ! $has_external ) : ?>
							<p class="description" style="margin:4px 0 0"><em>Links only — no guide content defined yet.</em></p>
						<?php endif; ?>

					</div>
				<?php endforeach; ?>

				<!-- Host-registered placeholders (not from any integration JSON) -->
				<?php
				$host_placeholders = $this->get_host_only_placeholders( $integrations );
				if ( $host_placeholders ) :
				?>
					<div class="card" style="max-width:none;margin-bottom:15px">
						<h3 style="display:flex;align-items:center;gap:8px;margin-top:0">
							<span class="dashicons dashicons-admin-plugins" style="color:#826eb4"></span>
							Host-registered
						</h3>
						<p class="description" style="margin:4px 0 10px">Placeholders registered by the host plugin/theme outside of integration JSONs.</p>
						<table class="widefat fixed striped">
							<thead>
								<tr>
									<th style="width:35%">Token</th>
									<th>Description</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $host_placeholders as $token => $desc ) : ?>
									<tr>
										<td><code style="font-size:11px"><?php echo esc_html( $token ); ?></code></td>
										<td><?php echo esc_html( $desc ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

			</div>
		</div>
		<?php
	}

	/**
	 * Get placeholders registered by the host but not defined in any integration JSON.
	 */
	private function get_host_only_placeholders( $integrations ) {
		// Collect all tokens defined in integration JSONs (keyed with {{ }}).
		$integration_tokens = array();
		foreach ( $integrations as $slug => $data ) {
			foreach ( array_keys( $data['placeholders'] ?? array() ) as $token ) {
				$integration_tokens[ $token ] = true;
			}
			// Also mark auto-generated tokens (settings_link, docs_link, external_status).
			$prefix = ! empty( $data['prefix'] ) ? $data['prefix'] : str_replace( '-', '_', $slug );
			if ( ! empty( $data['settings_url'] ) ) {
				$integration_tokens[ '{{' . $prefix . '_settings_link}}' ] = true;
			}
			if ( ! empty( $data['docs_url'] ) ) {
				$integration_tokens[ '{{' . $prefix . '_docs_link}}' ] = true;
			}
			if ( ! empty( $data['external'] ) ) {
				$integration_tokens[ '{{' . $prefix . '_external_status}}' ] = true;
			}
		}

		// Compare with all registered placeholders.
		// Tokens in get_all() already include {{ }} braces.
		$host = array();
		foreach ( $this->placeholders->get_all() as $token => $data ) {
			if ( ! isset( $integration_tokens[ $token ] ) ) {
				$host[ $token ] = $data['description'] ?? '';
			}
		}

		return $host;
	}

	// ── Settings & Tools Page ──────────────────────────────────────────

	public function render_settings_page() {
		$assets = $this->context->package_url . 'assets/';
		$ver    = $this->context->package_version;

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
		<div class="wrap">
			<h1>Guide Builder</h1>
			<?php $this->render_builder_nav( $this->context->page_slug( 'settings' ) ); ?>

			<?php if ( isset( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Guide imported successfully.</p></div>
			<?php endif; ?>

			<div style="max-width:900px">

				<!-- Import / Export -->
				<div class="card" style="max-width:none;margin-bottom:20px">
					<h3 style="margin-top:0">Import / Export</h3>
					<p>Export the full guide structure (tabs, hierarchy, templates) as a JSON file, or import one to replace the current guide.</p>

					<div style="display:flex;gap:15px;align-items:flex-start;margin-top:15px">
						<div>
							<h4 style="margin:0 0 8px">Export</h4>
							<p class="description" style="margin-bottom:8px">Download the current guide as a JSON file.</p>
							<a href="<?php echo esc_url( $export_url ); ?>" class="button">Export JSON</a>
						</div>

						<div style="border-left:1px solid #dcdcde;padding-left:15px">
							<h4 style="margin:0 0 8px">Import</h4>
							<p class="description" style="margin-bottom:8px">Upload a JSON file to replace the current guide structure.</p>
							<form method="post" enctype="multipart/form-data"
								action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( $this->context->nonce_action() ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( $this->context->action_name( 'import' ) ); ?>">
								<input type="file" name="guide_import_file" accept=".json" style="margin-bottom:8px"><br>
								<button type="submit" class="button">Import JSON</button>
							</form>
						</div>
					</div>
				</div>

				<!-- Info -->
				<div class="card" style="max-width:none;margin-bottom:20px">
					<h3 style="margin-top:0">Guide Info</h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Output directory</th>
							<td><code><?php echo esc_html( $this->generator->get_output_dir() ); ?></code></td>
						</tr>
						<tr>
							<th scope="row">Guide pages</th>
							<td><?php echo count( $this->config->get_tab_entries() ); ?> pages (<?php echo count( $this->config->get_tabs() ); ?> top-level tabs)</td>
						</tr>
						<tr>
							<th scope="row">Active integrations</th>
							<td><?php echo count( $this->integrations->get_active() ); ?> of <?php echo count( $this->integrations->get_all() ); ?></td>
						</tr>
						<tr>
							<th scope="row">Placeholders</th>
							<td><?php echo count( $this->placeholders->get_all() ); ?> registered</td>
						</tr>
						<tr>
							<th scope="row">Instance prefix</th>
							<td><code><?php echo esc_html( $this->context->prefix ); ?></code></td>
						</tr>
						<tr>
							<th scope="row">Package version</th>
							<td><?php echo esc_html( $this->context->package_version ); ?></td>
						</tr>
					</table>
				</div>

			</div>
		</div>
		<?php
	}
}
