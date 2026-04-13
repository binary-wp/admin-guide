# Admin Guide — Roadmap

> Last updated: 2026-04-13
>
> Badges: `[FREE]` included in base package · `[PRO]` requires paid license · `[LAUNCH]` release preparation
>
> See also: [Competitive Analysis](COMPETITIVE-ANALYSIS.md) · [Feature List](FEATURES.md)
>
> Items within each version are ordered by dependency — earlier items are prerequisites for later ones.

## Table of Contents

### Next

- [Licensing Model](#licensing-model)
- [v0.7.0 — Builder & editor foundation](#v070--builder--editor-foundation)
  - [1. Pill labels](#1-pill-labels)
  - [2. Native post editor](#2-native-post-editor-for-tab-editing)
  - [3. Builder quick-edit](#3-builder-quick-edit)
  - [4. Guide viewer improvements](#4-guide-viewer-improvements)
- [v0.8.0 — Placeholder engine core](#v080--placeholder-engine-core)
  - [1. Token naming convention](#1-token-naming-convention)
  - [2. output_type strong typing](#2-output_type-strong-typing)
  - [3. Dynamic placeholders (parser)](#3-dynamic-placeholders-parser)
  - [4. Nested placeholders (resolver)](#4-nested-placeholders-resolver)
  - [5. Migration from legacy tokens](#5-migration-from-legacy-tokens)
- [v0.9.0 — Integration layer](#v090--integration-layer)
  - [1. wp\_\_edit / wp\_\_tax navigation](#1-wp__edit--wp__tax--universal-admin-navigation)
  - [2. Per-integration standard placeholders](#2-per-integration-standard-placeholders)
  - [3. Availability checks (integrity check)](#3-availability-checks-integrity-check)
  - [4. Auto-generated integration guide pages](#4-auto-generated-integration-guide-pages)
- [v0.10.0 — Pill UX & editor enhancements](#v0100--pill-ux--editor-enhancements) `[DRAFT]`
  - [1. Pill color coding](#1-pill-color-coding)
  - [2. Pill contextual actions](#2-pill-contextual-actions)
  - [3. Break (detach)](#3-break-detach)
  - [4. Admin link picker in TinyMCE](#4-admin-link-picker-in-tinymce)
- [v0.11.0 — Advanced placeholder views](#v0110--advanced-placeholder-views) `[DRAFT]`
  - [1. Query-based list view](#1-query-based-list-view)
  - [2. Dynamic counts](#2-dynamic-counts)
  - [3. Scoped taxonomy table](#3-scoped-taxonomy-table)
- [v1.0.0-rc — WordPress.org launch](#v100-rc--wordpressorg-launch) `[DRAFT]`
  - [1. Plugin directory submission](#1-plugin-directory-submission)
  - [2. Visual assets](#2-visual-assets)
  - [3. Landing page & documentation](#3-landing-page--documentation)
  - [4. Demo & distribution](#4-demo--distribution)
  - [5. Marketing](#5-marketing)
- [v1.0.0 — PRO infrastructure](#v100--pro-infrastructure) `[IDEA]`
  - [0. Free/PRO business model](#0-freepro-business-model--open-design-problem)
  - [1. License system](#1-license-system)
  - [2. Package skeleton](#2-package-skeleton)
  - [3. Feature flags](#3-feature-flags)
  - [4. PRO admin page](#4-pro-admin-page)
- [v1.1.0 — PRO wave 1](#v110--pro-wave-1) `[IDEA]`
  - [1. Contextual placement](#1-contextual-placement)
  - [2. White-label / branding](#2-white-label--branding)
  - [3. Role-based access](#3-role-based-access)
- [v1.2.0 — PRO wave 2](#v120--pro-wave-2) `[IDEA]`
  - [1. Dashboard widget](#1-dashboard-widget)
  - [2. Premium integrations](#2-premium-integrations)
  - [3. Contact form](#3-contact-form)
- [v1.3.0 — PRO wave 3](#v130--pro-wave-3) `[IDEA]`
  - [1. Multisite sync](#1-multisite-sync)
  - [2. Revision history UI](#2-revision-history-ui)

### Reference

- [PRO Package Architecture](#pro-package-architecture)
- [Implementation Notes](#implementation-notes)

### Done

- [**v0.1.0**](https://github.com/binary-wp/admin-guide/commit/0df0e0a) — Initial release: placeholder engine, JSON integrations, HTML/MD generator
- [**v0.2.0**](https://github.com/binary-wp/admin-guide/commit/591a9f3) — i18n support with Czech translation
- [**v0.3.0**](https://github.com/binary-wp/admin-guide/commit/ab68c5b) — CPT storage, nav-menu drag & drop builder, standalone editor with pill UI, import/export
- [**v0.4.0**](https://github.com/binary-wp/admin-guide/commit/5170958) — Zero-config boot, integration example docs, Packagist CI
- [**v0.5.0**](https://github.com/binary-wp/admin-guide/commit/7b077ed) — Three-page admin UI (Builder, Instructions, Settings & Tools), Astra integration
- [**v0.6.0**](https://github.com/binary-wp/admin-guide/commit/c80d540) — Version reconciliation (v0.4 + v0.5 combined)
- [**v0.6.1**](https://github.com/binary-wp/admin-guide/commit/74de3fd) — Project documentation
- **v0.6.2** — Roadmap expansion & strategy refinement ← **current**

---

## Licensing Model

- **Free:** WordPress.org + Composer — full builder, editor, placeholder engine, integrations, unlimited everything
- **PRO:** Annual per-site license, Composer package `binary-wp/admin-guide-pro` extending the free base
- **Agency:** Unlimited sites, multisite sync, white-label

---

## v0.7.0 — Builder & editor foundation

No dependencies on engine changes. Settles the editing environment before placeholder upgrades.

### 1. Pill labels

- [ ] `[FREE]` Editor pills show human-readable label instead of raw `{{token}}` (use `description` from registry)
- Establishes `data-token` attribute pattern — prerequisite for all future pill work

### 2. Native post editor for tab editing

Depends on: pill labels (editor must support `data-token` before we move to post.php).

- [ ] `[FREE]` Use the default WP post edit screen (`post.php`) instead of custom editor
  - Gives us draft/publish workflow, trash, revisions UI, featured image, custom fields — all free from WP core
  - Placeholder palette placement options (open question):
    - Right sidebar: custom meta box below Publish box — standard WP pattern, but competes for space
    - Left panel: collapsible drawer next to the editor — more room, non-standard
    - Above editor: dropdown/popover triggered by a toolbar button — minimal footprint, less discoverable
    - Hybrid: toolbar button opens a floating panel that can be pinned to sidebar
  - TinyMCE stays as the editor (not Gutenberg) — placeholder pills need `contenteditable=false` support
  - Decides palette placement — needed before v0.8+ adds parametric pill forms

### 3. Builder quick-edit

Depends on: native post editor (builder UI layout must be settled first).

- [ ] `[FREE]` Inline editing of tab properties without opening the editor:
  - Slug (custom and group tabs only — system/post_type/taxonomy slugs stay locked)
  - Attribution — reassign tab to a system item or mark as custom
  - Group / content toggle — switch between group-only container and content tab
  - Tab icon (optional) — Dashicon picker for tab/submenu icon

### 4. Guide viewer improvements

Independent — can land in parallel with above.

- [ ] `[FREE]` **Guide search** — JS search/filter across guide tabs in the admin viewer (within the rendered guide, not the builder)
- [ ] `[FREE]` **Auto-TOC** — parse H2/H3 in rendered tab content, emit anchor links — low priority

---

## v0.8.0 — Placeholder engine core

Foundational decisions — naming convention, type system, parser. Must be settled before any feature work builds on them.

### 1. Token naming convention

Foundation — defines the format all other items use.

- [ ] `[FREE]` **Structured format:** `{{source__entity-view:scope:modifier?filter=value}}`
  | Segment | Separator | Role | Examples |
  |---|---|---|---|
  | `source` | `__` | who owns the data | `wp`, `woo`, `astra`, `tec`, `square` |
  | `entity` | `-` (joined with view) | what thing | `cpt`, `tax`, `user`, `option`, `setting`, `menu` |
  | `view` | `-` (suffix on entity) | how to render = `output_type` = pill color | `link`, `url`, `list`, `info`, `table`, `section`, `image`, `status` |
  | `scope` | `:` | which instance of entity | `product`, `product_cat`, `general` |
  | `modifier` | `:` | ID, sub-property, param | `123`, `label`, `singular`, `count` |
  | `filter` | `?key=val` | query narrowing (list view) | `?tax=cat:5`, `?status=publish`, `?ids=1,2,3` |

  **URL transform layer** — tokens never contain raw URLs or `.php` filenames. All scopes/modifiers are human-readable keys (`menus`, `checkout`, `product`) that get resolved to actual admin URLs at render time through:
  1. **Built-in whitelist** — WP core settings pages (`menus` → `nav-menus.php`, `general` → `options-general.php`, etc.)
  2. **Integration aliases** — from JSON `settings_sections` map (`checkout` → `admin.php?page=wc-settings&tab=checkout`)
  3. **WP registry lookups** — `post_type_exists()`, `taxonomy_exists()`, `menu_page_url()` for dynamic resolution
  
  This decouples token naming from WordPress URL internals — tokens stay stable even if WP changes URL patterns in future versions.

### 2. output_type strong typing

Depends on: naming convention (view segment = output_type).

- [ ] `[FREE]` **`output_type` field in placeholder registry** — canonical type, derived from the `view` segment in the token name. Drives pill color, editor behavior, and rendering. Set explicitly via JSON `type` or inferred from token name.

### 3. Dynamic placeholders (parser)

Depends on: naming convention (parser must understand the full format).

- [ ] `[FREE]` **Parametric tokens** — `{{token:arg1}}`, `{{token:arg1:arg2}}`
  - Parser: regex shift to support `{{source__entity-view:scope:modifier}}`
  - Registry: `register()` accepts optional `$params` schema (name, type, default)
  - Storage: `{{wp__edit:product:123}}` in `post_content` — still a plain token, resolved at render time

### 4. Nested placeholders (resolver)

Depends on: dynamic placeholders (inner tokens must resolve parametrically).

- [ ] `[FREE]` **Inside-out resolution** — resolve inner tokens before outer, like shortcode nesting
  - Use case: `{{wp__setting-link:{{woo__setting-url:checkout}}:Checkout Settings}}`
  - Resolver: iterative inside-out pass (deepest `{{...}}` first), depth limit of 5 passes
  - Backward compatible: existing `{{simple_token}}` works unchanged

### 5. Migration from legacy tokens

Depends on: naming convention + parser (new format must work before aliasing old tokens).

- [ ] `[FREE]` **Alias map** — old tokens resolve to new format. `register()` warns on non-conforming tokens in `WP_DEBUG` mode.

  **Setting link aliases** — clean names mapped to admin URLs, no `.php` in tokens:
  ```
  wp__setting-link:menus          → nav-menus.php
  wp__setting-link:widgets        → widgets.php
  wp__setting-link:customizer     → customize.php
  wp__setting-link:general        → options-general.php
  wp__setting-link:reading        → options-reading.php
  wp__setting-link:writing        → options-writing.php
  wp__setting-link:discussion     → options-discussion.php
  wp__setting-link:media          → options-media.php
  wp__setting-link:permalinks     → options-permalink.php
  wp__setting-link:privacy        → options-privacy.php
  ```
  Built-in whitelist for WP core pages. Integrations register their own aliases via JSON `settings_sections`. No raw URLs in tokens — always a human-readable key.

  **Link placeholders → `wp__edit` / `wp__tax`:**
  ```
  {{wp_menus_link}}              → {{wp__setting-link:menus}}
  {{wp_widgets_link}}            → {{wp__setting-link:widgets}}
  {{wp_customizer_link}}         → {{wp__setting-link:customizer}}
  {{wp_posts_page_link}}         → {{wp__edit:post}}
  {{woo_checkout_settings_link}} → {{woo__setting-link:checkout}}
  {{woo_emails_settings_link}}   → {{woo__setting-link:email}}
  {{pp_authors_listing_link}}    → {{wp__tax:author}}
  {{pp_authors_categories_link}} → {{wp__tax:author_category}}
  ```

  **Auto-generated `*_settings_link` / `*_docs_link`:**
  ```
  {{woo_settings_link}}          → {{woo__setting-link}}
  {{woo_docs_link}}              → {{woo__docs-link}}
  {{astra_settings_link}}        → {{astra__setting-link}}
  {{smtp_settings_link}}         → {{smtp__setting-link}}
  ... (all integrations with settings_url/docs_url)
  ```

  **Info / text → `*-info`:**
  ```
  {{wp_editor_name_text}}        → {{wp__editor-info:name}}
  {{wp_allowed_editors_text}}    → {{wp__editor-info:allowed}}
  ```

  **Complex / table / list (naming only, callbacks unchanged):**
  ```
  {{wp_content_types_table}}     → {{wp__cpt-table}}
  {{wp_other_content_table}}     → {{wp__cpt-table:other}}
  {{wp_post_categories_list}}    → {{wp__tax-list:category}}
  {{wp_editors_list}}            → {{wp__editor-list}}
  {{wp_settings_dashboards_table}} → {{wp__setting-table:dashboards}}
  {{wp_external_services_table}} → {{wp__service-status}}
  {{woo_payment_methods_table}}  → {{woo__gateway-table}}
  {{astra_color_palette}}        → {{astra__color-table}}
  {{astra_typography}}           → {{astra__typography-table}}
  {{tec_categories_list}}        → {{wp__tax-list:tribe_events_cat}}
  {{woo_subscriptions_products_table}} → {{woo__subscription-table}}
  {{woo_memberships_plans_section}}    → {{woo__membership-section}}
  ```

---

## v0.9.0 — Integration layer

Depends on: v0.8 engine (naming, typing, dynamic placeholders). Builds the integration-specific placeholder ecosystem.

### 1. wp\_\_edit / wp\_\_tax — universal admin navigation

First concrete implementation of dynamic placeholders.

- [ ] `[FREE]` **`wp__edit`** — universal CPT/post admin links:
  ```
  wp__edit:product                    → <a> to list view (edit.php?post_type=product)
  wp__edit:product:123                → <a> to record edit (post.php?action=edit&post=123)
  wp__edit:product:term:product_cat:5 → <a> to filtered list view
  wp__edit:post                       → <a> to Posts list (built-in types too)
  ```
  **URL + label resolver:**
  - No arg → `get_post_type_object()->labels->name` ("Products")
  - ID arg → `get_the_title(id)` ("Red T-Shirt")
  - Term arg → `get_term()->name . ' — ' . labels->name` ("Clothing — Products")

- [ ] `[FREE]` **`wp__tax`** — universal taxonomy admin links:
  ```
  wp__tax:product_cat                 → <a> to taxonomy list
  wp__tax:product_cat:5               → <a> to term edit
  wp__tax:category                    → <a> to Categories (built-in)
  ```

### 2. Per-integration standard placeholders

Depends on: `wp__edit` / `wp__tax` (used in integration templates).

- [ ] `[FREE]` **Auto-generated from JSON** — every integration produces:
  ```
  {source}__setting-link                  → <a> to main settings page
  {source}__setting-link:{section}        → <a> to specific settings section
  {source}__docs-link                     → <a> to external documentation
  {source}__screenshot:{view}             → <img> visual documentation
  ```
  JSON schema gains `settings_sections` and `screenshots` maps:
  ```json
  {
    "settings_sections": {
      "checkout": "admin.php?page=wc-settings&tab=checkout",
      "email": "admin.php?page=wc-settings&tab=email"
    },
    "screenshots": {
      "settings": "screenshots/woo-settings.png",
      "checkout": "screenshots/woo-checkout.png"
    }
  }
  ```

- [ ] `[FREE]` **Declared post types & taxonomies** — integration JSON lists owned CPTs/taxonomies:
  ```json
  {
    "post_types": ["product", "shop_order", "shop_coupon"],
    "taxonomies": ["product_cat", "product_tag"]
  }
  ```
  Auto-generates shortcut placeholders (`woo__edit:product` → alias for `wp__edit:product`) grouped under the integration name in the palette.

### 3. Availability checks (integrity check)

Depends on: per-integration standard placeholders (checks validate their targets).

- [ ] `[FREE]` **Placeholder integrity validation** — placeholders verify their targets exist at resolve time:
  | Placeholder type | Check | On failure |
  |---|---|---|
  | `setting-link` | Admin URL via `menu_page_url()` or `$submenu` lookup | Hide or render as disabled |
  | `setting-link:{section}` | Same — section URL valid | Fall back to main settings link |
  | `docs-link` | Optional: HTTP HEAD (cached, async) | Warning icon or skip |
  | `screenshot` | `file_exists()` on image path | "Screenshot not found" notice |
  | `wp__edit:type` | `post_type_exists()` | Hide or disabled |
  | `wp__edit:type:id` | `get_post(id)` valid | "Record not found" or fallback to list |
  | `wp__tax:taxonomy` | `taxonomy_exists()` | Hide or disabled |
  | `wp__tax:taxonomy:id` | `get_term(id)` valid | "Term not found" or fallback to list |

  Checks run at resolve time, cached per request via `wp_cache_set()`. Builder/editor shows a **validation summary** — list of all placeholders with broken targets.

### 4. Auto-generated integration guide pages

Depends on: ALL of the above (uses every new placeholder type + integrity checks).

- [ ] `[FREE]` **Zero-config guide tabs** — for every active integration, scaffold a default guide tab from placeholders:
  ```html
  <h1>WooCommerce</h1>
  <p>{{woo__plugin-info:description}}</p>
  <p>Settings: {{woo__setting-link}} | Documentation: {{woo__docs-link}}</p>
  <h2>Content Types</h2>
  {{woo__cpt-table}}
  <h2>Taxonomies</h2>
  {{woo__tax-table}}
  <h2>Settings Sections</h2>
  {{woo__setting-table}}
  <h2>External Services</h2>
  {{woo__service-status}}
  ```

  **New placeholder types:**
  | Placeholder | Output | Source |
  |---|---|---|
  | `{source}__plugin-info:description` | Plugin description text | JSON `description`, fallback to WP plugin header via `get_plugin_data()` |
  | `{source}__plugin-info:version` | Version string | WP plugin header `Version:` |
  | `{source}__plugin-info:author` | Author name | WP plugin header `Author:` |
  | `{source}__cpt-table` | Table of owned CPTs (label, count, admin link, availability) | JSON `post_types[]` |
  | `{source}__tax-table` | Table of owned taxonomies (label, count, admin link) | JSON `taxonomies[]` |
  | `{source}__setting-table` | Settings sections with availability status (✅/❌) | JSON `settings_sections{}` |
  | `{source}__service-status` | AJAX-driven external service status | JSON `external[]` (already exists) |

  JSON schema gains top-level `description` field (fallback to WP plugin header).

  - Zero-config baseline — every integration gets a functional guide page out of the box
  - User can break/customize any placeholder or replace the whole template
  - Output naming: `{slug}-overview` tab, compilable as `{slug}-general.md` snapshot

---

## v0.10.0 — Pill UX & editor enhancements

> Status: **discussed in this session but not deeply validated.** Color palette and output_type mapping are well-defined (see v0.8). Specific UX interactions (break, param forms) need prototyping — complexity is uncertain.

Depends on: v0.8 (output_type, dynamic placeholders) + v0.9 (real placeholders to work with). Polish layer — not blocking core functionality.

### 1. Pill color coding

Discussed and agreed — color ↔ output_type mapping is defined.

- [ ] `[FREE]` **Color by output type** (WP admin palette):
  | Type | Color | WP token |
  |---|---|---|
  | `info` | Gray `#646970` | secondary |
  | `link` | Blue `#2271b1` | primary / link |
  | `image` | Orange `#dba617` | warning accent |
  | `list` | Teal `#0a6273` | info accent |
  | `table` / `section` | Green `#00a32a` | success |
  | `status` / live feed | Red `#d63638` | destructive |

### 2. Pill contextual actions

Discussed — `×` remove and `✎` edit are straightforward. Param form UI is the biggest unknown.

- [ ] `[FREE]` **`×` remove** on all pills, **`✎` edit** on dynamic (parametric) pills
- [ ] `[FREE]` **Editor UI for params** — pill insert opens a param form (post type picker, AJAX autocomplete, text input). Complexity TBD — needs prototyping.

### 3. Break (detach)

Discussed conceptually — resolve pill to editable HTML, keep inner pills intact. Implementation complexity unclear: TinyMCE `contenteditable` nesting behavior needs testing.

- [ ] `[FREE]` **Break** — converts a pill into an editable HTML clone of its resolved output. Nested placeholders stay as pills.

### 4. Admin link picker in TinyMCE

Discussed — clear value, unclear feasibility. TinyMCE link dialog extension API may or may not support this cleanly.

- [ ] `[FREE]` **Admin link picker** — extend the TinyMCE link dialog with an "Admin page" tab/autocomplete
  - Inserts placeholder-backed link instead of hardcoded URL
  - Complexity: TBD — may need a custom dialog vs extending the built-in one

---

## v0.11.0 — Advanced placeholder views

> Status: **discussed in this session.** `cpt-list` with query args is well-defined conceptually. Implementation is a significant piece — essentially building a WP_Query wrapper with filter syntax parsing. Counts and scoped tables are simpler.

Depends on: v0.9 integration layer (dynamic placeholders, naming convention, output_type all stable).

### 1. Query-based list view

Discussed — syntax defined, maps to WP_Query args. Needs filter parser + editor UI for building queries.

- [ ] `[FREE]` **`cpt-list` view** — query-based `<ul>`, mimics WP_Query args (admin-scoped shortcodes):
  ```
  wp__cpt-list:product                → <ul> all products
  wp__cpt-list:product?tax=cat:5      → <ul> filtered by term
  wp__cpt-list:product?ids=1,2,3      → <ul> specific posts
  wp__cpt-list:product?status=draft   → <ul> by status
  ```

### 2. Dynamic counts

Straightforward — `wp_count_posts()` / `wp_count_terms()`.

- [ ] `[FREE]` **`cpt-info:count`** / **`tax-info:count`** — dynamic counts (`"47"`, `"12"`)

### 3. Scoped taxonomy table

- [ ] `[FREE]` **`tax-table` scoped view** — `wp__tax-table:product_cat@product` — term table scoped to CPT

---

## v1.0.0-rc — WordPress.org launch

> Status: **checklist items are standard WP.org requirements** — well-known process, no design uncertainty. Timing depends on when we consider the free product feature-complete. Minimum viable: v0.9 done.

### 1. Plugin directory submission

- [ ] `[LAUNCH]` **`readme.txt`** — WP standard format (short description, long description, installation, FAQ, changelog, upgrade notice)
  - Tested up to / Requires at least / Stable tag headers
  - Tags: `admin`, `guide`, `documentation`, `help`, `onboarding`, `client`

### 2. Visual assets

Depends on: feature-complete plugin (screenshots must show final UI).

- [ ] `[LAUNCH]` **Screenshots** (minimum set for WP.org listing):
  1. Builder — drag & drop tab management
  2. Editor — TinyMCE with placeholder pills in sidebar
  3. Guide viewer — rendered guide as end-user sees it
  4. Integrations — auto-detected plugins with status
  5. Placeholder palette — grouped tokens with descriptions
- [ ] `[LAUNCH]` **Banner & icon assets**
  - Plugin icon: 256×256 (SVG preferred) — guide/book motif
  - Banner: 1544×500 (hi-res) + 772×250 (standard)

### 3. Landing page & documentation

Depends on: visual assets (screenshots for hero section and docs).

- [ ] `[LAUNCH]` **Plugin landing page** — `binarywp.com/admin-guide` or similar
  - Hero section: tagline + screenshot + install CTA
  - Feature grid, integration showcase, developer section
  - Free vs PRO comparison table (when PRO exists)
  - Testimonials / use cases (agency handoff, client documentation, team onboarding)
- [ ] `[LAUNCH]` **Documentation site** — GitHub wiki, Notion, or docs/ in repo
  - Getting started, custom integrations, placeholder reference, hook reference, theming

### 4. Demo & distribution

Depends on: WP.org submission approved, docs ready.

- [ ] `[LAUNCH]` **Demo / playground** — WP Playground or hosted demo with pre-built guide tabs
- [ ] `[LAUNCH]` **GitHub repo polish** — issue templates, contributing guide, badges, SVN deploy action

### 5. Marketing

Depends on: screenshots, demo, landing page.

- [ ] `[LAUNCH]` **Marketing assets** — demo GIF/video (30–60s), social card, "Built with Admin Guide" badge

---

## v1.0.0 — PRO infrastructure

> Status: **conceptual.** Based on competitive analysis — competitors monetize via per-site licenses. We discussed the general shape (separate Composer package extending free base) but haven't validated the license platform choice or pricing. The dependency chain below is logical but untested. **This is one of the biggest open design decisions in the project.**

Depends on: WP.org launch (free product must be live first).

### 0. Free/PRO business model — open design problem

Before any code: decide HOW the split works. This is architectural — affects package structure, distribution, update mechanism, and user experience.

**Distribution model variants:**

| Variant | How it works | Pros | Cons |
|---|---|---|---|
| **A. Separate plugin** | Free on WP.org, PRO as separate download from our site | Clean separation, WP.org handles free updates | Two plugins to manage, activation UX clunky |
| **B. Freemium single plugin** | One plugin, PRO unlocked by license key (Freemius/EDD SDK embedded) | Single install, smooth upgrade UX, Freemius handles payments+updates+licensing | SDK bloat, Freemius takes 7% revenue, vendor lock-in |
| **C. Composer add-on** | Free base `binary-wp/admin-guide`, PRO as `binary-wp/admin-guide-pro` requiring the base | Clean for developers, Composer-native | Non-developers can't use Composer, need WP-friendly install path too |
| **D. Hybrid (C + A)** | Composer packages for devs, WP.org free + standalone PRO zip for end users | Best of both worlds | Most complex to maintain, two distribution channels |

**License platform variants:**

| Platform | Model | Cut | Notes |
|---|---|---|---|
| **Freemius** | Handles everything (payments, licensing, updates, analytics) | 7% | Most turnkey, used by 1000s of WP plugins. Embeds SDK in plugin. Has Composer support via `freemius/wordpress-sdk`. |
| **EDD (self-hosted)** | WP site with Easy Digital Downloads + Software Licensing add-on | 0% (hosting costs) | Full control, no revenue cut. Must self-host update server. More setup/maintenance. |
| **WooCommerce + Woo Software Add-on** | Similar to EDD but WooCommerce-based | 0% | If already using Woo for other sales. Less mature for software licensing than EDD. |
| **LemonSqueezy / Paddle** | External payment processor + license API | ~5-8% | Modern, handles EU VAT. Need custom update checker in plugin. |
| **Custom** | Own API for license validation + update server | 0% | Maximum control, maximum effort. Only makes sense at scale. |

**Update mechanism:**
- WP.org handles free plugin updates automatically
- PRO updates need a custom update checker hooking into `pre_set_site_transient_update_themes` / `plugins_api` — or handled by Freemius/EDD SDK
- Composer updates via Packagist (free) or private Packagist / Satis (PRO)

**Feature gating approaches:**

| Approach | How | Notes |
|---|---|---|
| **Code-level flags** | `$plugin->has_pro('feature')` checks license at runtime | Simple, but PRO code ships with free (can be reverse-engineered) |
| **Separate codebase** | PRO features only in PRO package, not in free at all | Cleaner IP protection, but more complex build/release |
| **Hook-based** | Free fires hooks, PRO registers callbacks | Our current architecture already supports this (`guide_builder/init`) — most natural fit |

**Pricing model considerations:**
- Per-site annual vs lifetime — annual is recurring revenue, lifetime is higher upfront
- Tiered: Personal (1 site), Business (5 sites), Agency (unlimited) — standard WP plugin pattern
- What's free vs what's PRO is defined in this roadmap — but the exact line may shift based on user feedback

**Open questions:**
- Do we want Freemius analytics (usage tracking, deactivation feedback) or is that too invasive?
- Composer-only distribution or must we also support manual zip install for non-developers?
- Do we self-host a sales site (binarywp.com) or use a marketplace (Freemius, CodeCanyon)?
- How do we handle grace periods when a license expires — degrade gracefully or hard-lock PRO features?

### 1. License system

- [ ] `[PRO]` Choose platform (Freemius / EDD / LemonSqueezy / custom) — see variants above
- [ ] `[PRO]` Implement license validation — API call on activation, periodic recheck, grace period logic

### 2. Package skeleton

Depends on: license system (needs license check at boot) + distribution model choice.

- [ ] `[PRO]` `admin-guide-pro` package — extends base via `guide_builder/init` hook, autoloads PRO modules
- [ ] `[PRO]` Distribution pipeline — Composer private package and/or downloadable zip with auto-updater

### 3. Feature flags

Depends on: package skeleton (flag check lives in Pro class).

- [ ] `[PRO]` Feature flag system — `$plugin->has_pro('feature_name')` — gating approach TBD (see variants above)

### 4. PRO admin page

Depends on: license system + feature flags (page shows key input + toggles).

- [ ] `[PRO]` License key input, activation/deactivation, feature toggles, PRO status overview, expiry warnings

---

## v1.1.0 — PRO wave 1

> Status: **from competitive analysis.** Contextual placement is the #1 feature Admin Help Docs has that we don't — strong signal from the market. White-label is standard agency upsell. Role-based access is straightforward (meta on CPT + capability check). None of these are designed yet — just identified as high-value gaps.

Depends on: PRO infrastructure (license system, feature flags).

### 1. Contextual placement

Identified as **key competitive gap** — Admin Help Docs offers CSS-selector-based placement. Our approach (screen ID mapping) is discussed in [Implementation Notes](#contextual-placement-pro) but not prototyped.

- [ ] `[PRO]` `ContextualDisplay` class, screen ID → tab mapping, `admin_notices` or custom meta box hook

### 2. White-label / branding

Standard agency feature. Low complexity — CSS custom properties + options page.

- [ ] `[PRO]` Logo upload, custom colors, CSS override, hide "Powered by"

### 3. Role-based access

Straightforward — add `_guide_roles[]` meta to tab CPT, check capabilities in render pipeline. Exact UX (per-tab UI in builder) not designed.

- [ ] `[PRO]` Per-tab capabilities, show/hide tabs by user role

---

## v1.2.0 — PRO wave 2

> Status: **wish list from competitive analysis.** These are features competitors have that round out a PRO offering. No design work done. Priority and grouping may change based on user feedback after v1.0 launch.

### 1. Dashboard widget

Standard WP pattern, low complexity.

- [ ] `[PRO]` `wp_dashboard_setup`, configurable content

### 2. Premium integrations

Low effort — just JSON files + callback functions. Which plugins to cover depends on user demand.

- [ ] `[PRO]` Gravity Forms, ACF, WPML, Yoast, WPForms — candidate list, not final

### 3. Contact form

Placeholder-based (`{{support_form}}`). Needs email/webhook handler. Not designed.

- [ ] `[PRO]` Embedded support form — email + webhook notification

---

## v1.3.0 — PRO wave 3

> Status: **speculative.** Multisite sync is WP Help's core differentiator (13 years of it). Building a competitive version is high-effort (REST API, conflict resolution, cron). Revision history UI is simpler (CPT revisions exist, just needs a diff viewer). Neither is designed.

### 1. Multisite sync

High complexity. WP Help does auto-pull from a central instance. Our approach (REST push/pull) is one option — could also be simple export/import between sites.

- [ ] `[PRO]` Content sync between sites — approach TBD

### 2. Revision history UI

Low–medium complexity. CPT revisions already exist in WP core. This just adds a visual diff + rollback button in admin.

- [ ] `[PRO]` Diff viewer in admin, rollback button

---

## PRO Package Architecture

```
binary-wp/admin-guide-pro (Composer)
├── src/
│   ├── Pro.php              — boot, license check, feature registration
│   ├── ContextualDisplay.php — screen-based tab placement
│   ├── Branding.php         — white-label settings + render
│   ├── RoleAccess.php       — per-tab capability checks
│   ├── DashboardWidget.php  — wp_dashboard_setup integration
│   ├── Sync.php             — multisite push/pull
│   └── ContactForm.php      — embedded support form
├── integrations/            — premium integration JSON files
└── assets/                  — PRO-only CSS/JS
```

**Hook-based integration:** PRO connects to base via `guide_builder/init` hook → `Pro::boot($plugin)`

**Feature check:** `$plugin->pro->has('contextual_display')` returns `bool`

---

## Implementation Notes

### Contextual Placement (PRO)

- Hook into `current_screen` to map `screen_id` → guide tab(s)
- Builder UI adds a "Show on screen" select populated with available screen IDs
- Tab meta: `_guide_screens[]` on the guide page CPT
- Render: `admin_notices` hook or custom meta box for sidebar placement
- JS: collapsible panel with guide content, lazy-loaded via AJAX

### White-label (PRO)

- Options: `logo_url`, `primary_color`, `secondary_color`, `custom_css`, `hide_branding`
- Storage: `{prefix}_guide_branding` option (serialized array)
- Render: `wp_add_inline_style()` with CSS custom properties

### Placeholder Engine Upgrade

**Pill labels (v0.7.0)**
- Editor already renders pills via `contenteditable=false` spans
- Change: pill innerHTML switches from `{{token}}` to the `description` field from the registry
- `data-token="{{token}}"` attribute keeps the real value for save/load

**Dynamic placeholders (v0.8.0)**
- Token syntax: `{{name}}` (current) + `{{name:arg1}}` + `{{name:arg1:arg2}}`
- Resolve regex: `/\{\{([a-z_]+)(?::([^}]*))?\}\}/` → token + raw params string, split on `:`
- `Placeholders::register()` gains optional 4th arg `$params` — array of `[ 'name' => string, 'type' => 'string'|'post_type'|'post_id'|'url', 'default' => mixed ]`
- Callback signature: `function( $arg1, $arg2, ... )` — resolver unpacks params

**Nested placeholders (v0.8.0)**
- Resolver runs iterative inside-out: find innermost `{{...}}` (no nested `{{` inside), resolve, repeat
- Depth limit: 5 passes max, then bail with unresolved tokens left as-is
- Backward compatible: existing `{{simple_token}}` works unchanged
