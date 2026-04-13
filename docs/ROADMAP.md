# Admin Guide — Roadmap

> Last updated: 2026-04-13

## Free vs PRO Tiers

### Free (current + upcoming improvements)

Everything available today, plus:

- Auto-generated table of contents from H2/H3 headings
- JS search/filter across tabs on the instructions page
- Unlimited tabs, placeholders, and integrations

### PRO (planned)

| Feature | Description | Complexity |
|---|---|---|
| **Contextual placement** | Show guide panels/widgets on any admin screen via screen ID mapping | Medium |
| **White-label / branding** | Logo upload, custom colors, CSS override, hide "Powered by" | Low |
| **Multisite sync** | Push/pull tabs between sites via REST API + wp-cron | High |
| **Role-based access** | Per-tab capabilities — show/hide tabs by user role | Low–Medium |
| **Dashboard widget** | Guide summary widget or full dashboard replacement | Low |
| **Premium integrations** | Gravity Forms, ACF, WPML, Yoast, WPForms, and more | Low (JSON files) |
| **Contact form** | Embedded support form with email/webhook notification | Low |
| **Revision history UI** | Visual diff and rollback in admin (CPT revisions already exist) | Medium |

### Licensing Model

- **Free:** WordPress.org + Composer — everything available today plus minor improvements
- **PRO:** Annual per-site license, Composer package `binary-wp/admin-guide-pro` extending the free base
- **Agency:** Unlimited sites, multisite sync, white-label

---

## Implementation Phases

### Phase 1 — Free tier polish (v0.7.0)

- [ ] Auto-TOC generator (parse H2/H3 in templates, emit anchor links)
- [ ] JS search/filter across tabs on the instructions page
- [ ] Minor UX fixes from current backlog

### Phase 2 — PRO infrastructure (v1.0.0)

- [ ] License system (API validation or EDD/WooCommerce/Freemius integration)
- [ ] `admin-guide-pro` package skeleton (extends base, autoloads PRO modules)
- [ ] Feature flag system — `$plugin->has_pro('feature_name')`
- [ ] PRO admin page (license key input, feature toggles)

### Phase 3 — PRO features wave 1 (v1.1.0)

- [ ] **Contextual placement** — `ContextualDisplay` class, screen ID to tab mapping, `admin_notices` or custom meta box hook
- [ ] **White-label** — settings section (logo, colors, CSS), `wp_add_inline_style()`
- [ ] **Role-based access** — meta `_guide_roles[]` on tab CPT, capability check in render pipeline

### Phase 4 — PRO features wave 2 (v1.2.0)

- [ ] **Dashboard widget** — `wp_dashboard_setup`, configurable content
- [ ] **Premium integrations** — Gravity Forms, ACF, WPML, Yoast, WPForms
- [ ] **Contact form** — placeholder `{{support_form}}`, email + webhook handler

### Phase 5 — PRO features wave 3 (v1.3.0)

- [ ] **Multisite sync** — REST API endpoint for push/pull, wp-cron scheduler, conflict resolution
- [ ] **Revision history UI** — diff viewer in admin, rollback button

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

## Implementation Notes

### Contextual Placement (key PRO feature)

- Hook into `current_screen` to map `screen_id` → guide tab(s)
- Builder UI adds a "Show on screen" select populated with available screen IDs
- Tab meta: `_guide_screens[]` on the guide page CPT
- Render: `admin_notices` hook or custom meta box for sidebar placement
- JS: collapsible panel with guide content, lazy-loaded via AJAX

### White-label

- Options: `logo_url`, `primary_color`, `secondary_color`, `custom_css`, `hide_branding`
- Storage: `{prefix}_guide_branding` option (serialized array)
- Render: `wp_add_inline_style()` with CSS custom properties
