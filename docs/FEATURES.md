# Admin Guide — Feature List

> Current version: **0.6.0**

## Core Features

| Feature | Description |
|---|---|
| CPT-based storage | Each guide tab is a `{prefix}_guide_page` post — hierarchical, with revisions and REST API support |
| Drag & drop builder | Nav-menu style sortable UI with parent/child depth (max 1 level) |
| WYSIWYG editor | TinyMCE instance with a dedicated placeholder palette sidebar |
| Placeholder system | `{{token}}` syntax with typed registry — `callback`, `settings_link`, `image` — rendered as non-editable pills in the editor |
| JSON-driven integrations | Auto-detect active plugins/themes via `requires` resolver supporting `class`, `function`, `plugin`, `theme`, `post_type`, `taxonomy`, `constant`, `option` with AND/OR logic |
| External status checks | Live AJAX health checks returning `ok` / `warning` / `error` / `unknown` with custom messages |
| Import / Export | Full JSON bundle (tabs + templates + version metadata) |
| HTML + Markdown snapshots | Generator pipeline writes `.html` and `.md` files on every save |
| Composer package | `composer require binary-wp/admin-guide` — PSR-4 autoloaded |
| Prefix isolation | Multiple instances coexist in one WordPress install (separate CPT, AJAX actions, assets, hooks, nonces) |
| Dual hook system | Every hook fires both generic (`guide_builder/*`) and per-instance (`{prefix}/guide_builder/*`) variants |
| Tab sources | System tabs, registered post types + taxonomies, and per-integration template tabs |
| Group-only tabs | Container tabs that auto-fall-through to their first child (no own content) |
| Template scaffolding | Auto-generated HTML for post type, taxonomy, and custom tabs |
| i18n ready | Full translation support — `.pot`, `.po`, `.mo` with text domain `binary-wp-admin-guide` |
| Zero-config boot | `Plugin::boot('prefix')` with automatic path and URL detection |

## Bundled Integrations

WooCommerce, Astra, WP Mail SMTP, The Events Calendar, Elementor, Square, SearchWP, PublishPress Futures, PublishPress Authors, WooCommerce Memberships, WooCommerce Subscriptions.

Custom integrations can be added by supplying additional `integrations_dirs[]` at boot time.

## Unique Differentiators

These features are **not available in any competing plugin**:

1. **Composer package with prefix isolation** — the only admin guide solution installable as a dependency with per-instance namespacing
2. **Placeholder system with registry + callbacks** — dynamic content via typed tokens, rendered as pills in the editor
3. **JSON-driven integration auto-detection** — modular content definitions with flexible requirement matching
4. **HTML + Markdown snapshot generation** — static output for fast rendering and external consumption
5. **Developer-first architecture** — PSR-4, dual-scoped hooks, extensible at every layer
6. **External status checker** — live AJAX health monitoring for third-party services
