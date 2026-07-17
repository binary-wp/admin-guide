# Changelog

## 0.10.0 — 2026-07-17

### Added
- **Viewer content paint** (`assets/guide-viewer.css`) — the generated guide body now ships its own chrome instead of leaving hosts to paint it:
  - **Tables** get the standard wp-admin list-table look (`.widefat .striped`: header band, hairline borders, zebra rows, comfortable padding) via element selectors, so both `{{placeholder}}` output and hand-authored tab markup are covered with no per-table class.
  - **Figures** get base paint (bordered image, muted figcaption) — deliberately no width or float, so a figure carrying its own sizing keeps it.
  - **`.binary-wp-admin-guide-figure-inset`** — opt-in modifier that floats a figure right with the copy wrapping around it, capped at `340px` / `42%` and unfloated below 960px. Opt-in rather than automatic because the narrow column suits a screenshot but ruins anything needing its natural width (a wide screencast, say).
- Values are wp-admin's own tokens (`#c3c4c7` / `#dcdcde` / `#f6f7f7`), all scoped to `.binary-wp-admin-guide-viewer`, so nothing leaks into other admin screens.

### Changed
- **Viewer styles moved from an inline `<style>` block to an enqueued stylesheet** — handle `{prefix}-admin-guide-viewer`, versioned with `package_version` for cache-busting. The nav-tab / sub-nav / header-actions rules previously printed inline in `Viewer::render_page()` now live in the same file.

### Migration / upgrade notes
- Hosts that shipped their own viewer table or figure CSS (scoping rules to `.binary-wp-admin-guide-viewer__body`) can now drop it — the package covers it. Palmetto's `includes/admin-guide-content/guide-styles.php` was the reference case and is removed in the host plugin.
- Hosts emitting floated screenshot figures should swap their own float class for `binary-wp-admin-guide-figure-inset` and regenerate the guide snapshots.

## 0.9.1 — 2026-07-05

### Fixed
- **Generator slug/file mismatch** — `generate()` wrote snapshots using the raw slug (`pages.html`) while `read_tab()`/`remove_files()` resolved the path via `sanitize_file_name()`, which mangles slugs WordPress reads as bare extensions (e.g. `pages` → `unnamed-file.pages`). Any such tab rendered blank in the Viewer and Regenerate couldn't fix it. Both sides now use `sanitize_key()` consistently (a no-op for normal slugs), so `pages` and similar tabs resolve correctly.

## 0.9.0 — 2026-07-02

### Added
- **`menu.builder` boot arg** (bool, default `true`). Set `false` to hide the Guide Builder / Instructions / Settings & Tools submenus from the admin menu while keeping the pages routable — the guide is then reached through the Viewer only.
- **Viewer "Guide Builder" button** — a right-aligned action in the Viewer header (next to Regenerate) linking to the builder, so hosts that hide the builder submenu still expose it in one click.

### Fixed
- **Viewer nav wrapping** — scoped CSS so the top `.nav-tab-wrapper` wraps cleanly via flexbox (no floated-tab overlap when there are many tabs) and the child `.subsubsub` sub-nav wraps instead of overflowing; first content heading no longer collides with the sub-nav.

## 0.8.0 — 2026-05-09

### Changed
- **CPT renamed: `{prefix}_guide_page` → `{prefix}_guide`** in `Plugin::register_post_type()` + `Config` post_type binding. Frees the unprefixed `guide` namespace for content CPTs registered by host sites. Records under the legacy post_type are auto-migrated on init (see below).

### Added
- **`Plugin::migrate_legacy_post_type()`** — one-time backward-compat migration: scans `wp_posts` for any `{prefix}_guide_page` rows and updates `post_type` → `{prefix}_guide`. Preserves post-id, postmeta, revisions (direct DB UPDATE, no insert/delete). Idempotent — completion marker stored in `{prefix}_admin_guide_pt_migration_v0_8` option, subsequent boots short-circuit. Hooked on `init` priority 11 (after `register_post_type` priority 10). Flushes rewrite rules + cache when records were updated.

### Migration / upgrade notes
- Hosts that upgrade from 0.7.x: no API changes for code that uses `Plugin::get_post_type()` (returns the new slug). Code that hardcodes the literal `{prefix}_guide_page` post_type in queries / `wp_insert_post` / `get_page_by_path` calls must be updated to `{prefix}_guide`. The package's own internals (Config queries, Generator post lookups, etc.) handle this automatically.
- Existing data migrates the first time the plugin loads under v0.8.0 — no manual action required.

## 0.7.0 — 2026-04-16

### Added
- **`Viewer` class** (`src/Viewer.php`) — new read-only admin page that renders
  the assembled guide to end-user roles. Horizontal `nav-tab-wrapper` for
  top-level tabs, WooCommerce-style `.subsubsub` for children, generated HTML
  body underneath. Regenerate button rebuilds snapshots on demand.
- Viewer URL: `?page={prefix}-admin-guide-viewer` (uses `Context::page_slug('viewer')`).
- Regenerate handler on `admin_post_{prefix}_admin_guide_regenerate_viewer`.
- `menu.viewer_label` boot arg is now honoured (was previously a no-op).
- `menu.builder_label` boot arg is now honoured (was previously a no-op).
- `menu.viewer` boot arg (bool) — set to `false` to skip registering the
  viewer submenu; useful for hosts that already roll their own.

### Changed
- `Plugin::__construct` instantiates the new `Viewer` by default (opt-out via
  `menu.viewer = false`). Hosts that upgrade from 0.6.x gain a new "Admin
  Guide" submenu automatically — no API change, pure addition.

### Backward compatibility
- No breaking changes. Hosts on 0.6.x continue to work as-is after composer
  update. The only user-facing difference is the new submenu; hosts that
  already shipped their own viewer should either drop it (preferred) or set
  `menu.viewer => false` to keep their implementation.

## 0.6.2 — 2026-04-13

### Added
- `docs/ROADMAP.md` — complete rewrite: linear versioned timeline (v0.7–v1.3), dependency ordering, `[FREE]`/`[PRO]`/`[LAUNCH]` badges, status tags
- Placeholder engine design — token naming convention (`source__entity-view:scope:modifier`), output_type strong typing, dynamic/nested placeholders, URL transform layer
- Integration layer design — `wp__edit`/`wp__tax` navigation, per-integration standard placeholders, availability checks, auto-generated guide pages
- Pill UX design — color coding by output_type (WP admin palette), contextual actions, break/detach workflow
- WordPress.org launch checklist — readme.txt, screenshots, landing page, docs, marketing
- Free/PRO business model analysis — distribution variants, license platforms, feature gating, pricing

### Changed
- `docs/COMPETITIVE-ANALYSIS.md` — version updated to v0.6.2, feature matrix validated

## 0.6.1 — 2026-04-13

### Added
- `docs/FEATURES.md` — complete feature inventory with unique differentiators
- `docs/COMPETITIVE-ANALYSIS.md` — comparison matrix vs WP Help, Admin Help Docs, WP Admin Pages PRO
- `docs/ROADMAP.md` — free/PRO tier split, 5-phase implementation plan, PRO package architecture

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
