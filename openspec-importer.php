<?php
/**
 * Plugin Name: OpenSpec Importer
 * Plugin URI: https://wordpress.org/plugins/openspec-importer/
 * Description: Import OpenSpec documents (requirements, design, tasks, proposals) from openspec/ folder into WordPress as formatted posts
 * Version: 1.0.0
 * Author: Geometry OS
 * Author URI: https://geometryos.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: openspec-importer
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * This plugin imports OpenSpec markdown documents with YAML frontmatter
 * into WordPress posts with:
 * - YAML frontmatter parsing and metadata extraction
 * - Markdown to HTML conversion with syntax highlighting
 * - Prism.js syntax highlighting for code blocks
 * - Document type categorization (requirements, design, tasks, proposals)
 *
 * "Specifications are living documents that illuminate the path from idea to implementation."
 *
 * @package OpenSpec_Importer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required class files
require_once plugin_dir_path(__FILE__) . 'includes/class-markdown-parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-html-formatter.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-importer.php';

/**
 * Class OpenSpec_Importer_Admin
 *
 * Main admin class for the OpenSpec Importer plugin.
 */
class OpenSpec_Importer_Admin {

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'handle_import'));
        add_action('admin_init', array($this, 'test_parse'));
    }

    /**
     * Get the OpenSpec directory path
     *
     * @return string Path to openspec directory
     */
    private function get_openspec_dir(): string {
        // Use GEOMETRY_OS_ROOT environment variable or fallback to default
        $geometry_root = getenv('GEOMETRY_OS_ROOT');
        if ($geometry_root) {
            return $geometry_root . '/openspec/';
        }
        return str_replace('~', getenv('HOME'), '~/zion/projects/geometry_os/geometry_os/openspec/');
    }

    /**
     * Count total OpenSpec markdown files
     *
     * @return int Number of .md files
     */
    private function count_specs(): int {
        $openspec_dir = $this->get_openspec_dir();
        $count = 0;

        // Count in changes/ directory
        $changes_pattern = rtrim($openspec_dir, '/') . '/changes/**/*.md';
        $changes_files = glob($changes_pattern);
        if ($changes_files) {
            $count += count($changes_files);
        }

        // Count in specs/ directory
        $specs_pattern = rtrim($openspec_dir, '/') . '/specs/**/*.md';
        $specs_files = glob($specs_pattern);
        if ($specs_files) {
            $count += count($specs_files);
        }

        // Also do recursive search for deeper nesting
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($openspec_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $all_md = array();
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $all_md[] = $file->getPathname();
            }
        }

        return count(array_unique($all_md));
    }

    /**
     * Count imported posts
     *
     * @return int Number of posts with _openspec_document_id meta
     */
    private function count_imported(): int {
        $query = new WP_Query(array(
            'post_type' => 'openspec_doc',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        return $query->found_posts;
    }

    /**
     * Add admin menu page
     */
    public function add_menu() {
        add_menu_page(
            'OpenSpec Docs',
            'OpenSpec Docs',
            'manage_options',
            'openspec-importer',
            array($this, 'render_page'),
            'dashicons-media-document',
            31
        );
    }

    /**
     * Render the admin page
     */
    public function render_page() {
        // Get counts
        $openspec_dir = $this->get_openspec_dir();
        $spec_count = $this->count_specs();
        $imported_count = $this->count_imported();

        ?>
        <div class="wrap">
            <h1>OpenSpec Document Importer</h1>

            <?php
            // Display admin notices
            if (isset($_GET['openspec_imported'])) {
                $imported = intval($_GET['openspec_imported']);
                $skipped = intval($_GET['openspec_skipped']);
                $errors = intval($_GET['openspec_errors']);
                $notice_class = ($errors > 0) ? 'notice-warning' : 'notice-success';
                echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>';
                echo esc_html(sprintf(
                    'Import complete: %d imported, %d skipped (duplicates), %d errors.',
                    $imported, $skipped, $errors
                ));
                echo '</p></div>';
            }

            // Display error notices
            if (isset($_GET['openspec_error'])) {
                $error_type = sanitize_key($_GET['openspec_error']);
                $error_msg = isset($_GET['openspec_error_msg']) ? sanitize_text_field(urldecode($_GET['openspec_error_msg'])) : '';
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo '<strong>Error:</strong> ';
                switch ($error_type) {
                    case 'no_files':
                        echo 'No .md files found in the OpenSpec directory.';
                        break;
                    case 'parse_error':
                        echo 'Failed to parse markdown file.';
                        break;
                    case 'import_error':
                        echo esc_html($error_msg) ?: 'An error occurred during import.';
                        break;
                    case 'empty_document':
                        echo 'The document has no content.';
                        break;
                    default:
                        echo esc_html($error_msg) ?: 'An unknown error occurred.';
                }
                echo '</p></div>';
            }
            ?>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Status</h2>
                <p>Import OpenSpec documents (requirements, design, tasks, proposals) into WordPress.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">OpenSpec Directory</th>
                        <td><code><?php echo esc_html($openspec_dir); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">Document Count</th>
                        <td><?php echo esc_html($spec_count); ?> .md files found</td>
                    </tr>
                    <tr>
                        <th scope="row">Imported Count</th>
                        <td><?php echo esc_html($imported_count); ?> documents imported</td>
                    </tr>
                </table>

                <h3>Actions</h3>

                <!-- Import All Documents Form -->
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('openspec_import_all', 'openspec_import_nonce'); ?>
                    <input type="hidden" name="openspec_action" value="import_all">
                    <button type="submit" class="button button-primary">
                        Import All Documents
                    </button>
                    <p class="description">
                        Import all .md files from the OpenSpec directory. Duplicates will be skipped.
                    </p>
                </form>

                <!-- Test Parse First Document Form -->
                <form method="post" action="">
                    <?php wp_nonce_field('openspec_test_parse', 'openspec_test_nonce'); ?>
                    <input type="hidden" name="openspec_action" value="test_parse">
                    <button type="submit" class="button">
                        Test Parse First Document
                    </button>
                    <p class="description">
                        Parse and preview the first .md file found without creating a post.
                    </p>
                </form>

                <?php
                // Display test parse preview if available
                if (isset($_GET['openspec_preview']) && $_GET['openspec_preview'] === '1') {
                    $preview = get_transient('openspec_preview_html');
                    if ($preview) {
                        echo '<h3>Preview</h3>';
                        echo '<div style="background: #fff; border: 1px solid #ccc; padding: 15px; max-height: 500px; overflow: auto;">';
                        echo $preview;
                        echo '</div>';
                        delete_transient('openspec_preview_html');
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle import all documents action
     */
    public function handle_import() {
        // Check if this is our action
        if (!isset($_POST['openspec_action']) || $_POST['openspec_action'] !== 'import_all') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['openspec_import_nonce']) || !wp_verify_nonce($_POST['openspec_import_nonce'], 'openspec_import_all')) {
            wp_die('Security check failed');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Run import
        $importer = new OpenSpec_Importer();
        $stats = $importer->import_all($this->get_openspec_dir());

        // Handle WP_Error from import_all
        if (is_wp_error($stats)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'import_error',
                'openspec_error_msg' => urlencode($stats->get_error_message()),
            ), admin_url('admin.php')));
            exit;
        }

        // Redirect with stats
        wp_redirect(add_query_arg(array(
            'page' => 'openspec-importer',
            'openspec_imported' => $stats['imported'],
            'openspec_skipped' => $stats['skipped'],
            'openspec_errors' => $stats['errors'],
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle test parse action
     */
    public function test_parse() {
        // Check if this is our action
        if (!isset($_POST['openspec_action']) || $_POST['openspec_action'] !== 'test_parse') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['openspec_test_nonce']) || !wp_verify_nonce($_POST['openspec_test_nonce'], 'openspec_test_parse')) {
            wp_die('Security check failed');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Find first .md file with content
        $openspec_dir = $this->get_openspec_dir();
        $md_files = $this->find_md_files($openspec_dir);

        if (empty($md_files)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'no_files',
            ), admin_url('admin.php')));
            exit;
        }

        // Parse first file with error handling
        $filepath = $md_files[0];
        $parser = new OpenSpec_MarkdownParser($filepath);
        $document = $parser->parse();

        if (is_wp_error($document)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'parse_error',
                'openspec_error_msg' => urlencode($document->get_error_message()),
            ), admin_url('admin.php')));
            exit;
        }

        // Check for empty document
        if (empty($document['content'])) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'empty_document',
            ), admin_url('admin.php')));
            exit;
        }

        // Format with HTML formatter
        $formatter = new OpenSpec_HtmlFormatter();
        $preview = '<div style="background: #f9f9f9; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
        $preview .= '<strong>File:</strong> ' . esc_html(basename($filepath)) . '<br>';
        $preview .= '<strong>Document ID:</strong> ' . esc_html($document['document_id']) . '<br>';
        $preview .= '<strong>Type:</strong> ' . esc_html($document['type']) . '<br>';
        if (!empty($document['frontmatter'])) {
            $preview .= '<strong>Frontmatter:</strong><br><pre style="margin: 5px 0; padding: 5px; background: #eee; overflow: auto;">' . esc_html(print_r($document['frontmatter'], true)) . '</pre>';
        }
        $preview .= '</div>';
        $preview .= $formatter->get_css();
        $preview .= $formatter->format($document);

        // Store in transient for display
        set_transient('openspec_preview_html', $preview, 60);

        // Redirect to show preview
        wp_redirect(add_query_arg(array(
            'page' => 'openspec-importer',
            'openspec_preview' => '1',
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Find all markdown files in directory recursively
     */
    private function find_md_files(string $dir): array {
        $files = array();

        if (!is_dir($dir)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files;
    }
}

/**
 * Register custom post type for OpenSpec documents
 */
function openspec_register_post_type() {
    register_post_type('openspec_doc', array(
        'labels' => array(
            'name' => 'OpenSpec Documents',
            'singular_name' => 'OpenSpec Document',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Document',
            'edit_item' => 'Edit Document',
            'new_item' => 'New Document',
            'view_item' => 'View Document',
            'search_items' => 'Search Documents',
            'not_found' => 'No documents found',
            'not_found_in_trash' => 'No documents found in trash',
        ),
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'custom-fields', 'author'),
        'menu_icon' => 'dashicons-media-document',
        'rewrite' => array('slug' => 'openspec'),
    ));
}
add_action('init', 'openspec_register_post_type');

/**
 * Register custom taxonomies for OpenSpec documents
 */
function openspec_register_taxonomies() {
    // Document Type taxonomy
    register_taxonomy('openspec_type', 'openspec_doc', array(
        'labels' => array(
            'name' => 'Document Types',
            'singular_name' => 'Document Type',
            'menu_name' => 'Document Types',
            'all_items' => 'All Types',
            'edit_item' => 'Edit Type',
            'add_new_item' => 'Add New Type',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'openspec-type'),
    ));

    // Project taxonomy
    register_taxonomy('openspec_project', 'openspec_doc', array(
        'labels' => array(
            'name' => 'Projects',
            'singular_name' => 'Project',
            'menu_name' => 'Projects',
            'all_items' => 'All Projects',
            'edit_item' => 'Edit Project',
            'add_new_item' => 'Add New Project',
        ),
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'openspec-project'),
    ));
}
add_action('init', 'openspec_register_taxonomies');

/**
 * Enqueue Prism.js for syntax highlighting on OpenSpec document posts
 */
function openspec_enqueue_prism() {
    // Only load on single posts
    if (!is_singular('openspec_doc')) {
        return;
    }

    // Enqueue Prism CSS (Tomorrow Night theme)
    wp_enqueue_style(
        'prism-css',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css',
        array(),
        '1.29.0'
    );

    // Enqueue Prism JS core
    wp_enqueue_script(
        'prism-js',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js',
        array(),
        '1.29.0',
        true
    );

    // Enqueue language components
    $languages = array('python', 'bash', 'javascript', 'rust', 'php', 'yaml', 'json', 'markdown', 'typescript', 'wgsl', 'glsl');
    foreach ($languages as $lang) {
        wp_enqueue_script(
            "prism-js-{$lang}",
            "https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-{$lang}.min.js",
            array('prism-js'),
            '1.29.0',
            true
        );
    }

    // Add inline CSS for document styling
    $formatter = new OpenSpec_HtmlFormatter();
    wp_add_inline_style('prism-css', $formatter->get_css());
}
add_action('wp_enqueue_scripts', 'openspec_enqueue_prism');

// Initialize the plugin
new OpenSpec_Importer_Admin();
