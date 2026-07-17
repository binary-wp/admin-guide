# Admin Guide

Admin guide builder for WordPress with hierarchical CPT storage, drag & drop nav-menu style builder, WYSIWYG editor with placeholder pills, JSON-driven integration registry, and per-instance prefix isolation so multiple hosts can bundle it side-by-side.

Works **three ways**:

1. **Standalone plugin** — drop into `wp-content/plugins/admin-guide/`, activate, done.
2. **Composer dependency** in another plugin — `composer require binary-wp/admin-guide`, then `Plugin::boot()` from your plugin's main file.
3. **Composer dependency** in a theme — same as above from `functions.php`.

## Requirements

- PHP 7.4+
- WordPress 5.8+

## Installation

### As a standalone plugin

```bash
cd wp-content/plugins
git clone https://github.com/wasilosos/admin-guide.git
cd admin-guide
composer install
```

Then activate "Admin Guide" in `Plugins`.

### As a Composer dependency

```bash
composer require binary-wp/admin-guide
```

In your plugin/theme main file, boot an instance **before** `admin_menu`:

```php
use BinaryWP\AdminGuide\Plugin;

// Minimal — package_path/url auto-detected from vendor location:
Plugin::boot( 'my_prefix' );
```

All options are optional. Full example with overrides:

```php
Plugin::boot( 'my_prefix', [
    'package_path'    => __DIR__ . '/vendor/binary-wp/admin-guide/',
    'package_url'     => plugin_dir_url( __FILE__ ) . 'vendor/binary-wp/admin-guide/',
    'package_version' => '0.11.0',
    'guide_dir'       => __DIR__ . '/guide/',
    'capability'      => 'manage_options',
    'menu'            => [
        'parent'        => 'tools.php',     // or your own top-level slug, or '' for standalone
        'viewer_label'  => 'Admin Guide',
        'builder_label' => 'Guide Builder',
    ],
    'integrations_dirs' => [ __DIR__ . '/my-integrations/' ],
] );
```

Auto-detection: `package_path` defaults to the package's own directory. `package_url` is resolved via `plugins_url()` or theme URI. `guide_dir` falls back to the nearest plugin/theme root + `/guide/`.

Your instance prefix (`'my_prefix'`) scopes all CPT slugs, AJAX actions, nonces, asset handles, and admin page slugs so multiple instances can coexist.

## How it works

### Storage

Guide pages are stored as a **hierarchical custom post type** (`{prefix}_guide`):

| Field | Usage |
|---|---|
| `post_title` | Tab label |
| `post_name` | Tab slug |
| `post_content` | HTML template with `{{placeholders}}` |
| `post_parent` | Hierarchy (0 = top-level, >0 = child) |
| `menu_order` | Sort position |
| `_guide_source` meta | `system` / `post_type` / `taxonomy` / `platform` / `custom` |
| `_guide_group` meta | `1` = group-only tab (no own content, shows first child) |

Benefits: revisions, REST API, standard WP queries, native WP export.

### Builder page

Nav-menu style drag & drop sortable with depth management (max 1 level deep). Matches WP core menu editor look and feel. Sidebar with metaboxes for adding system guides (from integrations) and custom guides.

### Editor page

Standalone TinyMCE with placeholder palette sidebar. Click to insert or drag & drop placeholders into the editor. Placeholders render as non-editable pills in the editor and are stored as `{{tokens}}` in the database.

### Output generation

On every save, the generator:

1. Queries all published `{prefix}_guide` posts
2. Resolves `{{placeholders}}` via PHP callbacks
3. Writes `.html` snapshots to `guide/html/` (for fast admin rendering)
4. Writes `.md` copies to `guide/` (portable reading, via `league/html-to-markdown`)

### Display

The host renders the guide viewer with:

- Top-level tabs as horizontal `nav-tab-wrapper`
- Children as inline sub-navigation (WooCommerce-style `subsubsub`)
- Group-only tabs auto-fall through to first child content

The viewer ships its own paint (`assets/guide-viewer.css`, enqueued automatically),
so guide content needs no styling from the host:

- **Tables** get the wp-admin list-table look — no class needed on the `<table>`.
- **Figures** get a bordered image and a muted caption. No width or float is
  imposed, so a figure that carries its own sizing keeps it.
