<?php
/**
 * Shared SEO and social-sharing metadata.
 *
 * This module preserves the public hooks, field keys, option names, and page
 * slug from the standalone NovaStream SEO plugin.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NOVASTREAM_SEO_IMAGE_WIDTH  = 1200;
const NOVASTREAM_SEO_IMAGE_HEIGHT = 630;

require_once __DIR__ . '/json-ld.php';

/**
 * Register the hard-cropped fallback used by featured and taxonomy images.
 */
function novastream_register_seo_image_size() {
	add_image_size(
		'novastream-seo',
		NOVASTREAM_SEO_IMAGE_WIDTH,
		NOVASTREAM_SEO_IMAGE_HEIGHT,
		true
	);
}

/**
 * Determine whether ACF can provide the SEO settings interface.
 *
 * @return bool
 */
function novastream_seo_has_acf_pro() {
	return function_exists( 'acf_add_local_field_group' )
		&& function_exists( 'acf_add_options_page' )
		&& function_exists( 'get_field' );
}

/**
 * Populate the post-type selector used by SEO Options.
 *
 * @param array<string, mixed> $field ACF field configuration.
 * @return array<string, mixed>
 */
function acf_load_post_types( $field ) {
	$field['choices'] = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();

	foreach ( get_post_types( array( 'show_in_nav_menus' => true ), 'objects' ) as $post_type ) {
		$field['choices'][ $post_type->name ] = $post_type->labels->singular_name;
	}

	return $field;
}

/**
 * Register the legacy SEO ACF location-rule category.
 */
function acf_location_rules_types( $choices ) {
	$choices['Basic']['seo'] = __( 'SEO', 'novastream-theme-helper' );

	return $choices;
}

/**
 * Register the legacy SEO ACF location-rule operator.
 */
function acf_location_rules_operators( $choices ) {
	$choices['='] = __( 'is selected', 'novastream-theme-helper' );

	return $choices;
}

/**
 * Register the legacy SEO ACF location-rule value.
 */
function acf_location_rule_values_seo( $choices ) {
	$choices['true'] = __( 'True', 'novastream-theme-helper' );

	return $choices;
}

/**
 * Show per-entry SEO fields for post types selected in SEO Options.
 */
function acf_location_rule_match_seo( $match, $rule, $options, $field_group ) {
	unset( $match, $rule, $field_group );

	$locations = get_field( 'seo_locations', 'option' );
	$post_type = isset( $options['post_type'] ) ? $options['post_type'] : get_post_type();

	return is_array( $locations ) && in_array( $post_type, $locations, true );
}

/**
 * Register the SEO options page and stable ACF field groups.
 */
