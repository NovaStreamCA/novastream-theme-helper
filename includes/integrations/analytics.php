<?php
/**
 * Shared Google Analytics option handling.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return configured measurement IDs from the existing ACF options repeater.
 *
 * @return string[]
 */
function novastream_get_google_analytics_codes() {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$rows  = get_field( 'ga_codes', 'option' );
	$codes = array();

	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$code = isset( $row['ga_code'] ) ? trim( (string) $row['ga_code'] ) : '';

		if ( $code ) {
			$codes[] = $code;
		}
	}

	return array_values(
		array_unique(
			(array) apply_filters( 'novastream_google_analytics_codes', $codes )
		)
	);
}

/**
 * Determine whether analytics should run for the current request.
 *
 * @return bool
 */
function novastream_google_analytics_enabled() {
	$current_host = isset( $_SERVER['HTTP_HOST'] )
		? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) )
		: '';
	// NovaStream-owned development and staging hosts intentionally do not track.
	$enabled = $current_host && ! str_contains( $current_host, 'novastream' );

	return (bool) apply_filters( 'novastream_google_analytics_enabled', $enabled, $current_host );
}

/**
 * Enqueue gtag and configure every measurement ID supplied in Site Options.
 */
function novastream_output_google_analytics() {
	$codes = novastream_get_google_analytics_codes();

	if ( ! $codes || ! novastream_google_analytics_enabled() ) {
		return;
	}

	wp_enqueue_script(
		'novastream-google-analytics',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $codes[0] ),
		array(),
		null,
		false
	);

	$configuration = "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());";

	foreach ( $codes as $code ) {
		$configuration .= 'gtag(\'config\',' . wp_json_encode( $code ) . ');';
	}

	wp_add_inline_script( 'novastream-google-analytics', $configuration, 'after' );
}
add_action( 'wp_enqueue_scripts', 'novastream_output_google_analytics', 20 );

/**
 * Warn administrators when a production hostname has no measurement ID.
 */
function novastream_google_analytics_admin_notice() {
	$current_host = isset( $_SERVER['HTTP_HOST'] )
		? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) )
		: '';

	if (
		str_contains( $current_host, '.dev' )
		|| novastream_get_google_analytics_codes()
		|| ! current_user_can( 'manage_options' )
	) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>'
		. esc_html__( 'This site currently does not have a Google Analytics code.', 'novastream-theme-helper' )
		. '</strong></p><p>'
		. esc_html__( 'Please contact support@novastream.ca if you see this notice on your live website.', 'novastream-theme-helper' )
		. '</p></div>';
}
add_action( 'admin_notices', 'novastream_google_analytics_admin_notice' );
