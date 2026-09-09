<?php

/**
 * Capability-driven image optimization.
 *
 * Uses WordPress image editors (Imagick or GD) and does not require server-side
 * command-line utilities. Define any of the NOVASTREAM_IMAGE_* constants in
 * wp-config.php before WordPress loads to override these starter defaults.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!defined('NOVASTREAM_IMAGE_MAX_DIMENSION')) {
    define('NOVASTREAM_IMAGE_MAX_DIMENSION', 2560);
}

if (!defined('NOVASTREAM_IMAGE_JPEG_QUALITY')) {
    define('NOVASTREAM_IMAGE_JPEG_QUALITY', 82);
}

if (!defined('NOVASTREAM_IMAGE_WEBP_QUALITY')) {
    define('NOVASTREAM_IMAGE_WEBP_QUALITY', 80);
}

if (!defined('NOVASTREAM_IMAGE_CONVERT_JPEG_TO_WEBP')) {
    define('NOVASTREAM_IMAGE_CONVERT_JPEG_TO_WEBP', true);
}

if (!defined('NOVASTREAM_IMAGE_CONVERT_PNG_TO_WEBP')) {
    define('NOVASTREAM_IMAGE_CONVERT_PNG_TO_WEBP', true);
}

/**
 * Limit the working full-size image while retaining WordPress's original-file
 * backup for future edits and size regeneration.
 *
 * @return int|false Maximum width or height in pixels, or false to disable.
 */
function novastream_image_size_threshold()
{
    $threshold = (int) NOVASTREAM_IMAGE_MAX_DIMENSION;

    return $threshold > 0 ? $threshold : false;
}
add_filter('big_image_size_threshold', 'novastream_image_size_threshold');

/**
 * Convert JPEG and PNG output to WebP when the selected server image editor
 * supports it. WebP preserves PNG alpha transparency, and GIFs remain GIFs.
 *
 * @param string[] $formats Current source-to-output MIME mappings.
 * @return string[]
 */
function novastream_image_output_formats($formats)
{
    if (
        !wp_image_editor_supports(array('mime_type' => 'image/webp'))
    ) {
        return $formats;
    }

    if (NOVASTREAM_IMAGE_CONVERT_JPEG_TO_WEBP) {
        $formats['image/jpeg'] = 'image/webp';
    }

    if (NOVASTREAM_IMAGE_CONVERT_PNG_TO_WEBP) {
        $formats['image/png'] = 'image/webp';
    }

    return $formats;
}
add_filter('image_editor_output_format', 'novastream_image_output_formats');

/**
 * Apply starter quality settings to images saved by WordPress.
 *
 * @param int    $quality   Current quality value.
 * @param string $mime_type Output image MIME type.
 * @return int
 */
function novastream_image_editor_quality($quality, $mime_type)
{
    if ('image/webp' === $mime_type) {
        return max(1, min(100, (int) NOVASTREAM_IMAGE_WEBP_QUALITY));
    }

    if ('image/jpeg' === $mime_type) {
        return max(1, min(100, (int) NOVASTREAM_IMAGE_JPEG_QUALITY));
    }

    return $quality;
}
add_filter('wp_editor_set_quality', 'novastream_image_editor_quality', 10, 2);

/**
 * Save JPEG fallbacks progressively when the active editor supports it.
 *
 * @param bool   $interlace Current progressive-image setting.
 * @param string $mime_type Output image MIME type.
 * @return bool
 */
function novastream_save_progressive_jpegs($interlace, $mime_type)
{
    return 'image/jpeg' === $mime_type ? true : $interlace;
}
add_filter('image_save_progressive', 'novastream_save_progressive_jpegs', 10, 2);

/**
 * Build a current optimization report from WordPress attachment metadata.
 *
 * @param int $attachment_id Image attachment ID.
 * @return array|null
 */