function novastream_register_seo_fields() {
	if ( ! novastream_seo_has_acf_pro() ) {
		return;
	}

	$page_args = apply_filters(
		'novastream_seo_options_page_args',
		array(
			'page_title' => __( 'SEO & Social Sharing Options', 'novastream-theme-helper' ),
			'menu_title' => __( 'SEO Options', 'novastream-theme-helper' ),
			'menu_slug'  => 'novastream-seo-options',
			'capability' => 'edit_posts',
			'redirect'   => false,
			'icon_url'   => 'dashicons-share',
		)
	);
	acf_add_options_page( $page_args );

	$groups = array(
		array(
			'key'      => 'group_62ed13bfc141b',
			'title'    => __( 'SEO & Social Sharing Configuration', 'novastream-theme-helper' ),
			'fields'   => array(
				array(
					'key'       => 'field_628e39fe55b08',
					'label'     => __( 'Social Sharing Defaults', 'novastream-theme-helper' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
				),
				array(
					'key'                 => 'field_628e3a2755b09',
					'label'               => __( 'Image', 'novastream-theme-helper' ),
					'name'                => 'default_seo_image',
					'type'                => 'image_aspect_ratio_crop',
					'instructions'        => __( 'Select an image at least 1200 × 630 pixels. It will be hard-cropped to exactly 1200 × 630 pixels for social sharing.', 'novastream-theme-helper' ),
					'crop_type'           => 'pixel_size',
					'aspect_ratio_width'  => NOVASTREAM_SEO_IMAGE_WIDTH,
					'aspect_ratio_height' => NOVASTREAM_SEO_IMAGE_HEIGHT,
					'min_width'           => NOVASTREAM_SEO_IMAGE_WIDTH,
					'min_height'          => NOVASTREAM_SEO_IMAGE_HEIGHT,
					'return_format'       => 'url',
					'preview_size'        => 'medium',
					'library'             => 'all',
					'mime_types'          => apply_filters( 'novastream_seo_image_mime_types', '' ),
				),
				array(
					'key'          => 'field_628e3a3b55b0a',
					'label'        => __( 'Title', 'novastream-theme-helper' ),
					'name'         => 'default_seo_title',
					'type'         => 'text',
					'instructions' => __( 'A length of approximately 40–60 characters is recommended.', 'novastream-theme-helper' ),
					'placeholder'  => __( 'Explore', 'novastream-theme-helper' ),
				),
				array(
					'key'          => 'field_628e3a5755b0b',
					'label'        => __( 'Description', 'novastream-theme-helper' ),
					'name'         => 'default_seo_description',
					'type'         => 'textarea',
					'instructions' => __( 'Use one or two short sentences, ideally no more than 200 characters.', 'novastream-theme-helper' ),
					'placeholder'  => __( 'Explore what this website has to offer.', 'novastream-theme-helper' ),
				),
				array(
					'key'       => 'field_628e39fe50001',
					'label'     => __( 'Social Sharing Override Locations', 'novastream-theme-helper' ),
					'name'      => '',
					'type'      => 'tab',
					'placement' => 'top',
				),
				array(
					'key'           => 'field_62ed13c8c8e6b',
					'label'         => __( 'Locations', 'novastream-theme-helper' ),
					'name'          => 'seo_locations',
					'type'          => 'checkbox',
					'instructions'  => __( 'Choose the post types that should receive entry-specific SEO and sharing overrides.', 'novastream-theme-helper' ),
					'choices'       => array(),
					'layout'        => 'vertical',
					'return_format' => 'value',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'novastream-seo-options',
					),
				),
			),
			'position' => 'normal',
			'style'    => 'default',
			'active'   => true,
		),
		array(
			'key'      => 'group_62ec034dd774a',
			'title'    => __( 'SEO/Sharing', 'novastream-theme-helper' ),
			'fields'   => array(
				array(
					'key'      => 'field_62ec0593d7d45',
					'label'    => '',
					'name'     => '',
					'type'     => 'message',
					'message'  => __( 'These fields override the title, description, and image shown in search and social sharing. Defaults are configured under SEO Options.', 'novastream-theme-helper' ),
					'new_lines' => 'wpautop',
				),
				array(
					'key'         => 'field_62ec03ce911b7',
					'label'       => __( 'Title', 'novastream-theme-helper' ),
					'name'        => 'seo_title',
					'type'        => 'text',
					'placeholder' => __( 'Explore', 'novastream-theme-helper' ),
				),
				array(
					'key'         => 'field_62ec05caba705',
					'label'       => __( 'Description', 'novastream-theme-helper' ),
					'name'        => 'seo_description',
					'type'        => 'textarea',
					'placeholder' => __( 'Explore what this page has to offer.', 'novastream-theme-helper' ),
					'rows'        => 2,
				),
				array(
					'key'                 => 'field_62ec05d6ba706',
					'label'               => __( 'Image', 'novastream-theme-helper' ),
					'name'                => 'seo_image',
					'type'                => 'image_aspect_ratio_crop',
					'instructions'        => __( 'Select an image at least 1200 × 630 pixels. It will be hard-cropped to exactly 1200 × 630 pixels for social sharing.', 'novastream-theme-helper' ),
					'crop_type'           => 'pixel_size',
					'aspect_ratio_width'  => NOVASTREAM_SEO_IMAGE_WIDTH,
					'aspect_ratio_height' => NOVASTREAM_SEO_IMAGE_HEIGHT,
					'min_width'           => NOVASTREAM_SEO_IMAGE_WIDTH,
					'min_height'          => NOVASTREAM_SEO_IMAGE_HEIGHT,
					'return_format'       => 'url',
					'preview_size'        => 'medium',
					'library'             => 'all',
					'mime_types'          => apply_filters( 'novastream_seo_image_mime_types', '' ),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'seo',
						'operator' => '=',
						'value'    => 'true',
					),
				),
			),
			'position' => 'side',
			'style'    => 'standard',
			'active'   => true,
		),
	);

	foreach ( (array) apply_filters( 'novastream_seo_field_groups', $groups ) as $group ) {
		acf_add_local_field_group( $group );
	}
}

