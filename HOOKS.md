# Hooks

Every hook fires twice: once under a generic name that all instances share, then under a
name scoped to one booted instance.

```
admin_guide_builder/{name}            every instance
{prefix}/admin_guide_builder/{name}   only the instance booted as Plugin::boot( '{prefix}' )
```

The standalone plugin boots with the prefix `admin_guide`.

Hook in at file load or on `plugins_loaded` before priority 5. Several hooks fire while an
instance boots (`placeholders`, `integrations`, `capability`, `guide_dir`, `booted`), so a
callback added later misses them.

## Actions

| Hook | Args | When |
|---|---|---|
| `placeholders` | `Placeholders $ph, Context $ctx` | Instance boot. Register `{{tokens}}` with `$ph->register( $token, $callback, $group, $description )`. |
| `integrations` | `Integrations $int` | After JSON integrations load. Add more with `$int->register( $slug, $args )`. |
| `booted` | `Plugin $plugin, Context $ctx` | Instance wired and registered; `Plugin::get( $prefix )` works. Start extensions here. |
| `content_changed` | `string $what, array $data, Context $ctx` | A tab was `added`, `removed`, `renamed`, its `template` edited, `order` changed, or a bundle `imported`. Fires from the storage layer, so seeders and imports trigger it too. Snapshots are not regenerated for you. |
| `before_generate` | `array $templates, Context $ctx` | Before snapshots are written. `$templates` is slug → raw HTML. |
| `generated` | `string[] $slugs, string $dir, Context $ctx` | After snapshots are written. |
| `enqueue_assets` | `string $screen, Context $ctx` | A guide screen is rendering. `$screen` is `viewer`, `builder`, `editor`, `instructions`, `settings`, or an extension screen slug. |
| `settings_sections` | `Context $ctx` | End of Settings & Tools. Print your own cards; save through your own `admin_post_` handler. |

### Triggering a rebuild

The Viewer shows snapshots, and placeholders resolve when snapshots are written, not when a
page is viewed. If your placeholders read data that changes, rebuild when it changes:

```php
do_action( 'admin_guide_builder/regenerate' );             // every instance
do_action( 'admin_guide_builder/regenerate', 'myplugin' ); // one instance
// or: Plugin::get( 'myplugin' )->regenerate();
```

## Filters

| Hook | Value | Extra args | Use |
|---|---|---|---|
| `capability` | `string` | `Context` | Capability for every guide screen and handler. Default `manage_options`, or the `capability` boot arg. |
| `guide_dir` | `string` | `Context` | Where snapshots are written. |
| `system_tabs` | `array` | `string[] $existing` | Options in the builder's “Add System Guide” box. |
| `tab_template` | `string $html` | `string $slug, Context` | A tab's raw template, before placeholders resolve. Source a tab from a file or a remote doc here; the result still gets `{{tokens}}` resolved and is written as a snapshot. |
| `tab_content` | `string $html` | `string $slug, Context` | A tab's HTML right before the Viewer prints it, on every page view. Use it for per-request or per-user output. Never put per-user logic in a placeholder; that output is frozen into one shared snapshot. |
| `viewer_actions` | `array` | `Context` | Buttons beside the Viewer title, keyed by id: `[ 'label' => …, 'url' => …, 'capability' => … ]`. The built-in one is `builder`. |
| `screens` | `array` | `Context` | Admin screens contributed by extensions; see below. |
| `content_type_registrars` | `array` | `Context` | “Registered by” labels in `{{wp_content_types_table}}`: post-type slug or slug prefix → label, e.g. `'newsletter' => 'My Plugin'`. |

### Adding a screen

```php
add_filter( 'admin_guide_builder/screens', function ( $screens, $context ) {
	$screens['compare'] = array(
		'title'      => 'Site Compare',
		'callback'   => 'my_render_compare', // receives the Context
		'nav'        => 'hidden',            // 'menu' | 'builder' | 'hidden'
		'capability' => 'manage_options',    // optional, defaults to the instance capability
	);
	return $screens;
}, 10, 2 );

add_filter( 'admin_guide_builder/viewer_actions', function ( $actions, $context ) {
	$actions['compare'] = array(
		'label' => 'Site Compare',
		'url'   => admin_url( 'admin.php?page=' . $context->page_slug( 'compare' ) ),
	);
	return $actions;
}, 10, 2 );
```

The screen is registered under the instance's menu parent at
`admin.php?page={prefix}-admin-guide-{slug}`.

- `nav => 'menu'` gives the screen its own sidebar item.
- `nav => 'builder'` adds it as a tab beside Builder / Instructions / Settings & Tools. To
  print that nav on your screen, call
  `Plugin::get( $context->prefix )->admin->render_builder_nav( $context->page_slug( $slug ) )`.
- `nav => 'hidden'` keeps it routable only; link to it from a `viewer_actions` button.

The slugs `builder`, `instructions`, `settings` and `viewer` are reserved.

## Renamed in 0.13.0

These hooks used to be called `guide_builder/{name}` and `{prefix}/guide_builder/{name}`. The
old names still fire, through `do_action_deprecated()` / `apply_filters_deprecated()`, so they
log a notice when `WP_DEBUG` is on. Move to the new names:

| Old | New |
|---|---|
| `guide_builder/placeholders` | `admin_guide_builder/placeholders` |
| `guide_builder/integrations` | `admin_guide_builder/integrations` |
| `guide_builder/system_tabs` | `admin_guide_builder/system_tabs` |
| `guide_builder/guide_dir` | `admin_guide_builder/guide_dir` |