function novastream_get_image_optimization_status($attachment_id)
{
    if (!wp_attachment_is_image($attachment_id)) {
        return null;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    $metadata = is_array($metadata) ? $metadata : array();
    $active_path = get_attached_file($attachment_id);
    $active_filetype = $active_path ? wp_check_filetype($active_path) : array();
    $active_mime = !empty($active_filetype['type'])
        ? (string) $active_filetype['type']
        : (string) get_post_mime_type($attachment_id);
    $original_name = (string) ($metadata['original_image'] ?? '');
    $original_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $has_social_original = in_array($original_extension, array('jpg', 'jpeg', 'png'), true);
    $original_path = $has_social_original ? wp_get_original_image_path($attachment_id) : '';
    $original_exists = $original_path && is_file($original_path);
    $active_size = $active_path && is_file($active_path) ? (int) filesize($active_path) : 0;
    $original_size = $original_exists ? (int) filesize($original_path) : 0;
    $stored_paths = array_filter(array($active_path, $original_exists ? $original_path : ''));

    if ($active_path && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size) {
            if (!empty($size['file'])) {
                $stored_paths[] = dirname($active_path) . '/' . $size['file'];
            }
        }
    }

    $stored_paths = array_unique($stored_paths);
    $total_size = 0;

    foreach ($stored_paths as $stored_path) {
        if (is_file($stored_path)) {
            $total_size += (int) filesize($stored_path);
        }
    }

    $is_converted = 'image/webp' === $active_mime && $original_exists;
    $webp_supported = wp_image_editor_supports(array('mime_type' => 'image/webp'));
    $conversion_enabled = ('image/jpeg' === $active_mime && NOVASTREAM_IMAGE_CONVERT_JPEG_TO_WEBP)
        || ('image/png' === $active_mime && NOVASTREAM_IMAGE_CONVERT_PNG_TO_WEBP);
    $retry_failed = (bool) get_post_meta($attachment_id, '_novastream_image_optimization_retry_failed', true);

    if ($is_converted) {
        $label = __('Optimized WebP', 'novastream-theme-helper');
        $state = 'optimized';
    } elseif ('image/webp' === $active_mime) {
        $label = __('Native WebP', 'novastream-theme-helper');
        $state = 'warning';
    } elseif (!$webp_supported) {
        $label = __('WebP unavailable', 'novastream-theme-helper');
        $state = 'unavailable';
    } else {
        $active_format = strtoupper((string) pathinfo($active_path, PATHINFO_EXTENSION));

        if ($conversion_enabled && $retry_failed) {
            $label = __('Optimization failed — regenerate to try again', 'novastream-theme-helper');
        } elseif ($conversion_enabled) {
            $label = $original_size > $active_size && $active_size > 0
                ? sprintf(
                    /* translators: %s: current active image format. */
                    __('Scaled %s — WebP conversion pending', 'novastream-theme-helper'),
                    $active_format
                )
                : sprintf(
                    /* translators: %s: current active image format. */
                    __('%s — WebP conversion pending', 'novastream-theme-helper'),
                    $active_format
                );
        } else {
            $label = sprintf(
                /* translators: %s: current active image format. */
                __('%s — WebP conversion disabled', 'novastream-theme-helper'),
                $active_format
            );
        }
        $state = $conversion_enabled
            ? ($retry_failed ? 'failed' : 'pending')
            : 'disabled';
    }

    $active_reduction = $original_size > $active_size && $active_size > 0
        ? $original_size - $active_size
        : 0;

    return array(
        'label'               => $label,
        'state'               => $state,
        'active_mime'         => $active_mime,
        'active_size'         => $active_size,
        'original_extension'  => $original_extension,
        'original_size'       => $original_size,
        'has_social_fallback' => $has_social_original && $original_exists,
        'generated_sizes'     => !empty($metadata['sizes']) && is_array($metadata['sizes'])
            ? count($metadata['sizes'])
            : 0,
        'total_size'          => $total_size,
        'active_reduction'    => $active_reduction,
        'can_regenerate'      => in_array($state, array('pending', 'failed'), true),
    );
}

/**
 * Build the secured URL used to retry image optimization.
 *
 * @param int $attachment_id Image attachment ID.
 * @return string
 */
function novastream_get_image_optimization_regeneration_url($attachment_id)
{
    $url = add_query_arg(
        array(
            'action'        => 'novastream_regenerate_image_optimization',
            'attachment_id' => (int) $attachment_id,
        ),
        admin_url('admin-post.php')
    );

    return wp_nonce_url($url, 'novastream_regenerate_image_optimization_' . (int) $attachment_id);
}

/**
 * Regenerate an image and all registered sizes from its retained source file.
 *
 * The previous attachment path and metadata are restored when WordPress cannot
 * produce the expected WebP, so a failed retry does not leave the attachment in
 * a less useful state.
 *
 * @param int $attachment_id Image attachment ID.
 * @return array|WP_Error Current optimization status on success, or an error.
 */
