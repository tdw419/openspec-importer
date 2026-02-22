<?php
/**
 * OpenSpec Importer
 *
 * Handles importing OpenSpec documents into WordPress.
 *
 * @package OpenSpec_Importer
 * @since 1.0.0
 * @license GPL-2.0-or-later
 */

/*
This file is part of OpenSpec Importer.

OpenSpec Importer is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

OpenSpec Importer is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

if (!defined('ABSPATH')) {
    exit;
}

class OpenSpec_Importer {

    /**
     * Import statistics
     *
     * @var array
     */
    private $stats = array(
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0,
        'updated' => 0,
    );

    /**
     * Import all OpenSpec documents from a directory
     *
     * @param string $openspec_dir Path to openspec directory
     * @return array|WP_Error Import statistics or error
     */
    public function import_all(string $openspec_dir) {
        if (!is_dir($openspec_dir)) {
            return new WP_Error('directory_not_found', 'OpenSpec directory not found: ' . $openspec_dir);
        }

        // Find all markdown files
        $files = $this->find_markdown_files($openspec_dir);

        if (empty($files)) {
            return new WP_Error('no_files', 'No markdown files found in OpenSpec directory');
        }

        $results = array();

        foreach ($files as $filepath) {
            $result = $this->import_document($filepath, $openspec_dir);
            $results[] = $result;
        }

        return array(
            'stats' => $this->stats,
            'details' => $results,
        );
    }

    /**
     * Find all markdown files in directory recursively
     *
     * @param string $dir Directory path
     * @return array List of file paths
     */
    private function find_markdown_files(string $dir): array {
        $files = array();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        // Sort by path for consistent ordering
        sort($files);

        return $files;
    }

    /**
     * Import a single document
     *
     * @param string $filepath    Path to markdown file
     * @param string $openspec_dir Root openspec directory
     * @return array Import result
     */
    private function import_document(string $filepath, string $openspec_dir): array {
        // Parse the document
        $parser = new OpenSpec_MarkdownParser($filepath);
        $document = $parser->parse();

        if (is_wp_error($document)) {
            $this->stats['errors']++;
            return array(
                'status' => 'error',
                'filepath' => $filepath,
                'reason' => $document->get_error_message(),
            );
        }

        // Check for empty content
        if (empty($document['content'])) {
            $this->stats['skipped']++;
            return array(
                'status' => 'skipped',
                'filepath' => $filepath,
                'document_id' => $document['document_id'],
                'reason' => 'Empty document',
            );
        }

        // Check if already imported
        $existing = $this->find_existing_post($document['document_id']);

        // Format as HTML
        $formatter = new OpenSpec_HtmlFormatter();
        $html = $formatter->format($document);

        if ($existing) {
            // Update existing post if file has been modified
            $existing_mtime = get_post_meta($existing, '_openspec_filemtime', true);

            if ($existing_mtime && $document['filemtime'] <= $existing_mtime) {
                $this->stats['skipped']++;
                return array(
                    'status' => 'skipped',
                    'filepath' => $filepath,
                    'document_id' => $document['document_id'],
                    'reason' => 'Already up to date',
                    'post_id' => $existing,
                );
            }

            // Update existing post
            $post_id = $this->update_post($existing, $document, $html);

            if (is_wp_error($post_id)) {
                $this->stats['errors']++;
                return array(
                    'status' => 'error',
                    'filepath' => $filepath,
                    'document_id' => $document['document_id'],
                    'reason' => $post_id->get_error_message(),
                );
            }

            $this->stats['updated']++;
            return array(
                'status' => 'updated',
                'filepath' => $filepath,
                'document_id' => $document['document_id'],
                'post_id' => $post_id,
                'title' => $document['title'],
            );
        }

        // Create new post
        $post_id = $this->create_post($document, $html);

        if (is_wp_error($post_id)) {
            $this->stats['errors']++;
            return array(
                'status' => 'error',
                'filepath' => $filepath,
                'document_id' => $document['document_id'],
                'reason' => $post_id->get_error_message(),
            );
        }

        $this->stats['imported']++;
        return array(
            'status' => 'imported',
            'filepath' => $filepath,
            'document_id' => $document['document_id'],
            'post_id' => $post_id,
            'title' => $document['title'],
        );
    }

    /**
     * Find existing post by document ID
     *
     * @param string $document_id Document ID
     * @return int|false Post ID or false
     */
    private function find_existing_post(string $document_id) {
        $query = new WP_Query(array(
            'post_type' => 'openspec_doc',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_openspec_document_id',
                    'value' => $document_id,
                ),
            ),
            'fields' => 'ids',
        ));

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return false;
    }

    /**
     * Create a new WordPress post
     *
     * @param array  $document Document data
     * @param string $html     Formatted HTML content
     * @return int|WP_Error Post ID or error
     */
    private function create_post(array $document, string $html) {
        // Add CSS to content
        $formatter = new OpenSpec_HtmlFormatter();
        $full_content = $formatter->get_css() . $html;

        $post_data = array(
            'post_title'   => $document['title'],
            'post_content' => $full_content,
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'openspec_doc',
            'post_name'    => sanitize_title($document['document_id']),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store metadata
        $this->save_post_meta($post_id, $document);

        // Set taxonomies
        $this->set_taxonomies($post_id, $document);

        return $post_id;
    }

    /**
     * Update an existing WordPress post
     *
     * @param int    $post_id  Post ID
     * @param array  $document Document data
     * @param string $html     Formatted HTML content
     * @return int|WP_Error Post ID or error
     */
    private function update_post(int $post_id, array $document, string $html) {
        $formatter = new OpenSpec_HtmlFormatter();
        $full_content = $formatter->get_css() . $html;

        $post_data = array(
            'ID'           => $post_id,
            'post_title'   => $document['title'],
            'post_content' => $full_content,
        );

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update metadata
        $this->save_post_meta($post_id, $document);

        // Update taxonomies
        $this->set_taxonomies($post_id, $document);

        return $post_id;
    }

    /**
     * Save post metadata
     *
     * @param int   $post_id  Post ID
     * @param array $document Document data
     */
    private function save_post_meta(int $post_id, array $document): void {
        update_post_meta($post_id, '_openspec_document_id', $document['document_id']);
        update_post_meta($post_id, '_openspec_filepath', $document['filepath']);
        update_post_meta($post_id, '_openspec_relative_path', $document['relative_path']);
        update_post_meta($post_id, '_openspec_type', $document['type']);
        update_post_meta($post_id, '_openspec_filemtime', $document['filemtime']);
        update_post_meta($post_id, '_openspec_imported_at', current_time('mysql'));

        // Store frontmatter as JSON
        if (!empty($document['frontmatter'])) {
            update_post_meta($post_id, '_openspec_frontmatter', wp_json_encode($document['frontmatter']));
        }
    }

    /**
     * Set post taxonomies
     *
     * @param int   $post_id  Post ID
     * @param array $document Document data
     */
    private function set_taxonomies(int $post_id, array $document): void {
        // Set document type taxonomy
        $type_term = term_exists($document['type'], 'openspec_type');
        if (!$type_term) {
            $type_term = wp_insert_term($document['type'], 'openspec_type');
        }
        if (!is_wp_error($type_term)) {
            wp_set_object_terms($post_id, (int) $type_term['term_id'], 'openspec_type');
        }

        // Set project taxonomy from path
        $project = $this->extract_project_from_path($document['relative_path']);
        if ($project) {
            $project_term = term_exists($project, 'openspec_project');
            if (!$project_term) {
                $project_term = wp_insert_term($project, 'openspec_project');
            }
            if (!is_wp_error($project_term)) {
                wp_set_object_terms($post_id, (int) $project_term['term_id'], 'openspec_project', true);
            }
        }
    }

    /**
     * Extract project name from path
     *
     * @param string $relative_path Relative path
     * @return string|null Project name
     */
    private function extract_project_from_path(string $relative_path): ?string {
        // Extract project from changes/<project>/ or specs/<project>/
        if (preg_match('#^(?:changes|specs)/([^/]+)#', $relative_path, $matches)) {
            return sanitize_key($matches[1]);
        }
        return null;
    }

    /**
     * Get import statistics
     *
     * @return array Statistics
     */
    public function get_stats(): array {
        return $this->stats;
    }
}
