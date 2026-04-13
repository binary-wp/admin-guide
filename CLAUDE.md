# CLAUDE.md

## Project overview

Admin Guide — JSON-driven admin guide builder for WordPress. Composer package `binary-wp/admin-guide`.

Works as standalone plugin, or as Composer dependency in a plugin/theme with per-instance prefix isolation.

## Architecture

- `src/` — PSR-4 namespace `BinaryWP\AdminGuide\`
  - `Plugin.php` — boot + singleton registry
  - `Config.php` — CPT-based tab storage (CRUD, import/export)
  - `Admin.php` — admin pages (builder, editor, viewer)
  - `Generator.php` — renders placeholders → writes `.html` + `.md` snapshots
  - `Integrations.php` — loads JSON integration files from dirs
  - `Placeholders.php` — placeholder registry + resolution
  - `Context.php` — runtime context for placeholder callbacks
- `integrations/` — JSON integration definitions + `functions/*.php` render callbacks
- `assets/` — CSS + JS for builder/editor/viewer admin pages
- `languages/` — i18n (.pot, .po, .mo)

## Conventions

- PHP 7.4+ compatibility required
- WordPress coding standards (snake_case functions, prefixed hooks)
- All hooks fire both generic and prefix-scoped variants
- Integration files are JSON; render functions live in `integrations/functions/{slug}.php`
- Text domain: `binary-wp-admin-guide`

## Build / dev

- No build step; plain PHP + vanilla JS
- `composer install` for dependencies (parsedown-extra, html-to-markdown)
- Translations: `msgfmt` to compile .po → .mo

## Testing

- No automated tests yet

## Version locations

When bumping version, update ALL of these:

1. `admin-guide.php` — plugin header `Version:` line
2. `admin-guide.php` — `Plugin::boot()` `'package_version'` value
3. `README.md` — code example `'package_version'` value
4. `README.md` — xgettext `--package-version` flag
5. `languages/binary-wp-admin-guide.pot` — `Project-Id-Version` header
6. `CHANGELOG.md` — new entry at top