function novastream_regenerate_image_optimization($attachment_id)
{
    $attachment_id = (int) $attachment_id;

    if (!wp_attachment_is_image($attachment_id)) {
        return new WP_Error(
            'novastream_invalid_image_attachment',
            __('The selected attachment is not an image.', 'novastream-theme-helper')
        );
    }

    if (!wp_image_editor_supports(array('mime_type' => 'image/webp'))) {
        return new WP_Error(
            'novastream_webp_unavailable',
            __('The server image editor does not support WebP.', 'novastream-theme-helper')
        );
    }

    $source_path = wp_get_original_image_path($attachment_id, true);

    if (!$source_path || !is_file($source_path)) {
        update_post_meta($attachment_id, '_novastream_image_optimization_retry_failed', time());

        return new WP_Error(
            'novastream_image_source_missing',
            __('The original image file could not be found.', 'novastream-theme-helper')
        );
    }

    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $previous_path = get_attached_file($attachment_id, true);
    $previous_metadata = wp_get_attachment_metadata($attachment_id);
    $metadata = wp_generate_attachment_metadata($attachment_id, $source_path);

    if (is_array($metadata) && !empty($metadata['width']) && !empty($metadata['height'])) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    $status = novastream_get_image_optimization_status($attachment_id);

    if (!is_array($status) || 'optimized' !== $status['state']) {
        if ($previous_path) {
            update_attached_file($attachment_id, $previous_path);
        }

        wp_update_attachment_metadata($attachment_id, $previous_metadata);
        update_post_meta($attachment_id, '_novastream_image_optimization_retry_failed', time());

        return new WP_Error(
            'novastream_image_regeneration_failed',
            __('WordPress could not regenerate the image as WebP.', 'novastream-theme-helper')
        );
    }

    delete_post_meta($attachment_id, '_novastream_image_optimization_retry_failed');

    return $status;
}

/**
 * Handle a Media Library image optimization retry.
 */
