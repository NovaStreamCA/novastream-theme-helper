<?php

/**
 * Native WordPress admin-menu organization.
 *
 * The menu is grouped into Dashboard, content, site management, and any
 * remaining plugin pages. Existing capabilities continue to control whether
 * each item is visible to the current user.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const NOVASTREAM_ADMIN_MENU_SEPARATORS = array(
    'content'    => 'separator-novastream-content',
    'management' => 'separator-novastream-management',
    'other'      => 'separator-novastream-other',
);

/**
 * Return the ordered admin-menu sections.
 *
 * Child themes may insert their own sections without teaching the base theme
 * about plugin-specific menu slugs.
 *
 * @return array<string, string> Group names mapped to separator slugs.
 */
function novastream_get_admin_menu_sections()
{
    return apply_filters('novastream_admin_menu_sections', NOVASTREAM_ADMIN_MENU_SEPARATORS);
}

/**
 * Add stable separator entries that can be placed by the menu-order filter.
 */
function novastream_register_admin_menu_separators()
{
    global $menu;

    foreach (novastream_get_admin_menu_sections() as $separator) {
        $menu[] = array('', 'read', $separator, '', 'wp-menu-separator');
    }
}
add_action('admin_menu', 'novastream_register_admin_menu_separators', PHP_INT_MAX);

/**
 * Remove the Comments screen from the admin navigation.
 */
function novastream_remove_comments_admin_menu()
{
    if (function_exists('novastream_comments_disabled') && !novastream_comments_disabled()) {
        return;
    }

    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'novastream_remove_comments_admin_menu', PHP_INT_MAX);

/**
 * Remove the Comments shortcut from the admin toolbar.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin toolbar instance.
 */
function novastream_remove_comments_admin_bar_item($wp_admin_bar)
{
    if (function_exists('novastream_comments_disabled') && !novastream_comments_disabled()) {
        return;
    }

    $wp_admin_bar->remove_node('comments');
}
add_action('admin_bar_menu', 'novastream_remove_comments_admin_bar_item', PHP_INT_MAX);

/**
 * Remove the Recent Comments dashboard widget.
 */
function novastream_remove_comments_dashboard_widget()
{
    if (function_exists('novastream_comments_disabled') && !novastream_comments_disabled()) {
        return;
    }

    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}
add_action('wp_dashboard_setup', 'novastream_remove_comments_dashboard_widget');

/**
 * Enable custom ordering while leaving access control to WordPress.
 *
 * @return bool
 */
function novastream_enable_custom_admin_menu_order()
{
    return true;
}
add_filter('custom_menu_order', 'novastream_enable_custom_admin_menu_order');

/**
 * Identify the group for a top-level menu slug.
 *
 * @param string $slug Admin menu slug.
 * @return string
 */
function novastream_get_admin_menu_group($slug)
{
    if ('index.php' === $slug) {
        return apply_filters('novastream_admin_menu_group', 'dashboard', $slug);
    }

    $management_slugs = array(
        'themes.php',
        'plugins.php',
        'users.php',
        'profile.php',
        'tools.php',
        'options-general.php',
        'edit.php?post_type=acf-field-group',
        'general-settings',
        'novastream-seo-options',
    );

    if (
        in_array($slug, $management_slugs, true)
        || str_starts_with($slug, 'wpseo_')
        || str_starts_with($slug, 'acf-')
    ) {
        return apply_filters('novastream_admin_menu_group', 'management', $slug);
    }

    if (
        in_array($slug, array('edit.php', 'upload.php', 'edit.php?post_type=page'), true)
        || str_starts_with($slug, 'edit.php?post_type=')
        || str_contains($slug, 'wpforms')
    ) {
        return apply_filters('novastream_admin_menu_group', 'content', $slug);
    }

    return apply_filters('novastream_admin_menu_group', 'other', $slug);
}

/**
 * Rank familiar items while preserving discovery order for dynamic CPTs and
 * unrecognized plugin pages.
 *
 * @param string $group Menu group.
 * @param string $slug  Admin menu slug.
 * @return int
 */
function novastream_get_admin_menu_rank($group, $slug)
{
    $ranks = array(
        'content' => array(
            'edit.php'                => 10,
            'upload.php'              => 20,
            'edit.php?post_type=page' => 30,
        ),
        'management' => array(
            'themes.php'                         => 10,
            'plugins.php'                        => 20,
            'users.php'                          => 30,
            'profile.php'                        => 30,
            'tools.php'                          => 40,
            'options-general.php'                => 50,
            'edit.php?post_type=acf-field-group' => 60,
            'general-settings'                   => 70,
            'novastream-seo-options'             => 80,
        ),
    );

    if (isset($ranks[$group][$slug])) {
        return (int) apply_filters('novastream_admin_menu_rank', $ranks[$group][$slug], $group, $slug);
    }

    if ('content' === $group && str_contains($slug, 'wpforms')) {
        return (int) apply_filters('novastream_admin_menu_rank', 1000, $group, $slug);
    }

    if ('management' === $group && str_starts_with($slug, 'wpseo_')) {
        return (int) apply_filters('novastream_admin_menu_rank', 90, $group, $slug);
    }

    return (int) apply_filters('novastream_admin_menu_rank', 500, $group, $slug);
}

/**
 * Group top-level menu items with standard WordPress separators.
 *
 * @param string[] $menu_order Current top-level menu slugs.
 * @return string[]
 */
function novastream_order_admin_menu($menu_order)
{
    $sections = novastream_get_admin_menu_sections();
    $groups = array_fill_keys(array_merge(array('dashboard'), array_keys($sections)), array());

    foreach ($menu_order as $index => $slug) {
        if (str_starts_with($slug, 'separator')) {
            continue;
        }

        $group = novastream_get_admin_menu_group($slug);
        if (!isset($groups[$group])) {
            $group = 'other';
        }

        $groups[$group][] = array(
            'slug'  => $slug,
            'rank'  => novastream_get_admin_menu_rank($group, $slug),
            'index' => $index,
        );
    }

    foreach ($groups as &$items) {
        usort(
            $items,
            static function ($left, $right) {
                return array($left['rank'], $left['index']) <=> array($right['rank'], $right['index']);
            }
        );
        $items = array_column($items, 'slug');
    }
    unset($items);

    $ordered = $groups['dashboard'];
    foreach ($sections as $group => $separator) {
        if (!$groups[$group]) {
            continue;
        }

        if ($ordered) {
            $ordered[] = $separator;
        }
        array_push($ordered, ...$groups[$group]);
    }

    return $ordered;
}
add_filter('menu_order', 'novastream_order_admin_menu', PHP_INT_MAX);

/**
 * Visually strengthen the native separators used between menu groups.
 */
function novastream_style_admin_menu_separators()
{
    wp_add_inline_style(
        'common',
        '#adminmenu li.wp-menu-separator{margin:6px 0 6px;background:rgb(12.15, 12.15, 12.15);}'
    );
}
add_action('admin_enqueue_scripts', 'novastream_style_admin_menu_separators');
