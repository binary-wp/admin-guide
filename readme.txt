=== Admin Guide Builder ===
Contributors: binarywp
Tags: admin guide, documentation, client handoff, help, editor guide
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write a guide for the people who edit your site, right in wp-admin. Placeholders fill it with live facts about the site.

== Description ==

When you hand a site over, the people who run it need to know how it works: which content types exist, what each one is for, who may edit what, and which plugins handle forms, shop or newsletter. That knowledge usually ends up in a PDF that is out of date in a month.

Admin Guide Builder keeps that guide inside wp-admin, where editors work. You write it once. Placeholders such as `{{wp_content_types_table}}` fill in the parts WordPress already knows. When you rebuild the guide, those parts are read from the site again.

= What you get =

* **A guide page for editors.** Tabs and sub-tabs, read-only, under Tools → Admin Guide.
* **A builder for you.** Arrange tabs by drag and drop, nav-menu style. Edit each tab in the classic editor, where placeholders show as pills.
* **Placeholders that read the site.** Content types with their taxonomies, other post types, post categories, which editor (block or classic) is in use, settings screens, connected services, and links to Menus, Widgets and the Customizer.
* **Integrations.** Ready-made tabs and placeholders for WooCommerce, WooCommerce Subscriptions and Memberships, The Events Calendar, Elementor, Astra, PublishPress Authors and Future, SearchWP, WP Mail SMTP, Square and others. Each integration switches on only when its plugin is active.
* **Service status.** For integrations that connect to an outside service, the builder shows whether the connection is configured.
* **Import and export.** The whole guide is one JSON file, so you can reuse it across sites or keep it in version control.

= For developers =

The guide is also a Composer package, `binary-wp/admin-guide`. A theme or plugin can embed one or more guides under its own prefix and menu. Public actions and filters let other plugins:

* register placeholders and integrations
* add admin screens and Viewer toolbar buttons
* add cards to Settings & Tools
* supply tab content from elsewhere
* react when the guide changes, and trigger a rebuild

The full list is in [HOOKS.md](https://github.com/binary-wp/admin-guide/blob/main/HOOKS.md). Development happens on [GitHub](https://github.com/binary-wp/admin-guide).

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the folder to `/wp-content/plugins/`.
2. Activate it.
3. Go to Tools → Guide Builder and add your first tabs. The “Add System Guide” box offers ready-made tabs for WordPress and the active integrations.
4. Editors read the result under Tools → Admin Guide.

== Frequently Asked Questions ==

= Who can see the guide? =

Users with the `manage_options` capability (administrators) by default. Developers can change it with the `admin_guide_builder/capability` filter.

= Do placeholders update by themselves? =

They update whenever the guide is rebuilt: after every edit in the builder, when you click Regenerate on the guide page, or when a plugin calls the `admin_guide_builder/regenerate` action. Between rebuilds the guide shows the last snapshot.

= Where are the generated files stored? =

In a folder under `wp-content/uploads/`. Its name includes a hash unique to the site, and it contains files that block direct access on Apache. Deleting the plugin removes the folder.

= Does the plugin contact external services? =

No. It makes no remote requests and loads no external scripts, fonts or images. A few bundled integrations contain plain links to the documentation of the plugin they cover (for example PublishPress or WordPress.org). Those links open only when you click them.

= What happens to my guide when I delete the plugin? =

Deleting the plugin from the Plugins screen removes the guide pages, the plugin's options and the generated files. Deactivating keeps everything. Export the guide first if you want to keep it.

== Screenshots ==

1. The guide as editors see it: tabs, sub-tabs and live tables.
2. The builder: drag-and-drop tab structure and the “Add System Guide” box.
3. Editing a tab: placeholders appear as pills.

== Changelog ==

= 0.13.0 =
* New: public hook API for extensions. Plugins can now add screens, Viewer buttons and settings cards, supply tab content, and react to guide changes. The old `guide_builder/*` hooks still work and are marked deprecated.
* New: translations now load. A WordPress.org language pack is used first, then the bundled files (Czech included).
* Changed: renamed to Admin Guide Builder. The text domain is `admin-guide-builder`.
* Changed: the standalone plugin stores generated files under `uploads/`, protected against direct access.
* Changed: the license is now GPLv2 or later.
* Changed: Site Compare moved to its own package, `binary-wp/site-compare`.
* Security: nonce checks run in every handler. Input is unslashed and sanitized. Imports are validated as genuine JSON uploads of sensible size.
* Fix: the “User Guide” links in the content-types table pointed to a non-existent page.
* Fix: the standalone plugin's builder and guide page no longer share one menu label.

== Upgrade Notice ==

= 0.13.0 =
First WordPress.org release. Generated files move to `uploads/`, and they are rebuilt automatically the first time the guide is opened.