function novastream_handle_image_optimization_regeneration()
{
    $attachment_id = isset($_GET['attachment_id']) ? absint(wp_unslash($_GET['attachment_id'])) : 0;

    check_admin_referer('novastream_regenerate_image_optimization_' . $attachment_id);

    if (!$attachment_id || !current_user_can('edit_post', $attachment_id)) {
        wp_die(
            esc_html__('You are not allowed to regenerate this image.', 'novastream-theme-helper'),
            esc_html__('Image regeneration denied', 'novastream-theme-helper'),
            array('response' => 403)
        );
    }

    $result = novastream_regenerate_image_optimization($attachment_id);
    $notice = is_wp_error($result) ? 'error' : 'success';
    $redirect = wp_get_referer();
    $redirect = $redirect ? $redirect : admin_url('upload.php');
    $redirect = remove_query_arg(array('novastream_image_regenerated', 'attachment_id'), $redirect);
    $redirect = add_query_arg(
        array(
            'novastream_image_regenerated' => $notice,
            'attachment_id'                => $attachment_id,
        ),
        $redirect
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_novastream_regenerate_image_optimization', 'novastream_handle_image_optimization_regeneration');

/**
 * Show the result of an image optimization retry.
 */
function novastream_image_optimization_regeneration_notice()
{
    // This read-only query flag is set by the nonce-protected admin-post handler above.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (empty($_GET['novastream_image_regenerated'])) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $result = sanitize_key(wp_unslash($_GET['novastream_image_regenerated']));

    if ('success' === $result) {
        $class = 'notice notice-success is-dismissible';
        $message = __('Image optimization regenerated successfully.', 'novastream-theme-helper');
    } elseif ('error' === $result) {
        $class = 'notice notice-error is-dismissible';
        $message = __('Image optimization could not be regenerated. Check the server image editor and try again.', 'novastream-theme-helper');
    } else {
        return;
    }

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}
add_action('admin_notices', 'novastream_image_optimization_regeneration_notice');

/**
 * Render an image optimization report for Media Library interfaces.
 *
 * @param int  $attachment_id Image attachment ID.
 * @param bool $compact       Whether to render the list-table summary.
 * @return string
 */
function novastream_render_image_optimization_status($attachment_id, $compact = false)
{
    $status = novastream_get_image_optimization_status($attachment_id);

    if (!$status) {
        return '';
    }

    $badge = sprintf(
        '<span class="novastream-image-status__badge is-%1$s">%2$s</span>',
        esc_attr($status['state']),
        esc_html($status['label'])
    );
    $regenerate = '';

    if ($status['can_regenerate'] && current_user_can('edit_post', $attachment_id)) {
        $regenerate = sprintf(
            '<a class="button button-small novastream-image-status__regenerate" href="%1$s">%2$s</a>',
            esc_url(novastream_get_image_optimization_regeneration_url($attachment_id)),
            esc_html__('Regenerate', 'novastream-theme-helper')
        );
    }

    if ($compact) {
        return $badge . sprintf(
            '<small>%s</small>',
            esc_html(
                sprintf(
                    /* translators: 1: image format, 2: active image file size. */
                    __('%1$s · %2$s active', 'novastream-theme-helper'),
                    strtoupper((string) pathinfo(wp_parse_url(wp_get_attachment_url($attachment_id), PHP_URL_PATH), PATHINFO_EXTENSION)),
                    $status['active_size'] ? size_format($status['active_size'], 1) : __('Unknown size', 'novastream-theme-helper')
                )
            )
        ) . $regenerate;
    }

    $social_fallback = $status['has_social_fallback']
        ? sprintf(
            /* translators: %s: original image format. */
            __('Available from retained %s original', 'novastream-theme-helper'),
            strtoupper($status['original_extension'])
        )
        : __('Unavailable — use a JPEG or PNG source for social images', 'novastream-theme-helper');

    $rows = array(
        __('Status', 'novastream-theme-helper')          => $badge,
        __('Active image', 'novastream-theme-helper')    => sprintf(
            '%1$s · %2$s',
            esc_html($status['active_mime']),
            esc_html($status['active_size'] ? size_format($status['active_size'], 1) : __('Unknown size', 'novastream-theme-helper'))
        ),
        __('Social fallback', 'novastream-theme-helper') => esc_html($social_fallback),
        __('Generated sizes', 'novastream-theme-helper') => esc_html((string) $status['generated_sizes']),
        __('Total disk usage', 'novastream-theme-helper') => esc_html(size_format($status['total_size'], 1)),
    );

    if ($status['original_size']) {
        $rows[__('Original backup', 'novastream-theme-helper')] = esc_html(size_format($status['original_size'], 1));
    }

    if ($status['active_reduction']) {
        $rows[__('Active-file reduction (original retained)', 'novastream-theme-helper')] = esc_html(
            size_format($status['active_reduction'], 1)
        );
    }

    $html = '<div class="novastream-image-status">';

    foreach ($rows as $label => $value) {
        $html .= sprintf(
            '<div class="novastream-image-status__row"><strong>%1$s</strong><span>%2$s</span></div>',
            esc_html($label),
            $value
        );
    }

    if ($regenerate) {
        $html .= '<div class="novastream-image-status__actions">' . $regenerate . '</div>';
    }

    return $html . '</div>';
}

/**
 * Add the optimization report to image details in the Media Library modal.
 *
 * @param array   $fields     Attachment compatibility fields.
 * @param WP_Post $attachment Attachment post object.
 * @return array
 */
function novastream_add_image_optimization_attachment_field($fields, $attachment)
{
    if (!wp_attachment_is_image($attachment->ID)) {
        return $fields;
    }

    $fields['novastream_image_optimization'] = array(
        'label' => __('Optimization', 'novastream-theme-helper'),
        'input' => 'html',
        'html'  => novastream_render_image_optimization_status($attachment->ID),
    );

    return $fields;
}
add_filter('attachment_fields_to_edit', 'novastream_add_image_optimization_attachment_field', 10, 2);

/**
 * Add an optimization summary column to the Media Library list view.
 *
 * @param array $columns Existing Media Library columns.
 * @return array
 */
function novastream_add_image_optimization_media_column($columns)
{
    $columns['novastream_image_optimization'] = __('Optimization', 'novastream-theme-helper');

    return $columns;
}
add_filter('manage_media_columns', 'novastream_add_image_optimization_media_column');

/**
 * Render the Media Library optimization summary column.
 *
 * @param string $column_name  Column identifier.
 * @param int    $attachment_id Attachment ID.
 */
function novastream_render_image_optimization_media_column($column_name, $attachment_id)
{
    if ('novastream_image_optimization' !== $column_name) {
        return;
    }

    echo wp_kses_post(novastream_render_image_optimization_status($attachment_id, true));
}
add_action('manage_media_custom_column', 'novastream_render_image_optimization_media_column', 10, 2);

/**
 * Style the read-only Media Library optimization reports.
 */
function novastream_enqueue_image_optimization_admin_styles()
{
    wp_add_inline_style(
        'common',
        '.media-types-required-info:has(+.compat-attachment-fields .compat-field-novastream_image_optimization){display:none}.novastream-image-status{display:grid;gap:6px}.novastream-image-status__row{display:grid;gap:2px}.novastream-image-status__row strong{font-size:11px;text-transform:uppercase;color:#646970}.novastream-image-status__badge{display:inline-block;padding:2px 7px;border-radius:999px;background:#dcdcde;color:#1d2327;font-weight:600}.novastream-image-status__badge.is-optimized{background:#d7f0df;color:#005c12}.novastream-image-status__badge.is-warning{background:#fcf0c3;color:#6e4c00}.novastream-image-status__badge.is-pending{background:#dceaf7;color:#004b73}.novastream-image-status__badge.is-failed{background:#f6d7d7;color:#8a2424}.novastream-image-status__actions{margin-top:4px}.column-novastream_image_optimization{width:220px}.column-novastream_image_optimization small{display:block;margin-top:5px;color:#646970}.column-novastream_image_optimization .novastream-image-status__regenerate{margin-top:7px}'
    );
}
add_action('admin_enqueue_scripts', 'novastream_enqueue_image_optimization_admin_styles');
