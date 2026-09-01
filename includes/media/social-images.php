<?php

/**
 * Retained-original social-image integrations.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Use the preserved JPEG or PNG original for social metadata when the active
 * attachment was converted to WebP. Social crawlers do not all support modern
 * image formats consistently.
 *
 * @param string $image_url Social image URL selected by Yoast.
 * @return string
 */
function novastream_get_social_image_fallback($image_url)
{
    if (!$image_url || 'webp' !== strtolower(pathinfo(wp_parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
        return $image_url;
    }

    $attachment_id = attachment_url_to_postid($image_url);

    if (!$attachment_id) {
        return $image_url;
    }

    $original_url = wp_get_original_image_url($attachment_id);

    if (!$original_url) {
        return $image_url;
    }

    $original_type = wp_check_filetype(wp_parse_url($original_url, PHP_URL_PATH));

    return in_array($original_type['type'], array('image/jpeg', 'image/png'), true)
        ? $original_url
        : $image_url;
}
add_filter('wpseo_opengraph_image', 'novastream_get_social_image_fallback');
add_filter('wpseo_twitter_image', 'novastream_get_social_image_fallback');
add_filter('novastream_seo_social_image', 'novastream_get_social_image_fallback');

/**
 * Allow optimized WebP attachments to be selected. A JPEG upload converted by
 * WordPress is represented as WebP in the media modal even though its original
 * JPEG is retained and used by the social-image filter above.
 *
 * @return string
 */
function novastream_seo_image_mime_types()
{
    return 'jpg,jpeg,png,webp';
}
add_filter('novastream_seo_image_mime_types', 'novastream_seo_image_mime_types');

/**
 * Reject native WebP files in NovaStream SEO fields while allowing WebP
 * attachments generated from a retained JPEG or PNG original.
 *
 * @param string[] $errors     Existing attachment validation errors.
 * @param array    $file       Normalized file details from ACF.
 * @param array    $attachment Attachment upload or Media Library details.
 * @param array    $field      ACF field configuration.
 * @param string   $context    Validation context: upload or prepare.
 * @return string[]
 */
function novastream_validate_seo_social_image($errors, $file, $attachment, $field, $context)
{
    if ('webp' !== ($file['type'] ?? '')) {
        return $errors;
    }

    $attachment_id = absint($attachment['id'] ?? $attachment['ID'] ?? 0);

    if (!$attachment_id && !empty($attachment['url'])) {
        $attachment_id = attachment_url_to_postid($attachment['url']);
    }

    $metadata = $attachment_id ? wp_get_attachment_metadata($attachment_id) : array();
    $original = is_array($metadata) ? ($metadata['original_image'] ?? '') : '';
    $original_extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $has_social_original = in_array($original_extension, array('jpg', 'jpeg', 'png'), true)
        && is_file(wp_get_original_image_path($attachment_id));

    if ('upload' === $context || !$has_social_original) {
        $errors['novastream_social_image'] = __(
            'SEO images must be uploaded as JPEG or PNG. WebP is allowed only when WordPress retains a JPEG or PNG original.',
            'novastream-theme-helper'
        );
    }

    return $errors;
}
add_filter('acf/validate_attachment/name=seo_image', 'novastream_validate_seo_social_image', 10, 5);
add_filter('acf/validate_attachment/name=default_seo_image', 'novastream_validate_seo_social_image', 10, 5);
