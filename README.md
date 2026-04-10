# Admin Guide

JSON-driven admin guide builder for WordPress. Lets you build an in-dashboard user manual from reusable tabs and live placeholders, with a TinyMCE editor, JSON import/export, and per-instance prefix isolation so multiple hosts can bundle it side-by-side.

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

Plugin::boot( 'my_prefix', [
    'package_path'    => __DIR__ . '/vendor/binary-wp/admin-guide/',
    'package_url'     => plugin_dir_url( __FILE__ ) . 'vendor/binary-wp/admin-guide/',
    'package_version' => '0.1.0',
    'guide_dir'       => __DIR__ . '/guide/',
    'menu'            => [
        'parent'        => '',              // empty = top-level menu
        'builder_label' => 'Admin Guide',
    ],
] );
```

Your instance prefix (`'my_prefix'`) scopes all `wp_option` keys, AJAX actions, nonces, asset handles, and admin page slugs so multiple instances can coexist.

## Per-host integrations

Ship your own JSON integration definitions alongside the package by passing extra directories at boot:

```php
Plugin::boot( 'my_prefix', [
    'integrations_dirs' => [ __DIR__ . '/guide-integrations/' ],
    // …
] );
```

Each extra directory is scanned for `*.json` integration files, with optional `functions/` and `templates/` subdirectories alongside.

## Exposed API

```php
$plugin = \BinaryWP\AdminGuide\Plugin::get( 'my_prefix' );

$plugin->config->get_tabs();                // [ slug => label ]
$plugin->config->get_template( $slug );     // raw HTML
$plugin->config->save_template( $slug, $html );
$plugin->config->export();                  // bundle array
$plugin->config->import( $bundle );         // true|WP_Error

$plugin->generator->generate();             // regenerate guide/ output
$plugin->generator->read_tab( $slug );      // rendered HTML
```

## Hooks

Filters (each also fires a prefix-scoped variant, e.g. `my_prefix/guide_builder/parent_menu`):

- `guide_builder/parent_menu` — parent menu slug for the builder page
- `guide_builder/guide_dir` — output directory for generated files
- `guide_builder/legacy_guide_dir` — legacy templates dir for one-shot migration
- `guide_builder/system_tabs` — available system tab groups in the builder UI

Actions:

- `guide_builder/placeholders` — register custom placeholders
- `guide_builder/integrations` — register additional integrations at runtime

## License

MIT
