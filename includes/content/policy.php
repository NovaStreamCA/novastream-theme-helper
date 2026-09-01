<?php

/**
 * Shared front-end content, privacy, and search-query behavior.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function novastream_get_post_ancestor_id($post_id)
{
    $post = get_post($post_id);

    while ($post && 0 !== (int) $post->post_parent) {
        $post = get_post($post->post_parent);
    }

    return $post ? $post->ID : 0;
}

function novastream_redirect_home_banner()
{
    if (post_type_exists('home_banner') && is_singular('home_banner')) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}
add_action('template_redirect', 'novastream_redirect_home_banner');

function novastream_filter_link_query_args($query)
{
    $query['post_status'] = array('publish');
    $post_types = isset($query['post_type']) ? (array) $query['post_type'] : array('post', 'page');

    if (post_type_exists('product')) {
        $post_types[] = 'product';
    }

    $query['post_type'] = array_values(array_unique(array_diff($post_types, array('home_banner'))));

    return $query;
}
add_filter('wp_link_query_args', 'novastream_filter_link_query_args');

/**
 * Limit front-end searches to a requested public, searchable post type.
 *
 * The post_type query argument is intentionally validated instead of trusting
 * arbitrary values from the URL.
 */
function novastream_filter_search_post_type($query)
{
    if (is_admin() || ! $query->is_main_query() || ! $query->is_search()) {
        return;
    }

    $requested_type = isset($_GET['post_type'])
        ? sanitize_key(wp_unslash($_GET['post_type']))
        : '';

    if (! $requested_type) {
        return;
    }

    $post_type = get_post_type_object($requested_type);

    if ($post_type && $post_type->public && ! $post_type->exclude_from_search) {
        $query->set('post_type', $requested_type);
    }
}
add_action('pre_get_posts', 'novastream_filter_search_post_type');

function novastream_remove_oembed_author_data($data)
{
    unset($data['author_url'], $data['author_name']);

    return $data;
}
add_filter('oembed_response_data', 'novastream_remove_oembed_author_data');

/**
 * Determine whether the shared no-comments policy is enabled for this site.
 *
 * @return bool
 */
function novastream_comments_disabled()
{
    return (bool) apply_filters('novastream_disable_comments', true);
}

/**
 * Disable comments and trackbacks for every registered post type.
 */
function novastream_disable_comments_support()
{
    if (!novastream_comments_disabled()) {
        return;
    }

    foreach (get_post_types() as $post_type) {
        remove_post_type_support($post_type, 'comments');
        remove_post_type_support($post_type, 'trackbacks');
    }
}
add_action('init', 'novastream_disable_comments_support', PHP_INT_MAX);

/**
 * Keep comments and pings closed, including on previously published content.
 *
 * @param bool $open Existing open state.
 * @return bool
 */
function novastream_close_comments($open)
{
    return novastream_comments_disabled() ? false : $open;
}
add_filter('comments_open', 'novastream_close_comments', PHP_INT_MAX);
add_filter('pings_open', 'novastream_close_comments', PHP_INT_MAX);

/**
 * Prevent existing comments from being rendered on the front end.
 *
 * @param array $comments Existing comments.
 * @return array
 */
function novastream_hide_existing_comments($comments)
{
    return novastream_comments_disabled() ? array() : $comments;
}
add_filter('comments_array', 'novastream_hide_existing_comments', PHP_INT_MAX);

/**
 * Redirect the public registration action to the site homepage.
 */
function novastream_redirect_register_page()
{
    $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
	$enabled = (bool) apply_filters('novastream_redirect_registration', true);

    if ($enabled && 'register' === $action) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}
add_action('login_init', 'novastream_redirect_register_page');
