<?php
/**
 * Plugin Name: OpenSpec Importer
 * Plugin URI: https://wordpress.org/plugins/openspec-importer/
 * Description: Import OpenSpec markdown documents with YAML frontmatter into WordPress as a searchable knowledge base
 * Version: 1.0.0
 * Author: Geometry OS
 * Author URI: https://geometryos.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: openspec-importer
 * Domain Path: /languages
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
 * @since 1.0.0
 * @license GPL-2.0-or-later
 */

/*
OpenSpec Importer is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

OpenSpec Importer is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with OpenSpec Importer. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('OPENSPEC_IMPORTER_VERSION', '1.0.0');
define('OPENSPEC_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPENSPEC_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required class files
require_once OPENSPEC_IMPORTER_PLUGIN_DIR . 'includes/class-markdown-parser.php';
require_once OPENSPEC_IMPORTER_PLUGIN_DIR . 'includes/class-html-formatter.php';
require_once OPENSPEC_IMPORTER_PLUGIN_DIR . 'includes/class-importer.php';

/**
 * Class OpenSpec_Importer_Admin
 *
 * Main admin class for the OpenSpec Importer plugin.
 *
 * @since 1.0.0
 */
class OpenSpec_Importer_Admin {

    /**
     * Plugin version
     *
     * @var string
     */
    private $version = OPENSPEC_IMPORTER_VERSION;

    /**
     * Initialize the plugin
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'handle_import'));
        add_action('admin_init', array($this, 'test_parse'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     * @since 1.0.0
     */
    public function add_settings_link(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=openspec-importer-settings')),
            esc_html__('Settings', 'openspec-importer')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register plugin settings
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting('openspec_importer_settings', 'openspec_importer_docs_dir', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_directory_path'),
            'default' => '',
        ));