/**
 * Preserve the Settings submenu exposed by the standalone SEO plugin.
 */
function novastream_seo_admin_menu() {
	if ( ! novastream_seo_has_acf_pro() ) {
		return;
	}

	add_submenu_page(
		'novastream-seo-options',
		__( 'Settings', 'novastream-theme-helper' ),
		__( 'Settings', 'novastream-theme-helper' ),
		'edit_posts',
		'novastream-seo-options',
		'__return_empty_string',
		1
	);
}

/**
 * Add a direct SEO Settings link to Theme Helper's plugin row.
 *
 * @param string[] $links Existing plugin action links.
 * @return string[]
 */
function novastream_seo_plugin_action_links( $links ) {
	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=novastream-seo-options' ) ),
			esc_html__( 'SEO Settings', 'novastream-theme-helper' )
		)
	);

	return $links;
}

/**
 * Safely read an ACF value when ACF is available.
 */
function novastream_seo_get_field( $name, $post_id = false ) {
	return function_exists( 'get_field' ) ? get_field( $name, $post_id ) : null;
}

/**
 * Resolve the pixel dimensions of the exact social image URL being emitted.
 *
 * @param string $image_url Public image URL.
 * @return array{width:int,height:int}
 */
function novastream_get_seo_image_dimensions( $image_url ) {
	$dimensions = array(
		'width'  => 0,
		'height' => 0,
	);
	$image_url  = (string) $image_url;

	if ( '' === $image_url ) {
		return $dimensions;
	}

	$uploads  = wp_get_upload_dir();
	$base_url = isset( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) : '';
	$base_dir = isset( $uploads['basedir'] ) ? trailingslashit( $uploads['basedir'] ) : '';

	if ( $base_url && $base_dir && str_starts_with( $image_url, $base_url ) ) {
		$relative_path = rawurldecode( substr( $image_url, strlen( $base_url ) ) );
		$image_path    = path_join( $base_dir, $relative_path );
		$image_size    = is_file( $image_path ) ? wp_getimagesize( $image_path ) : false;

		if ( $image_size ) {
			$dimensions['width']  = (int) $image_size[0];
			$dimensions['height'] = (int) $image_size[1];
		}
	}

	$dimensions = (array) apply_filters( 'novastream_seo_image_dimensions', $dimensions, $image_url );

	return array(
		'width'  => absint( $dimensions['width'] ?? 0 ),
		'height' => absint( $dimensions['height'] ?? 0 ),
	);
}

/**
 * Build the metadata values for the current request.
 *
 * @return array<string, mixed>
 */
