=== NovaStream Theme Helper ===
Contributors: novastream
Tags: platform, media, admin, integrations, acf
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later

Shared platform functionality for NovaStream WordPress sites.

== Description ==

This plugin centralizes NovaStream functionality that should remain available
independently of the active theme, including administration, content policy,
analytics, integrations, and media infrastructure.

* WordPress image size, quality, WebP output, and progressive JPEG settings.
* Media Library optimization status reports.
* ACF-backed strict-ratio Featured Image cropping and thumbnail synchronization.
* Retained JPEG/PNG fallbacks for social metadata when active images are WebP.
* YouTube and Vimeo embed URL parsing helpers.
* Stable Site Options registration for ACF-powered sites.
* Native WordPress admin-menu grouping with extension hooks for child packages.
* Shared dashboard help, administration footer, and login identity behavior.
* Comment/trackback policy, oEmbed privacy, registration redirect, and shared
  search-query behavior.
* WPForms and Yoast compatibility integrations.
* Google Analytics loading from the existing Site Options measurement IDs.

The public NOVASTREAM_IMAGE_* and NOVASTREAM_FEATURED_IMAGE_CROP_* constants,
functions, and filters are retained for backwards compatibility.

The crop-enabled Featured Image interface requires Advanced Custom Fields Pro
and the ACF Image Aspect Ratio Crop field add-on. All other modules continue to
operate when that optional interface is unavailable.

== Configuration ==

Define supported NOVASTREAM_IMAGE_* or NOVASTREAM_FEATURED_IMAGE_CROP_*
constants in wp-config.php before WordPress loads. Existing filters can be used
for per-site and per-post-type configuration.

Important fleet-level filters include `novastream_theme_helper_modules`,
`novastream_disable_comments`, `novastream_google_analytics_enabled`,
`novastream_google_analytics_codes`, `novastream_admin_menu_sections`,
`novastream_site_options_page_args`, and the existing Featured Image crop
filters. Site-specific ACF field groups should target the stable
`general-settings` options-page slug.

== Changelog ==

= 1.0.0 =
* Extract shared platform, media, administration, content-policy, analytics,
  ACF, and third-party integration behavior from the NovaStream parent theme.
