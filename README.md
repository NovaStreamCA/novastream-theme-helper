# NovaStream Theme Helper

NovaStream Theme Helper contains reusable WordPress behavior shared by
NovaStream sites. It is deliberately independent of theme presentation so a
fix can be deployed across the fleet by updating one plugin.

## Requirements

- WordPress 6.5 or newer
- PHP 8.0 or newer
- ACF Pro for the Site Options page and featured-image crop interface
- ACF Image Aspect Ratio Crop for strict-ratio featured-image cropping

The plugin degrades safely when optional integrations such as ACF, WPForms,
Yoast, or WooCommerce are unavailable.

## Modules

- `includes/media/`: upload optimization, WebP output, social fallbacks,
  featured-image cropping, and video URL parsing
- `includes/admin/`: dashboard branding and native admin-menu organization
- `includes/content/`: shared search, privacy, comment, and registration policy
- `includes/integrations/`: analytics, WPForms, and Yoast integration
- `includes/acf/`: stable Site Options page registration
- `includes/seo/`: ACF SEO settings, per-entry overrides, and Open Graph output
- `includes/updates/`: updates from public GitHub Releases

The bootstrap delays module loading until `after_setup_theme` priority `100`.
This permits rolling deployments alongside older themes that still declare the
same public functions.

## Site-specific ACF fields

The plugin owns the stable **Site Options page**, while the active theme owns
the fields placed on it. A site or child theme can add any fields it needs
without changing this plugin.

The preferred workflow is:

1. Create a field group in ACF.
2. Set its location rule to **Options Page is equal to Site Options**.
3. Save the group into the site's or child theme's `acf-json/` directory.

This keeps site-specific schema with the site implementation while the stable
options-page slug remains `general-settings`.

Fields can also be registered from a theme in PHP:

```php
add_action('acf/init', function () {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_example_site_options',
        'title' => 'Example Site Options',
        'fields' => array(
            array(
                'key' => 'field_example_phone',
                'label' => 'Contact phone',
                'name' => 'contact_phone',
                'type' => 'text',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'general-settings',
                ),
            ),
        ),
    ));
}, 30);
```

The page definition can be adjusted before registration:

```php
add_filter('novastream_site_options_page_args', function ($args) {
    $args['capability'] = 'manage_options';
    return $args;
});
```

## Extension API

Site and child themes should customize behavior with hooks rather than copying
plugin modules. Important filters include:

- `novastream_theme_helper_modules`
- `novastream_site_options_page_args`
- `novastream_disable_comments`
- `novastream_redirect_registration`
- `novastream_google_analytics_codes`
- `novastream_google_analytics_enabled`
- `novastream_admin_help_logo_url`
- `novastream_admin_menu_sections`
- `novastream_admin_menu_group`
- `novastream_admin_menu_rank`
- `novastream_featured_image_crop_enabled`
- `novastream_featured_image_crop_ratio`
- `novastream_seo_enabled`
- `novastream_seo_metadata`
- `novastream_seo_options_page_args`
- `novastream_seo_field_groups`

`novastream_site_options_page_registered` fires after the ACF page is created.
Standard WordPress, ACF, WPForms, and Yoast hooks used by the modules remain
available as well. See `readme.txt` and the inline PHPDoc for configuration
constants and lower-level media hooks.

### SEO migration compatibility

Theme Helper owns the functionality previously shipped as NovaStream SEO. The
following database and extension contracts remain unchanged:

- the `novastream-seo-options` admin page slug;
- the `seo_locations`, `seo_title`, `seo_description`, `seo_image`, and
  `default_seo_*` field names;
- the original ACF field and group keys;
- `novastream_seo_social_image` and `novastream_seo_image_mime_types`.

The standalone `novastream-seo` plugin should be deactivated after Theme Helper
1.1.0 or newer is active. During a rolling deployment, Theme Helper detects its
legacy `novastream_seo()` function and does not load a duplicate module.

## GitHub updates

Theme Helper provides the release updater used by both NovaStream helper
plugins. No per-site configuration is required while these repositories are
public:

- `NovaStreamCA/novastream-theme-helper`
- `NovaStreamCA/novastream-woocommerce-theme-helper`

For each release:

1. Update the `Version` header and matching version constant.
2. Commit and push the release code.
3. Create a GitHub release whose tag is the same semantic version, with or
   without a leading `v` (for example `v1.2.0`).
4. In WordPress, use **Dashboard > Updates > Check again**.

The updater checks GitHub's public Releases API, downloads the generated source
archive, and restores the stable plugin directory name during installation.
Release notes appear as the plugin changelog. These filters support downstream
configuration:

- `novastream_github_plugin_update_args`
- `novastream_github_release_data`

## Validation

Lint all plugin PHP files from the WordPress root:

```bash
find wp-content/plugins/novastream-theme-helper -name '*.php' -print0 \
  | xargs -0 -n1 php -l
```

Then activate and inspect the plugin:

```bash
wp plugin activate novastream-theme-helper
wp plugin status novastream-theme-helper
```

## Release policy

Public `novastream_*` functions, hooks, constants, option keys, ACF keys, and
the `general-settings` slug are compatibility APIs. Changes must remain
backwards compatible across rolling theme and plugin deployments.