function novastream_get_seo_metadata() {
	$post_id = ( is_singular() || is_home() ) ? get_queried_object_id() : 0;
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$post_id = wc_get_page_id( 'shop' );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
	}

	$title       = $post_id ? novastream_seo_get_field( 'seo_title', $post_id ) : '';
	$description = $post_id ? novastream_seo_get_field( 'seo_description', $post_id ) : '';
	$image       = $post_id ? novastream_seo_get_field( 'seo_image', $post_id ) : '';
	$url         = $post_id ? get_permalink( $post_id ) : '';

	if ( ! $title ) {
		$title = $post ? get_the_title( $post_id ) : novastream_seo_get_field( 'default_seo_title', 'option' );
	}

	if ( ! $description ) {
		$description = $post ? get_the_excerpt( $post_id ) : '';
	}

	if ( ! $description ) {
		$description = novastream_seo_get_field( 'default_seo_description', 'option' );
	}

	if ( ! $image && $post_id && has_post_thumbnail( $post_id ) ) {
		$image = get_the_post_thumbnail_url( $post_id, 'novastream-seo' );
	}

	if ( ! $image ) {
		$image = novastream_seo_get_field( 'default_seo_image', 'option' );
	}

	if ( is_front_page() ) {
		$title = get_bloginfo( 'name' );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$title       = $term->name ?: $title;
			$description = $term->description ?: novastream_seo_get_field( 'default_seo_description', 'option' );
			$term_link   = get_term_link( $term );
			$url         = is_wp_error( $term_link ) ? $url : $term_link;

			if ( function_exists( 'is_product_category' ) && is_product_category() ) {
				$thumbnail = get_term_meta( $term->term_id, 'thumbnail_id', true );
				$image     = $thumbnail ? wp_get_attachment_image_url( (int) $thumbnail, 'novastream-seo' ) : novastream_seo_get_field( 'default_seo_image', 'option' );
			}
		}
	}

	$image      = apply_filters( 'novastream_seo_social_image', $image, $post_id );
	$dimensions = novastream_get_seo_image_dimensions( $image );

	$metadata = array(
		'title'        => wp_strip_all_tags( (string) $title ),
		'description'  => wp_strip_all_tags( (string) $description ),
		'url'          => $url,
		'site_name'    => get_bloginfo( 'name' ),
		'image'        => $image,
		'image_width'  => $dimensions['width'],
		'image_height' => $dimensions['height'],
		'post_id'      => $post_id,
	);

	return (array) apply_filters( 'novastream_seo_metadata', $metadata );
}

/**
 * Print description and Open Graph metadata for the current request.
 */
function novastream_seo() {
	if ( (bool) apply_filters( 'novastream_seo_enabled', true ) === false ) {
		return;
	}

	$metadata     = novastream_get_seo_metadata();
	$twitter_card = $metadata['image'] ? 'summary_large_image' : 'summary';
	$twitter_card = apply_filters( 'novastream_seo_twitter_card', $twitter_card, $metadata );

	printf( '<meta name="twitter:card" content="%s">' . "\n", esc_attr( $twitter_card ) );

	if ( '' !== $metadata['description'] ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $metadata['description'] ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $metadata['description'] ) );
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $metadata['description'] ) );
	}

	if ( '' !== $metadata['title'] ) {
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $metadata['title'] ) );
		printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $metadata['title'] ) );
	}

	if ( $metadata['url'] ) {
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $metadata['url'] ) );
	}

	if ( '' !== $metadata['site_name'] ) {
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( $metadata['site_name'] ) );
	}

	if ( $metadata['image'] ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $metadata['image'] ) );

		if ( ! empty( $metadata['image_width'] ) && ! empty( $metadata['image_height'] ) ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n", absint( $metadata['image_width'] ) );
			printf( '<meta property="og:image:height" content="%d">' . "\n", absint( $metadata['image_height'] ) );
		}

		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $metadata['image'] ) );
	}

	novastream_seo_render_json_ld( $metadata );
}

/**
 * Enqueue the legacy SEO editor styling from Theme Helper.
 */
function seo_style() {
	wp_enqueue_style(
		'seo-css',
		plugins_url( 'assets/css/seo.css', NOVASTREAM_THEME_HELPER_FILE ),
		array(),
		NOVASTREAM_THEME_HELPER_VERSION
	);
}

add_filter( 'acf/load_field/name=seo_locations', 'acf_load_post_types' );
add_filter( 'acf/location/rule_types', 'acf_location_rules_types' );
add_filter( 'acf/location/rule_operators', 'acf_location_rules_operators' );
add_filter( 'acf/location/rule_values/seo', 'acf_location_rule_values_seo' );
add_filter( 'acf/location/rule_match/seo', 'acf_location_rule_match_seo', 10, 4 );
add_action( 'init', 'novastream_register_seo_image_size' );
add_action( 'acf/init', 'novastream_register_seo_fields', 20 );
add_action( 'admin_menu', 'novastream_seo_admin_menu', 20 );
add_action( 'admin_enqueue_scripts', 'seo_style' );
add_action( 'wp_head', 'novastream_seo', 5 );
add_filter( 'plugin_action_links_' . plugin_basename( NOVASTREAM_THEME_HELPER_FILE ), 'novastream_seo_plugin_action_links' );