- **`binary-wp-admin-guide-figure-inset`** — add this class to a `<figure>` to
  float it right with the copy wrapping around it. Suits screenshots; leave it
  off anything that needs its natural width.

```html
<figure class="binary-wp-admin-guide-figure-inset">
    <img src="..." alt="...">
    <figcaption>Where to find the Transform menu.</figcaption>
</figure>
```

## Per-host integrations

Ship your own JSON integration definitions alongside the package:

```php
Plugin::boot( 'my_prefix', [
    'integrations_dirs' => [ __DIR__ . '/guide-integrations/' ],
    // ...
] );
```

Each directory is scanned for `*.json` integration files. Each JSON defines:

- `name`, `slug`, `requires` (class/function/option checks for auto-detection)
- `placeholders[]` with token, description, type, optional callback
- `tab_templates[]` for auto-creating system tabs
- `external_status` for live service status checks

Render functions live in `functions/{slug}.php` alongside the JSON files.

### Example: custom integration JSON

Create `guide-integrations/my-crm.json`:

```json
{
    "slug": "my-crm",
    "name": "My CRM",
    "prefix": "my_crm",
    "requires": { "plugin": "my-crm/my-crm.php" },
    "settings_url": "admin.php?page=my-crm-settings",
    "docs_url": "https://example.com/docs/",
    "external": [
        {
            "service": "API Connection",
            "description": "CRM sync status",
            "check": "my_crm_api"
        }
    ],
    "tab_templates": [
        { "slug": "my-crm-overview", "label": "My CRM Overview" }
    ],
    "placeholders": {
        "{{my_crm_sync_status}}": {
            "callback": "guide_render_my_crm_sync_status",
            "description": "Current CRM sync status"
        },
        "{{my_crm_settings_link}}": {
            "type": "settings_link",
            "url": "admin.php?page=my-crm-settings",
            "label": "CRM Settings",
            "description": "Link to CRM settings page"
        }
    }
}
```

Then create `guide-integrations/functions/my-crm.php` with the render callback:

```php
function guide_render_my_crm_sync_status() {
    $last_sync = get_option( 'my_crm_last_sync' );
    return $last_sync
        ? sprintf( 'Last sync: %s', human_time_diff( $last_sync ) . ' ago' )
        : 'Not synced yet';
}
```

The integration auto-detects when `my-crm/my-crm.php` is active — its placeholders and tab templates appear in the builder automatically.

## Site Compare (optional)

An opt-in tool page that renders a **source-vs-target visual comparison gallery**: every URL scraped from the source site's sitemap is captured full-height on both origins by a host-provided node capture tool and shown side by side. The package contributes the admin surface only — settings form, capture toolbar (regenerate / stop / status), same-origin gallery iframe, and a collapsible URL inventory table read from the gallery's `manifest.json`.

Completely inert unless you pass a `compare` array to `Plugin::boot()`:

```php
Plugin::boot( 'my_prefix', [
    'compare' => [
        // URL defaults — editable on the page itself; saved values (stored in
        // the {prefix}_admin_guide_compare option) win over these:
        'source_base'    => 'https://www.example.com',                    // production origin
        'source_sitemap' => 'https://www.example.com/sitemap_index.xml',  // URL scraper input
        'target_base'    => 'https://example.local',                      // default: home_url()

        // Capture tool wiring (host-provided, node-based):
        'wrapper'    => '/path/to/bin/visual-compare.sh',  // capture shell wrapper
        'tool_dir'   => '/path/to/bin/visual-compare',     // default: wrapper minus '.sh'
        'node_bin'   => '/usr/local/bin/node',             // default shown; falls back to `command -v node`
        'output_rel' => 'migrate/compare',                 // gallery dir under uploads (default shown)

        // Page:
        'menu_label' => 'Site Compare',                    // default shown
        'capability' => 'manage_options',                  // default: package capability
    ],
] );
```

The page registers as `?page={prefix}-admin-guide-compare` under the same `menu.parent` as the rest of the package.

How the pieces fit:

