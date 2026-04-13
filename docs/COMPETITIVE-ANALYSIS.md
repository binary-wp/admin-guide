# Admin Guide — Competitive Analysis

> Last updated: 2026-04-13

## Landscape Overview

| | **Admin Guide** | **WP Help** | **Admin Help Docs** | **WP Admin Pages PRO** |
|---|---|---|---|---|
| Price | Free (Composer) | Free | Free | $79–149 |
| Installs | New | 10,000+ | 400+ | Premium only |
| Status | Active (v0.6.0) | Stagnant (2024) | Active (2026) | Active |
| Target | Developers | End users | End users / agencies | Agencies |

## Competitor Profiles

### WP Help (Mark Jaquith)

The longest-running plugin in the space (10K+ installs, launched ~2013).

- **Strengths:** Multisite content sync (auto-pull from central install), Gutenberg support, established brand, 20 translated locales
- **Weaknesses:** Not tested with the latest 3 WP releases, no dynamic placeholders, no integrations, no Composer support, minimal developer hooks
- **Verdict:** Strong legacy position but declining. No development activity suggests EOL risk.

### Admin Help Docs (PluginRx)

The most feature-rich free alternative and closest competitor by concept.

- **Strengths:** Contextual placement (CSS selector, hook, top/bottom/side), branding/theming, dashboard replacement, auto-TOC, built-in search, per-doc role access, developer hooks (v2.0)
- **Weaknesses:** No placeholder/dynamic content, no integration system, no Composer package, PHP 8.0+ only, small user base
- **Risk:** Actively maintained. If they add a templating system, they become the closest competitor.

### WP Admin Pages PRO

Premium-only page builder approach ($79/year or $149 lifetime).

- **Strengths:** Elementor/Beaver Builder integration, sandboxed PHP execution, CDN asset loading
- **Weaknesses:** Not documentation-focused (generic custom admin pages), no syncing, no placeholders, no developer ecosystem
- **Verdict:** Different market segment — competes on visual editing, not structured guides.

### WP Adminify / Ultimate Dashboard

All-in-one dashboard customization suites with help docs as a minor feature.

- **Verdict:** Indirect competition. They solve a broader problem (admin UI customization) and don't focus on structured documentation.

## Feature Comparison Matrix

| Feature | Admin Guide | WP Help | Admin Help Docs | WP Admin Pages PRO |
|---|---|---|---|---|
| CPT-based storage | Yes | Yes | Yes | No |
| Builder UI | Drag & drop (nav-menu style) | WP editor + reorder | WP editor + folders | Page builders |
| Dynamic placeholders | Yes (registry + callbacks) | No | No | No |
| JSON integrations | Yes (auto-detect) | No | No | No |
| Contextual placement | [Planned](ROADMAP.md) | No | Yes (CSS selector) | Yes (any menu) |
| Multisite sync | [Planned](ROADMAP.md) | Yes | Yes (import/feed) | No |
| Import / Export | Yes (JSON) | Sync only | Yes (JSON) | No |
| Composer package | Yes | No | No | No |
| Prefix isolation | Yes (multi-instance) | No | No | No |
| HTML/MD snapshots | Yes | No | No | No |
| White-label | [Planned](ROADMAP.md) | Rename only | Yes (logo, colors) | Yes |
| Gutenberg support | No (WYSIWYG) | Yes | Yes | Yes (page builders) |
| Role-based access | [Planned](ROADMAP.md) | Filterable caps | Yes (per-doc) | Yes |
| Status checks | Yes (AJAX) | No | No | No |
| Translations | i18n ready | 20 locales | 2 locales | N/A |
| PHP requirement | 7.4+ | N/A | 8.0+ | N/A |

## Gaps to Address

| Gap | Who has it | Priority | Notes |
|---|---|---|---|
| Contextual placement | Admin Help Docs | **High** | Biggest UX differentiator — show guide content on specific admin screens |
| White-label / branding | Admin Help Docs, WP Adminify | **Medium** | Agency must-have: logo, colors, CSS override |
| Multisite sync | WP Help | **Medium** | WP Help's core selling point for 13 years |
| Per-doc role access | Admin Help Docs | **Medium** | Per-tab capability restrictions |
| Dashboard widget | Admin Help Docs, WP Adminify | Low | Summary widget or full dashboard replacement |
| Gutenberg editor | WP Help, Admin Help Docs | Low | WYSIWYG is sufficient for guide content |
| Auto-TOC | Admin Help Docs | Low | Simple to implement from existing HTML |
| Built-in search | Admin Help Docs | Low | JS filter on the frontend |
