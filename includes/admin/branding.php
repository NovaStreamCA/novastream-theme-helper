<?php
/**
 * Shared WordPress administration and login branding.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove default dashboard widgets and add the NovaStream help widget.
 */
function novastream_customize_dashboard() {
	global $wp_meta_boxes;

	unset(
		$wp_meta_boxes['dashboard']['normal']['core']['dashboard_site_health'],
		$wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now'],
		$wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity'],
		$wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'],
		$wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']
	);

	wp_add_dashboard_widget( 'novastream_help_widget', get_bloginfo( 'name' ), 'novastream_render_help_widget' );
}
add_action( 'wp_dashboard_setup', 'novastream_customize_dashboard' );

/**
 * Render the dashboard help widget.
 */
function novastream_render_help_widget() {
	$default_logo_url = get_template_directory_uri() . '/images/novastream-logo.png';
	$logo_url         = (string) apply_filters( 'novastream_admin_help_logo_url', $default_logo_url );

	echo '<p><strong>' . esc_html__( 'Welcome to your custom-built WordPress website by NovaStream.', 'novastream-theme-helper' ) . '</strong></p>';

	if ( '' !== $logo_url ) {
		echo '<a href="https://novastream.ca/" target="_blank" rel="noopener noreferrer"><img style="max-width:85%;display:table" src="' . esc_url( $logo_url ) . '" alt="NovaStream"></a>';
	}

	echo '<p>' . wp_kses_post( __( 'Need help? Contact us at <a href="mailto:support@novastream.ca">support@novastream.ca</a>', 'novastream-theme-helper' ) ) . '</p>';
}

/**
 * Replace the default administration footer credit.
 */
function novastream_admin_footer_text() {
	echo '<span id="footer-thankyou">' . wp_kses_post( __( 'Developed by <a href="https://novastream.ca/" target="_blank" rel="noopener noreferrer">NovaStream</a>.', 'novastream-theme-helper' ) ) . '</span>';
}
add_filter( 'admin_footer_text', 'novastream_admin_footer_text' );

/**
 * Link the login logo to the site homepage.
 *
 * @return string
 */
function novastream_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'novastream_login_header_url' );

/**
 * Use the site title for the login logo accessible label.
 *
 * @return string
 */
function novastream_login_header_title() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'novastream_login_header_title' );

remove_action( 'welcome_panel', 'wp_welcome_panel' );
