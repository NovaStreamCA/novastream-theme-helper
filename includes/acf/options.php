<?php
/**
 * Shared ACF Site Options registration.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the stable Site Options page unless an older theme already did so.
 */
function novastream_register_site_options_page() {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	$pages = function_exists( 'acf_get_options_pages' ) ? acf_get_options_pages() : array();

	foreach ( is_array( $pages ) ? $pages : array() as $page ) {
		if ( 'general-settings' === ( $page['menu_slug'] ?? '' ) ) {
			return;
		}
	}

	$args = apply_filters(
		'novastream_site_options_page_args',
		array(
			'page_title' => __( 'Site Options', 'novastream-theme-helper' ),
			'menu_title' => __( 'Site Options', 'novastream-theme-helper' ),
			'menu_slug'  => 'general-settings',
			'capability' => 'edit_posts',
			'redirect'   => false,
		)
	);

	$page = acf_add_options_page( $args );

	/**
	 * Fires after the shared Site Options page has been registered.
	 *
	 * @param array|false $page Registered ACF options-page settings.
	 */
	do_action( 'novastream_site_options_page_registered', $page );
}
add_action( 'acf/init', 'novastream_register_site_options_page', 20 );
