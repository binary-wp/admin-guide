# Changelog

## 0.6.0 — 2026-04-13

### Added
- **Version reconciliation** — fixed version numbering after parallel development sessions caused v0.4.0/v0.5.0 overlap.

### Note
This release combines v0.4.0 (zero-config boot, integration example, Packagist CI) and v0.5.0 (three-page UI, Astra integration) into a single clean release.

## 0.5.0 — 2026-04-13

### Added
- **Three-page admin UI** — Builder, Instructions, Settings & Tools as sub-pages with shared `nav-tab-wrapper` navigation.
- **Instructions page** — usage guide (templates, placeholders, hierarchy, editor) + full integration registry showing status, system guide pages, external checks, and placeholder tables per integration.
- **Settings & Tools page** — import/export moved here from builder, plus Guide Info panel (output dir, page counts, active integrations, prefix, version).
- **Astra integration** — `{{astra_color_palette}}` renders global color swatches with CSS variable names; `{{astra_typography}}` renders body + heading font settings.
- `integrations/functions/astra.php` — render functions for Astra theme colors and typography.
- Host-registered placeholders section on Instructions page for placeholders outside integration JSONs.

### Changed
- **Layout** — sidebar moved to left on both builder and editor pages. Builder main column capped at 800px.
- Import redirect now points to Settings & Tools page.
- `pp_authors_people_link` renamed to `pp_authors_listing_link` — "People" relabeling is host-specific (HFP), not generic.

### Fixed
- Host-registered placeholder detection — tokens already include `{{}}` braces, no longer double-wrapped.

## 0.4.0 — 2026-04-13

### Added
- **Integration example** in README — complete walkthrough for creating custom integration JSON + PHP callback.
- **GitHub Actions** — Packagist auto-update workflow triggered on published releases.
- `CLAUDE.md` — project context for AI-assisted development.

### Fixed
- POT file version bumped from stale 0.2.0 to match release.

## 0.3.0 — 2026-04-13

### Added
- **CPT storage** — guide pages stored as hierarchical `{prefix}_guide_page` custom post type instead of wp_option. Enables revisions, REST API, native WP export.
- **Nav-menu style builder** — drag & drop sortable with depth management (max 1 level). Uses WP core nav-menu DOM structure and CSS for native look.
- **Separate editor page** — standalone `wp_editor()` with placeholder palette sidebar. Placeholder pills rendered as `contenteditable="false"` spans in TinyMCE.
- **Group-only tabs** — organizational parent tabs with no own content; auto-fall through to first child.
- **One-shot migration** from legacy wp_option + filesystem templates to CPT (`Config::maybe_migrate()`).
- **Import/export** — JSON bundle with two-pass import preserving hierarchy.
- **Slug change cleanup** — `Generator::remove_files()` deletes old `.md`/`.html` when a guide slug changes.
- **File cleanup on delete** — removing a guide also removes its generated files and children's files.
- `guide-editor.js` — TinyMCE pill conversion, placeholder palette with collapsible groups, click-to-insert + drag & drop.
- `integrations/functions/publishpress-future.php` — editor-aware screenshot placeholder for PublishPress Future.
- PublishPress Authors integration: added `pp_authors_categories_screenshot` and `pp_authors_metabox_screenshot` placeholders.
- PublishPress Future integration: added `pp_future_metabox_screenshot` (callback, editor-aware) and `pp_future_quickedit_screenshot` placeholders.

### Changed
- `Config.php` — complete rewrite for CPT queries. `get_tabs()`, `get_children()`, `get_all_guides()` (tree walk), `save_order()`, `add_tab()`, `remove_tab()`.
- `Admin.php` — complete rewrite. Two-page architecture (builder + editor). AJAX handlers for `save_order`, `add_guide`, `remove_guide`. Sidebar metaboxes for adding guides.
- `Plugin.php` — registers CPT on `init` hook. Added `get_post_type()` helper.
- `guide-builder.js` — rewrite for nav-menu depth management, collectOrder with parent stack, toggle via `.item-edit`.
- `guide-builder.css` — nav-menu overrides, flex layout with 300px sidebar, metabox styling, placeholder palette.
- `integrations/functions/default.php` — fixed Classic Editor documentation link, added non-Elementor posts listing in editor list.

### Fixed
- Inline label rename now targets `.menu-item-title` span instead of `.item-title` label wrapper.
- `#guide-sortable` padding uses `!important` to survive jQuery UI Sortable inline styles.

## 0.2.0 — 2026-04-10

- i18n support with Czech translation.

## 0.1.0 — 2026-04-09

- Initial release: Admin Guide builder for WordPress.
