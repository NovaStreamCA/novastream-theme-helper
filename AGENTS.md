# NovaStream Theme Helper Agent Guide

Read `README.md` before changing this plugin. This is shared fleet-level
infrastructure: a release may run across many NovaStream sites with different
parent themes, child themes, custom post types, and optional plugins.

## Scope

This plugin owns reusable WordPress behavior that should survive a theme change:

- media and image infrastructure;
- shared administration and dashboard behavior;
- content, privacy, comment, and registration policies;
- stable ACF Site Options registration;
- analytics and generic third-party integrations.
- SEO settings, metadata, and social-sharing integration.

Presentation belongs in themes. Do not move Twig templates, Sass design tokens,
site branding, blocks, WooCommerce templates, or site-specific field groups into
this plugin. WooCommerce-specific shared behavior belongs in the separate
`novastream-woocommerce-helper` plugin.

## Compatibility contract

- Preserve public `novastream_*` functions, filters, actions, constants, option
  keys, ACF keys, and the `general-settings` options-page slug.
- Prefer additive hooks and backwards-compatible defaults.
- Keep module sentinel checks in `novastream-theme-helper.php`; they permit a
  new plugin to coexist temporarily with an older theme during rollout.
- Guard optional dependencies with `function_exists()`, `class_exists()`, or
  `post_type_exists()` checks.
- The parent theme must continue to load if this plugin is inactive, and this
  plugin must not require WooCommerce.

## Extension design

Reusable defaults must be overrideable by a site or child theme through hooks.
Add a narrowly named filter before introducing a site-specific conditional.
Fire an action after registration when downstream code may need the resulting
object or state.

Site Options fields belong in the theme that presents and consumes them. Store
them in that theme's `acf-json/` directory or register them in code attached to
`acf/init`. Target the existing `general-settings` options page; do not add
theme-owned fields to this plugin.

Sanitize external input, escape rendered output, use capability checks for
administrative UI, and use safe redirects. Do not print raw analytics or media
metadata.

## Module layout

- `novastream-theme-helper.php`: metadata, constants, delayed module bootstrap
- `includes/media/`: upload and image behavior
- `includes/admin/`: dashboard and menu behavior
- `includes/content/`: shared request and content policies
- `includes/integrations/`: analytics and optional plugin adapters
- `includes/acf/`: shared ACF page registration only
- `includes/seo/`: SEO options, fields, metadata, and editor assets
- `includes/updates/`: public GitHub Releases update infrastructure

Add new behavior to the narrowest relevant module. Add a new module only when
it has a stable sentinel function and a distinct concern.

Preserve the migrated NovaStream SEO field/group keys, option names,
`novastream-seo-options` slug, public functions, and social-image filters. The
standalone plugin may coexist temporarily during rolling deployments.

The updater is also a compatibility API for dependent NovaStream plugins.
Preserve `novastream_register_github_plugin_update()` and keep repository and
release data filterable. Public updates must work without credentials. Test
release discovery, package download, and source-directory normalization when
the updater changes.

## Validation

For every change:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
wp plugin status novastream-theme-helper
```

Also run the parent and applicable child-theme builds when integration points
change. Test both with and without optional dependencies when practical. For
media changes, preserve transparent PNGs, retained social-image fallbacks, and
Featured Image synchronization.

Do not edit WordPress core or vendor packages. Preserve unrelated workspace
changes. In the handoff, list hooks added or changed and identify any browser,
database, editor, or third-party-plugin QA that remains.
