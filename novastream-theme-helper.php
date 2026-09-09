<?php
/**
 * Plugin Name: NovaStream Theme Helper
 * Plugin URI:  https://novastream.ca
 * Description: Shared platform behavior, integrations, administration, content policy, analytics, and media infrastructure for NovaStream sites.
 * Version:     1.2.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author:      NovaStream
 * Author URI:  https://novastream.ca
 * Text Domain: novastream-theme-helper
 * Update URI:  https://github.com/NovaStreamCA/novastream-theme-helper
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOVASTREAM_THEME_HELPER_VERSION', '1.2.0' );
define( 'NOVASTREAM_THEME_HELPER_FILE', __FILE__ );
define( 'NOVASTREAM_THEME_HELPER_PATH', plugin_dir_path( __FILE__ ) );

require_once NOVASTREAM_THEME_HELPER_PATH . 'includes/updates/github-releases.php';

novastream_register_github_plugin_update(
	array(
		'plugin_file' => NOVASTREAM_THEME_HELPER_FILE,
		'version'     => NOVASTREAM_THEME_HELPER_VERSION,
		'repository'  => 'NovaStreamCA/novastream-theme-helper',
	)
);

/**
 * Load translations.
 */
function novastream_theme_helper_load_textdomain() {
	load_plugin_textdomain(
		'novastream-theme-helper',
		false,
		dirname( plugin_basename( NOVASTREAM_THEME_HELPER_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'novastream_theme_helper_load_textdomain' );

/**
 * Load each module after the active theme for a safe rolling migration.
 *
 * Older NovaStream theme releases declared these public functions directly.
 * Checking a stable function from each module lets the plugin coexist with an
 * older theme during fleet rollouts without fatal redeclaration errors.
 */
function novastream_theme_helper_bootstrap() {
	$modules = array(
		'novastream_image_size_threshold'        => 'includes/media/image-optimization.php',
		'novastream_featured_image_crop_enabled' => 'includes/media/featured-image-crop.php',
		'novastream_get_social_image_fallback'   => 'includes/media/social-images.php',
		'novastream_parse_embed_video'            => 'includes/media/embed-media.php',
		'novastream_get_admin_menu_sections'      => 'includes/admin/menu.php',
		'novastream_customize_dashboard'          => 'includes/admin/branding.php',
		'novastream_get_post_ancestor_id'         => 'includes/content/policy.php',
		'novastream_add_canadian_wpforms_address_scheme' => 'includes/integrations/plugins.php',
		'novastream_google_analytics_admin_notice' => 'includes/integrations/analytics.php',
		'novastream_register_site_options_page'   => 'includes/acf/options.php',
		'novastream_seo'                          => 'includes/seo/seo.php',
	);
	$modules = (array) apply_filters( 'novastream_theme_helper_modules', $modules );

	foreach ( $modules as $legacy_function => $module ) {
		if ( is_string( $legacy_function ) && is_string( $module ) && ! function_exists( $legacy_function ) ) {
			require_once NOVASTREAM_THEME_HELPER_PATH . $module;
		}
	}
}
add_action( 'after_setup_theme', 'novastream_theme_helper_bootstrap', 100 );
