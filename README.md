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

## Translations

The package ships with a translation template (`languages/binary-wp-admin-guide.pot`) and a Czech translation (`cs_CZ`) out of the box. The text domain is `binary-wp-admin-guide` and is loaded automatically on `init` priority 1.

To contribute a translation:

1. Copy `languages/binary-wp-admin-guide.pot` to `languages/binary-wp-admin-guide-{locale}.po` (e.g. `fr_FR`, `de_DE`).
2. Translate each `msgstr` in the `.po` file (use [Poedit](https://poedit.net/) or any PO editor).
3. Compile to `.mo`:
   ```bash
   msgfmt --check --output-file=languages/binary-wp-admin-guide-{locale}.mo \
          languages/binary-wp-admin-guide-{locale}.po
   ```
4. Submit a pull request against `main` with both `.po` and `.mo` files.

### Regenerating the `.pot` template

After adding or modifying translatable strings in the source, regenerate the template:

```bash
xgettext \
  --language=PHP --from-code=UTF-8 \
  --keyword=__:1 --keyword=_e:1 \
  --keyword=esc_html__:1 --keyword=esc_html_e:1 \
  --keyword=esc_attr__:1 --keyword=esc_attr_e:1 \
  --keyword=_x:1,2c --keyword=esc_html_x:1,2c \
  --keyword=_n:1,2 --keyword=_nx:1,2,4c \
  --copyright-holder='BinaryWP' \
  --package-name='Admin Guide' --package-version='0.2.0' \
  --msgid-bugs-address='https://github.com/binary-wp/admin-guide/issues' \
  --add-comments=translators: \
  --output=languages/binary-wp-admin-guide.pot \
  src/*.php integrations/functions/*.php admin-guide.php
```

Then merge the new template into existing `.po` files:

```bash
msgmerge --update languages/binary-wp-admin-guide-cs_CZ.po languages/binary-wp-admin-guide.pot
```

## License

MIT
