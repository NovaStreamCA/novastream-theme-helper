<?php
/**
 * Optional third-party integrations shared by NovaStream sites.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function novastream_add_canadian_wpforms_address_scheme( $schemes ) {
	$schemes['canada'] = array(
		'label'          => 'Canada',
		'address1_label' => 'Address Line 1',
		'address2_label' => 'Address Line 2',
		'city_label'     => 'City',
		'postal_label'   => 'Postal Code',
		'state_label'    => 'Province',
		'states'         => array(
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NS' => 'Nova Scotia',
			'NT' => 'Northwest Territories',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon',
		),
	);

	return $schemes;
}
add_filter( 'wpforms_address_schemes', 'novastream_add_canadian_wpforms_address_scheme', 10, 1 );

/**
 * Load WPForms assets globally so forms rendered late by Timber still receive
 * every field type's required styles and scripts.
 */
add_filter( 'wpforms_global_assets', '__return_true' );

function novastream_remove_yoast_metabox() {
	remove_meta_box( 'wpseo_meta', 'menu', 'normal' );
}
add_action( 'add_meta_boxes', 'novastream_remove_yoast_metabox', 11 );

function novastream_lower_yoast_metabox_priority() {
	return 'low';
}
add_filter( 'wpseo_metabox_prio', 'novastream_lower_yoast_metabox_priority' );