- **Settings** (three URL fields) are stored in the `{prefix}_admin_guide_compare` option; non-empty saved values override the boot defaults. Regenerate passes them to the wrapper as `--sitemap` / `--prod-base` / `--local-base` (plus `--out` for the uploads gallery dir), so the config actually drives capture.
- **Regenerate** runs detached (`nohup`) with a PID lockfile in the gallery dir; the status poll checks the PID is a *live* process, so a crashed run recovers instantly instead of showing a phantom "capturing…". Modes: full sitemap, demo (`--demo`), per-sitemap-type (`--types <slug>`), plus a "first N" `--limit` and an incremental flag.
- **The gallery** (`index.html` + `manifest.json` + screenshots) lives under `wp-content/uploads/{output_rel}/`, i.e. it's served by WordPress on the *same origin* as wp-admin. The iframe loads it with `?wp=1&ajax=<admin-ajax>&nonce=…&action={prefix}_admin_guide_compare_refresh` so the gallery's own per-page refresh buttons can call `admin-ajax.php` directly — no CORS, no control server.
- **Graceful degradation**: if `shell_exec` is disabled or the wrapper / tool dir / node binary is missing, the settings form, read-only gallery, and URL inventory still work — only the capture toolbar is disabled, with a notice naming what's missing.

Requirements for the interactive toolbar: `shell_exec` enabled, node, and a capture tool matching the wrapper contract (a `capture.mjs`-style tool with a `refresh-cli.mjs` sibling — see the Wolfe's Neck `bin/visual-compare/` reference implementation). This is a dev/migration aid; don't ship it enabled on production.

## Exposed API

```php
$plugin = \BinaryWP\AdminGuide\Plugin::get( 'my_prefix' );

// Tabs
$plugin->config->get_tabs();                   // [ slug => label ] (top-level)
$plugin->config->get_children( $parent_slug ); // [ slug => label ] (children)
$plugin->config->get_all_guides();             // full tree with depth
$plugin->config->get_template( $slug );        // raw HTML content
$plugin->config->add_tab( $slug, $label, $source );
$plugin->config->remove_tab( $slug );
$plugin->config->save_order( $items );         // [{ id, parent_id, position }]

// Import / Export
$plugin->config->export();                     // JSON-serializable array
$plugin->config->import( $bundle );            // true | WP_Error

// Generator
$plugin->generator->generate();                // regenerate all output files
$plugin->generator->read_tab( $slug );         // rendered HTML for display
$plugin->generator->remove_files( $slug );     // clean up generated files for a slug
```

## Hooks

Filters (each also fires a prefix-scoped variant, e.g. `my_prefix/guide_builder/guide_dir`):

- `guide_builder/guide_dir` — output directory for generated files
- `guide_builder/system_tabs` — available system tab groups in the builder UI

Actions:

- `guide_builder/placeholders` — register custom placeholders
- `guide_builder/integrations` — register additional integrations at runtime

## Translations

The package ships with a translation template (`languages/binary-wp-admin-guide.pot`) and a Czech translation (`cs_CZ`). Text domain: `binary-wp-admin-guide`, loaded on `init` priority 1.

To contribute a translation:

1. Copy `languages/binary-wp-admin-guide.pot` to `languages/binary-wp-admin-guide-{locale}.po`
2. Translate each `msgstr` (use [Poedit](https://poedit.net/) or any PO editor)
3. Compile: `msgfmt --check --output-file=languages/binary-wp-admin-guide-{locale}.mo languages/binary-wp-admin-guide-{locale}.po`
4. Submit a PR with both `.po` and `.mo` files

### Regenerating the `.pot` template

```bash
xgettext \
  --language=PHP --from-code=UTF-8 \
  --keyword=__:1 --keyword=_e:1 \
  --keyword=esc_html__:1 --keyword=esc_html_e:1 \
  --keyword=esc_attr__:1 --keyword=esc_attr_e:1 \
  --keyword=_x:1,2c --keyword=esc_html_x:1,2c \
  --keyword=_n:1,2 --keyword=_nx:1,2,4c \
  --copyright-holder='BinaryWP' \
  --package-name='Admin Guide' --package-version='0.11.0' \
  --msgid-bugs-address='https://github.com/binary-wp/admin-guide/issues' \
  --add-comments=translators: \
  --output=languages/binary-wp-admin-guide.pot \
  src/*.php integrations/functions/*.php admin-guide.php
```

## License

MIT
