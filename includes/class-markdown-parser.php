<?php
/**
 * OpenSpec Markdown Parser
 *
 * Parses OpenSpec markdown documents with YAML frontmatter.
 *
 * @package OpenSpec_Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class OpenSpec_MarkdownParser {

    /**
     * Path to the markdown file
     *
     * @var string
     */
    private $filepath;

    /**
     * Constructor
     *
     * @param string $filepath Path to markdown file
     */
    public function __construct(string $filepath) {
        $this->filepath = $filepath;
    }

    /**
     * Parse the markdown file
     *
     * @return array|WP_Error Parsed document or error
     */
    public function parse() {
        if (!file_exists($this->filepath)) {
            return new WP_Error('file_not_found', 'Markdown file not found: ' . $this->filepath);
        }

        $content = file_get_contents($this->filepath);
        if ($content === false) {
            return new WP_Error('read_error', 'Failed to read markdown file');
        }

        // Parse frontmatter and content
        $parsed = $this->parse_frontmatter($content);

        // Generate document ID from filepath
        $document_id = $this->generate_document_id();

        // Determine document type from filename or frontmatter
        $type = $this->determine_type($parsed['frontmatter']);

        // Extract title from frontmatter, first heading, or filename
        $title = $this->extract_title($parsed['frontmatter'], $parsed['content']);

        // Get relative path for categorization
        $relative_path = $this->get_relative_path();

        return array(
            'document_id' => $document_id,
            'filepath' => $this->filepath,
            'relative_path' => $relative_path,
            'type' => $type,
            'title' => $title,
            'frontmatter' => $parsed['frontmatter'],
            'content' => $parsed['content'],
            'raw_content' => $content,
            'filemtime' => filemtime($this->filepath),
        );
    }

    /**
     * Parse YAML frontmatter from content
     *
     * @param string $content Raw file content
     * @return array Array with 'frontmatter' and 'content' keys
     */
    private function parse_frontmatter(string $content): array {
        $frontmatter = array();
        $body = $content;

        // Check for YAML frontmatter (--- at start)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $matches)) {
            $yaml_content = $matches[1];
            $body = $matches[2];

            // Parse YAML (simple key: value parser)
            $frontmatter = $this->parse_yaml($yaml_content);
        }

        return array(
            'frontmatter' => $frontmatter,
            'content' => trim($body),
        );
    }

    /**
     * Simple YAML parser for frontmatter
     *
     * @param string $yaml YAML content
     * @return array Parsed data
     */
    private function parse_yaml(string $yaml): array {
        $data = array();
        $lines = explode("\n", $yaml);
        $current_key = null;
        $in_array = false;

        foreach ($lines as $line) {
            // Skip empty lines
            if (trim($line) === '') {
                continue;
            }

            // Check for array items
            if (preg_match('/^(\s*)-\s+(.+)$/', $line, $matches)) {
                if ($current_key && isset($data[$current_key]) && is_array($data[$current_key])) {
                    $data[$current_key][] = $this->parse_yaml_value(trim($matches[2]));
                }
                continue;
            }

            // Match key: value pairs
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);

                if ($value === '') {
                    // Empty value - might be array or nested object
                    $data[$key] = array();
                    $current_key = $key;
                    $in_array = true;
                } else {
                    $data[$key] = $this->parse_yaml_value($value);
                    $current_key = null;
                    $in_array = false;
                }
            }
        }

        return $data;
    }

    /**
     * Parse a YAML value
     *
     * @param string $value Raw value
     * @return mixed Parsed value
     */
    private function parse_yaml_value(string $value) {
        // Remove quotes
        $value = trim($value, "\"'");

        // Boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // Number
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Array notation [a, b, c]
        if (preg_match('/^\[(.*)\]$/', $value, $matches)) {
            $items = explode(',', $matches[1]);
            return array_map(function($item) {
                return $this->parse_yaml_value(trim($item));
            }, $items);
        }

        return $value;
    }

    /**
     * Generate unique document ID from filepath
     *
     * @return string Document ID
     */
    private function generate_document_id(): string {
        // Create ID from relative path, normalized
        $relative = $this->get_relative_path();
        $id = strtolower($relative);
        $id = preg_replace('/[^a-z0-9\/_-]/', '-', $id);
        $id = preg_replace('/-+/', '-', $id);
        $id = trim($id, '-');
        return $id;
    }

    /**
     * Get relative path from openspec root
     *
     * @return string Relative path
     */
    private function get_relative_path(): string {
        // Remove .md extension
        $path = preg_replace('/\.md$/', '', $this->filepath);

        // Try to find openspec in path
        if (strpos($path, '/openspec/') !== false) {
            $path = substr($path, strpos($path, '/openspec/') + 10);
        }

        return $path;
    }

    /**
     * Determine document type from frontmatter or path
     *
     * @param array $frontmatter Parsed frontmatter
     * @return string Document type
     */
    private function determine_type(array $frontmatter): string {
        // Check explicit phase in frontmatter
        if (!empty($frontmatter['phase'])) {
            return sanitize_key($frontmatter['phase']);
        }

        // Check document type
        if (!empty($frontmatter['spec'])) {
            return 'spec';
        }

        // Determine from filename
        $filename = strtolower(basename($this->filepath));

        if (strpos($filename, 'requirements') !== false) {
            return 'requirements';
        }
        if (strpos($filename, 'design') !== false) {
            return 'design';
        }
        if (strpos($filename, 'tasks') !== false) {
            return 'tasks';
        }
        if (strpos($filename, 'proposal') !== false) {
            return 'proposal';
        }
        if (strpos($filename, 'spec') !== false) {
            return 'spec';
        }
        if (strpos($filename, 'research') !== false) {
            return 'research';
        }

        // Determine from path
        $path = strtolower($this->filepath);
        if (strpos($path, '/specs/') !== false) {
            return 'spec';
        }
        if (strpos($path, '/changes/') !== false) {
            return 'change';
        }
        if (strpos($path, '/proposals/') !== false) {
            return 'proposal';
        }
        if (strpos($path, '/archive/') !== false) {
            return 'archived';
        }

        return 'document';
    }

    /**
     * Extract title from frontmatter, first heading, or filename
     *
     * @param array  $frontmatter Parsed frontmatter
     * @param string $content     Document content
     * @return string Title
     */
    private function extract_title(array $frontmatter, string $content): string {
        // Check frontmatter title
        if (!empty($frontmatter['title'])) {
            return $frontmatter['title'];
        }

        // Check frontmatter name
        if (!empty($frontmatter['name'])) {
            return $frontmatter['name'];
        }

        // Check first H1 heading
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        // Check first H2 heading
        if (preg_match('/^##\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fall back to filename
        $filename = basename($this->filepath, '.md');
        $filename = str_replace(array('-', '_'), ' ', $filename);
        $filename = ucwords($filename);

        return $filename;
    }
}
