<?php
/**
 * ACF-backed Featured Image crop infrastructure.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Report the add-on required by the replacement Featured Image field.
 */
function novastream_acf_image_crop_notice() {
	if (
		! function_exists( 'acf_get_field_type' )
		|| acf_get_field_type( 'image_aspect_ratio_crop' )
		|| ! current_user_can( 'activate_plugins' )
	) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>'
		. esc_html__( 'NovaStream Theme Helper requires the ACF Image Aspect Ratio Crop field add-on.', 'novastream-theme-helper' )
		. '</strong> '
		. esc_html__( 'Install and activate the add-on to use the crop-enabled Featured Image field.', 'novastream-theme-helper' )
		. '</p></div>';
}
add_action( 'admin_notices', 'novastream_acf_image_crop_notice' );

if ( ! defined( 'NOVASTREAM_FEATURED_IMAGE_CROP_ENABLED' ) ) {
	define( 'NOVASTREAM_FEATURED_IMAGE_CROP_ENABLED', true );
}

if ( ! defined( 'NOVASTREAM_FEATURED_IMAGE_CROP_WIDTH' ) ) {
	define( 'NOVASTREAM_FEATURED_IMAGE_CROP_WIDTH', 16 );
}

if ( ! defined( 'NOVASTREAM_FEATURED_IMAGE_CROP_HEIGHT' ) ) {
	define( 'NOVASTREAM_FEATURED_IMAGE_CROP_HEIGHT', 9 );
}

/**
 * Determine whether the crop-enabled Featured Image UI is active.
 *
 * @param string $post_type Optional post type being checked.
 * @return bool
 */
function novastream_featured_image_crop_enabled( $post_type = '' ) {
	return (bool) apply_filters(
		'novastream_featured_image_crop_enabled',
		NOVASTREAM_FEATURED_IMAGE_CROP_ENABLED,
		$post_type
	);
}

/**
 * Determine whether ACF and its crop field type can provide the replacement UI.
 *
 * @return bool
 */
function novastream_featured_image_crop_available() {
	return novastream_featured_image_crop_enabled()
		&& function_exists( 'acf_add_local_field_group' )
		&& function_exists( 'acf_get_field_type' )
		&& (bool) acf_get_field_type( 'image_aspect_ratio_crop' );
}

/**
 * Get the strict Featured Image crop ratio.
 *
 * @return int[] Ratio containing width and height.
 */
function novastream_featured_image_crop_ratio() {
	$ratio = apply_filters(
		'novastream_featured_image_crop_ratio',
		array(
			'width'  => NOVASTREAM_FEATURED_IMAGE_CROP_WIDTH,
			'height' => NOVASTREAM_FEATURED_IMAGE_CROP_HEIGHT,
		)
	);

	$width  = max( 1, absint( $ratio['width'] ?? 16 ) );
	$height = max( 1, absint( $ratio['height'] ?? 9 ) );

	return array(
		'width'  => $width,
		'height' => $height,
	);
}

/**
 * Register the crop-enabled Featured Image field for post types that support
 * WordPress thumbnails.
 */
function novastream_register_featured_image_crop_field() {
	if (
		! novastream_featured_image_crop_available()
	) {
		return;
	}

	$locations = array();

	foreach ( get_post_types( array( 'show_ui' => true ) ) as $post_type ) {
		if ( 'attachment' === $post_type || ! post_type_supports( $post_type, 'thumbnail' ) ) {
			continue;
		}

		if ( ! novastream_featured_image_crop_enabled( $post_type ) ) {
			continue;
		}

		$locations[] = array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $post_type,
			),
		);
	}

	if ( ! $locations ) {
		return;
	}

	$ratio = novastream_featured_image_crop_ratio();

	acf_add_local_field_group(
		array(
			'key'                   => 'group_novastream_featured_image_crop',
			'title'                 => __( 'Featured Image', 'novastream-theme-helper' ),
			'fields'                => array(
				array(
					'key'                 => 'field_novastream_featured_image_crop',
					'label'               => __( 'Featured Image', 'novastream-theme-helper' ),
					'name'                => 'novastream_featured_image_crop',
					'type'                => 'image_aspect_ratio_crop',
					'instructions'        => sprintf(
						/* translators: 1: crop width, 2: crop height. */
						__( 'Select and crop an image to a strict %1$d:%2$d aspect ratio.', 'novastream-theme-helper' ),
						$ratio['width'],
						$ratio['height']
					),
					'required'            => 0,
					'crop_type'           => 'aspect_ratio',
					'aspect_ratio_width'  => $ratio['width'],
					'aspect_ratio_height' => $ratio['height'],
					'return_format'       => 'id',
					'preview_size'        => 'medium',
					'library'             => 'all',
					'mime_types'          => 'jpg,jpeg,png,webp',
				),
			),
			'location'              => $locations,
			'position'              => 'side',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'show_in_rest'          => 0,
		)
	);
}
add_action( 'acf/init', 'novastream_register_featured_image_crop_field', 20 );

/**
 * Show the current core Featured Image in the ACF replacement field.
 *
 * @param mixed      $value   Stored ACF value.
 * @param int|string $post_id Current post ID.
 * @return int|false
 */
function novastream_load_featured_image_crop_value( $value, $post_id ) {
	unset( $value );

	return get_post_thumbnail_id( $post_id );
}
add_filter(
	'acf/load_value/key=field_novastream_featured_image_crop',
	'novastream_load_featured_image_crop_value',
	10,
	2
);

/**
 * Synchronize the crop field to WordPress's standard Featured Image metadata.
 *
 * @param int|string $post_id Saved post ID.
 */
function novastream_sync_featured_image_crop( $post_id ) {
	if (
		! is_numeric( $post_id )
		|| wp_is_post_revision( $post_id )
		|| wp_is_post_autosave( $post_id )
	) {
		return;
	}

	$post_type = get_post_type( $post_id );

	if ( ! $post_type || ! novastream_featured_image_crop_enabled( $post_type ) ) {
		return;
	}

	$field_key = 'field_novastream_featured_image_crop';
	$acf_input = isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? wp_unslash( $_POST['acf'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: array();

	if ( ! array_key_exists( $field_key, $acf_input ) ) {
		return;
	}

	$attachment_id = absint( $acf_input[ $field_key ] );

	if ( $attachment_id ) {
		set_post_thumbnail( $post_id, $attachment_id );
	} else {
		delete_post_thumbnail( $post_id );
	}
}
add_action( 'acf/save_post', 'novastream_sync_featured_image_crop', 20 );

/**
 * Hide the duplicate core Featured Image panel while the ACF crop UI is active.
 */
function novastream_remove_core_featured_image_metabox() {
	if ( ! novastream_featured_image_crop_available() ) {
		return;
	}

	foreach ( get_post_types( array( 'show_ui' => true ) ) as $post_type ) {
		if (
			post_type_supports( $post_type, 'thumbnail' )
			&& novastream_featured_image_crop_enabled( $post_type )
		) {
			remove_meta_box( 'postimagediv', $post_type, 'side' );
		}
	}
}
add_action( 'add_meta_boxes', 'novastream_remove_core_featured_image_metabox', PHP_INT_MAX );
