<?php
/**
 * Uninstall script for OpenSpec Importer
 *
 * This file is called when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up all plugin data including post types and taxonomies.
 *
 * @package OpenSpec_Importer
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options
 */
delete_option('openspec_importer_docs_dir');
delete_option('openspec_importer_version');

/**
 * Delete all posts of the openspec_doc post type
 */
$args = array(
    'post_type'      => 'openspec_doc',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
);

$query = new WP_Query($args);

if ($query->have_posts()) {
    foreach ($query->posts as $post_id) {
        // Delete post meta
        delete_post_meta($post_id, '_openspec_document_id');
        delete_post_meta($post_id, '_openspec_filepath');
        delete_post_meta($post_id, '_openspec_relative_path');
        delete_post_meta($post_id, '_openspec_type');
        delete_post_meta($post_id, '_openspec_filemtime');
        delete_post_meta($post_id, '_openspec_imported_at');
        delete_post_meta($post_id, '_openspec_frontmatter');

        // Delete the post
        wp_delete_post($post_id, true);
    }
}

/**
 * Delete custom taxonomies terms
 */
$taxonomies = array('openspec_type', 'openspec_project');

foreach ($taxonomies as $taxonomy) {
    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ));

    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
    }
}

// Clear any cached data
wp_cache_flush();