        register_setting('openspec_importer_settings', 'openspec_importer_version', array(
            'type' => 'string',
            'default' => $this->version,
        ));
    }

    /**
     * Sanitize directory path
     *
     * @param string $path Raw directory path.
     * @return string Sanitized directory path.
     * @since 1.0.0
     */
    public function sanitize_directory_path(string $path): string {
        if (empty($path)) {
            return '';
        }

        // Expand ~ to home directory
        $expanded = str_replace('~', getenv('HOME'), $path);

        // Validate - prevent directory traversal
        if (preg_match('/\.\./', $path)) {
            add_settings_error(
                'openspec_importer_docs_dir',
                'invalid_path',
                __('Directory traversal not allowed in path.', 'openspec-importer')
            );
            return get_option('openspec_importer_docs_dir', '');
        }

        return $path;
    }

    /**
     * Get the OpenSpec directory path (expanded)
     *
     * @return string Expanded path
     * @since 1.0.0
     */
    private function get_openspec_dir(): string {
        $path = get_option('openspec_importer_docs_dir', '');

        if (empty($path)) {
            // Try environment variable
            $geometry_root = getenv('OPENSPEC_ROOT');
            if ($geometry_root) {
                return $geometry_root;
            }
            return '';
        }

        return str_replace('~', getenv('HOME'), $path);
    }

    /**
     * Add admin menu page for import
     *
     * @since 1.0.0
     */
    public function add_menu() {
        add_menu_page(
            __('OpenSpec Docs', 'openspec-importer'),
            __('OpenSpec Docs', 'openspec-importer'),
            'manage_options',
            'openspec-importer',
            array($this, 'render_page'),
            'dashicons-media-document',
            31
        );
    }

    /**
     * Add settings page
     *
     * @since 1.0.0
     */
    public function add_settings_page() {
        add_options_page(
            __('OpenSpec Importer Settings', 'openspec-importer'),
            __('OpenSpec Importer', 'openspec-importer'),
            'manage_options',
            'openspec-importer-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render the settings page
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('openspec_importer_settings');
                do_settings_sections('openspec_importer_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openspec_importer_docs_dir">
                                <?php esc_html_e('Documents Directory', 'openspec-importer'); ?>
                            </label>
                        </th>
                        <td>
                            <?php
                            $path = get_option('openspec_importer_docs_dir', '');
                            ?>
                            <input type="text"
                                   id="openspec_importer_docs_dir"
                                   name="openspec_importer_docs_dir"
                                   value="<?php echo esc_attr($path); ?>"
                                   class="regular-text"
                                   placeholder="/path/to/your/openspec/ or ~/documents/openspec/">
                            <p class="description">
                                <?php esc_html_e('Path to your OpenSpec documents directory. Use ~ for your home directory.', 'openspec-importer'); ?>
                            </p>
                            <?php
                            $expanded = $this->get_openspec_dir();
                            if (!empty($expanded)) {
                                $exists = is_dir($expanded);
                                echo '<p class="description">';
                                if ($exists) {
                                    echo '<span style="color: green;">&#10003;</span> ' . esc_html__('Directory found:', 'openspec-importer') . ' <code>' . esc_html($expanded) . '</code>';
                                } else {
                                    echo '<span style="color: #d63638;">&#10007;</span> ' . esc_html__('Directory not found:', 'openspec-importer') . ' <code>' . esc_html($expanded) . '</code>';
                                }
                                echo '</p>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Information', 'openspec-importer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Plugin Version', 'openspec-importer'); ?></th>
                    <td><?php echo esc_html(OPENSPEC_IMPORTER_VERSION); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('PHP Version', 'openspec-importer'); ?></th>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('WordPress Version', 'openspec-importer'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Supported Languages', 'openspec-importer'); ?></th>
                    <td>Python, Bash, JavaScript, Rust, PHP, YAML, JSON, Markdown, TypeScript, WGSL, GLSL</td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Count total OpenSpec markdown files
     *
     * @return int Number of .md files
     * @since 1.0.0
     */
    private function count_specs(): int {
        $openspec_dir = $this->get_openspec_dir();
        if (empty($openspec_dir) || !is_dir($openspec_dir)) {
            return 0;
        }

        $all_md = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($openspec_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

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
     * @since 1.0.0
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
     * Render the admin page
     *
     * @since 1.0.0
     */
    public function render_page() {
        // Get counts
        $openspec_dir = $this->get_openspec_dir();
        $config_path = get_option('openspec_importer_docs_dir', '');
        $spec_count = $this->count_specs();
        $imported_count = $this->count_imported();
        $dir_exists = !empty($openspec_dir) && is_dir($openspec_dir);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('OpenSpec Document Importer', 'openspec-importer'); ?></h1>

            <?php
            // Display admin notices
            if (isset($_GET['openspec_imported'])) {
                $imported = intval($_GET['openspec_imported']);
                $skipped = intval($_GET['openspec_skipped']);
                $errors = intval($_GET['openspec_errors']);
                $notice_class = ($errors > 0) ? 'notice-warning' : 'notice-success';
                echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>';
                echo esc_html(sprintf(
                    /* translators: %1$d: imported count, %2$d: skipped count, %3$d: error count */
                    __('Import complete: %1$d imported, %2$d skipped (duplicates), %3$d errors.', 'openspec-importer'),
                    $imported, $skipped, $errors
                ));
                echo '</p></div>';
            }

            // Display error notices
            if (isset($_GET['openspec_error'])) {
                $error_type = sanitize_key($_GET['openspec_error']);
                $error_msg = isset($_GET['openspec_error_msg']) ? sanitize_text_field(urldecode($_GET['openspec_error_msg'])) : '';
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo '<strong>' . esc_html__('Error:', 'openspec-importer') . '</strong> ';
                switch ($error_type) {
                    case 'no_files':
                        esc_html_e('No .md files found in the OpenSpec directory.', 'openspec-importer');
                        break;
                    case 'parse_error':
                        esc_html_e('Failed to parse markdown file.', 'openspec-importer');
                        break;
                    case 'import_error':
                        echo esc_html($error_msg) ?: esc_html__('An error occurred during import.', 'openspec-importer');
                        break;
                    case 'empty_document':
                        esc_html_e('The document has no content.', 'openspec-importer');
                        break;
                    case 'dir_not_found':
                        esc_html_e('OpenSpec directory not found. Please check settings.', 'openspec-importer');
                        break;
                    case 'not_configured':
                        esc_html_e('Please configure the documents directory in Settings.', 'openspec-importer');
                        break;
                    default:
                        echo esc_html($error_msg) ?: esc_html__('An unknown error occurred.', 'openspec-importer');
                }
                echo '</p></div>';
            }
            ?>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php esc_html_e('Status', 'openspec-importer'); ?></h2>
                <p><?php esc_html_e('Import OpenSpec documents (requirements, design, tasks, proposals) into WordPress.', 'openspec-importer'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Documents Directory', 'openspec-importer'); ?></th>
                        <td>
                            <code><?php echo esc_html($config_path ?: __('Not configured', 'openspec-importer')); ?></code>
                            <?php if (!$dir_exists && !empty($config_path)) : ?>
                                <br><span style="color: #d63638;">
                                    <?php esc_html_e('Directory not found!', 'openspec-importer'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Document Count', 'openspec-importer'); ?></th>
                        <td><?php echo esc_html(sprintf(__('%d .md files found', 'openspec-importer'), $spec_count)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Imported Count', 'openspec-importer'); ?></th>
                        <td><?php echo esc_html(sprintf(__('%d documents imported', 'openspec-importer'), $imported_count)); ?></td>
                    </tr>
                </table>

                <h3><?php esc_html_e('Actions', 'openspec-importer'); ?></h3>

                <?php if ($dir_exists) : ?>
                    <!-- Import All Documents Form -->
                    <form method="post" action="" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('openspec_import_all', 'openspec_import_nonce'); ?>
                        <input type="hidden" name="openspec_action" value="import_all">
                        <?php submit_button(__('Import All Documents', 'openspec-importer'), 'primary', 'submit', false); ?>
                        <p class="description">
                            <?php esc_html_e('Import all .md files from the OpenSpec directory. Duplicates will be skipped.', 'openspec-importer'); ?>
                        </p>
                    </form>

                    <!-- Test Parse First Document Form -->
                    <form method="post" action="">
                        <?php wp_nonce_field('openspec_test_parse', 'openspec_test_nonce'); ?>
                        <input type="hidden" name="openspec_action" value="test_parse">
                        <?php submit_button(__('Test Parse First Document', 'openspec-importer'), 'secondary', 'submit', false); ?>
                        <p class="description">
                            <?php esc_html_e('Parse and preview the first .md file found without creating a post.', 'openspec-importer'); ?>
                        </p>
                    </form>
                <?php else : ?>
                    <p class="description" style="color: #d63638;">
                        <?php
                        if (empty($config_path)) {
                            esc_html_e('Please configure the documents directory in Settings to enable import.', 'openspec-importer');
                        } else {
                            esc_html_e('The configured directory does not exist. Please update the settings.', 'openspec-importer');
                        }
                        ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=openspec-importer-settings')); ?>" class="button">
                        <?php esc_html_e('Configure Settings', 'openspec-importer'); ?>
                    </a>
                <?php endif; ?>

                <?php
                // Display test parse preview if available
                if (isset($_GET['openspec_preview']) && $_GET['openspec_preview'] === '1') {
                    $preview = get_transient('openspec_preview_html');
                    if ($preview) {
                        echo '<h3>' . esc_html__('Preview', 'openspec-importer') . '</h3>';
                        echo '<div style="background: #fff; border: 1px solid #ccc; padding: 15px; max-height: 500px; overflow: auto;">';
                        echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitized
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
     *
     * @since 1.0.0
     */
    public function handle_import() {
        // Check if this is our action
        if (!isset($_POST['openspec_action']) || $_POST['openspec_action'] !== 'import_all') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['openspec_import_nonce']) || !wp_verify_nonce($_POST['openspec_import_nonce'], 'openspec_import_all')) {
            wp_die(esc_html__('Security check failed', 'openspec-importer'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'openspec-importer'));
        }

        $openspec_dir = $this->get_openspec_dir();

        // Check if directory is configured
        if (empty($openspec_dir)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'not_configured',
            ), admin_url('admin.php')));
            exit;
        }

        // Check if directory exists
        if (!is_dir($openspec_dir)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'dir_not_found',
            ), admin_url('admin.php')));
            exit;
        }

        // Run import
        $importer = new OpenSpec_Importer();
        $result = $importer->import_all($openspec_dir);

        // Handle WP_Error from import_all
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'import_error',
                'openspec_error_msg' => urlencode($result->get_error_message()),
            ), admin_url('admin.php')));
            exit;
        }

        $stats = $result['stats'];

        // Redirect with stats
        wp_redirect(add_query_arg(array(
            'page' => 'openspec-importer',
            'openspec_imported' => $stats['imported'],
            'openspec_skipped' => $stats['skipped'] + ($stats['updated'] ?? 0),
            'openspec_errors' => $stats['errors'],
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle test parse action
     *
     * @since 1.0.0
     */
    public function test_parse() {
        // Check if this is our action
        if (!isset($_POST['openspec_action']) || $_POST['openspec_action'] !== 'test_parse') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['openspec_test_nonce']) || !wp_verify_nonce($_POST['openspec_test_nonce'], 'openspec_test_parse')) {
            wp_die(esc_html__('Security check failed', 'openspec-importer'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'openspec-importer'));
        }

        $openspec_dir = $this->get_openspec_dir();

        // Check if directory is configured
        if (empty($openspec_dir)) {
            wp_redirect(add_query_arg(array(
                'page' => 'openspec-importer',
                'openspec_error' => 'not_configured',
            ), admin_url('admin.php')));
            exit;
        }

        // Find first .md file with content
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
        $preview .= '<strong>' . esc_html__('File:', 'openspec-importer') . '</strong> ' . esc_html(basename($filepath)) . '<br>';
        $preview .= '<strong>' . esc_html__('Document ID:', 'openspec-importer') . '</strong> ' . esc_html($document['document_id']) . '<br>';
        $preview .= '<strong>' . esc_html__('Type:', 'openspec-importer') . '</strong> ' . esc_html($document['type']) . '<br>';
        if (!empty($document['frontmatter'])) {
            $preview .= '<strong>' . esc_html__('Frontmatter:', 'openspec-importer') . '</strong><br><pre style="margin: 5px 0; padding: 5px; background: #eee; overflow: auto;">' . esc_html(print_r($document['frontmatter'], true)) . '</pre>';
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
     *
     * @param string $dir Directory path.
     * @return array List of file paths.
     * @since 1.0.0
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
 *
 * @since 1.0.0
 */
function openspec_register_post_type() {
    register_post_type('openspec_doc', array(
        'labels' => array(
            'name'                  => __('OpenSpec Documents', 'openspec-importer'),
            'singular_name'         => __('OpenSpec Document', 'openspec-importer'),
            'add_new'               => __('Add New', 'openspec-importer'),
            'add_new_item'          => __('Add New Document', 'openspec-importer'),
            'edit_item'             => __('Edit Document', 'openspec-importer'),
            'new_item'              => __('New Document', 'openspec-importer'),
            'view_item'             => __('View Document', 'openspec-importer'),
            'search_items'          => __('Search Documents', 'openspec-importer'),
            'not_found'             => __('No documents found', 'openspec-importer'),
            'not_found_in_trash'    => __('No documents found in trash', 'openspec-importer'),
        ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => array('title', 'editor', 'custom-fields', 'author'),
        'menu_icon'    => 'dashicons-media-document',
        'rewrite'      => array('slug' => 'openspec'),
    ));
}
add_action('init', 'openspec_register_post_type');

/**
 * Register custom taxonomies for OpenSpec documents
 *
 * @since 1.0.0
 */
function openspec_register_taxonomies() {
    // Document Type taxonomy
    register_taxonomy('openspec_type', 'openspec_doc', array(
        'labels' => array(
            'name'          => __('Document Types', 'openspec-importer'),
            'singular_name' => __('Document Type', 'openspec-importer'),
        ),
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'rewrite'      => array('slug' => 'openspec-type'),
    ));

    // Project taxonomy
    register_taxonomy('openspec_project', 'openspec_doc', array(
        'labels' => array(
            'name'          => __('Projects', 'openspec-importer'),
            'singular_name' => __('Project', 'openspec-importer'),
        ),
        'hierarchical' => false,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'rewrite'      => array('slug' => 'openspec-project'),
    ));
}
add_action('init', 'openspec_register_taxonomies');

/**
 * Enqueue Prism.js for syntax highlighting on OpenSpec document posts
 *
 * @since 1.0.0
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

    // Enqueue language components (filterable)
    $languages = apply_filters('openspec_importer_prism_languages', array(
        'python', 'bash', 'javascript', 'rust', 'php', 'yaml', 'json', 'markdown', 'typescript', 'wgsl', 'glsl'
    ));

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
