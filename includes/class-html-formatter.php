<?php
/**
 * OpenSpec HTML Formatter
 *
 * Formats OpenSpec documents as HTML with syntax highlighting.
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

class OpenSpec_HtmlFormatter {

    /**
     * Format a document as HTML
     *
     * @param array $document Parsed document
     * @return string HTML content
     */
    public function format(array $document): string {
        $html = '<div class="openspec-document" data-type="' . esc_attr($document['type']) . '">';

        // Metadata header
        $html .= $this->format_metadata($document);

        // Document content
        $html .= '<div class="openspec-content">';
        $html .= $this->format_markdown($document['content']);
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Format document metadata as HTML
     *
     * @param array $document Document data
     * @return string HTML
     */
    private function format_metadata(array $document): string {
        $html = '<div class="openspec-metadata">';

        // Type badge
        $type_colors = array(
            'requirements' => '#2196f3',
            'design' => '#4caf50',
            'tasks' => '#ff9800',
            'proposal' => '#9c27b0',
            'spec' => '#00bcd4',
            'research' => '#795548',
            'change' => '#f44336',
            'archived' => '#9e9e9e',
            'document' => '#607d8b',
        );
        $type_color = $type_colors[$document['type']] ?? '#607d8b';
        $html .= '<span class="openspec-type-badge" style="background: ' . esc_attr($type_color) . '">';
        $html .= esc_html(ucfirst($document['type']));
        $html .= '</span>';

        // Relative path
        if (!empty($document['relative_path'])) {
            $html .= '<p class="openspec-path">';
            $html .= '<strong>Path:</strong> <code>' . esc_html($document['relative_path']) . '</code>';
            $html .= '</p>';
        }

        // Frontmatter display
        if (!empty($document['frontmatter'])) {
            $html .= '<div class="openspec-frontmatter">';
            $html .= '<details><summary>Frontmatter</summary>';
            $html .= '<pre class="openspec-frontmatter-yaml">';
            $html .= esc_html($this->array_to_yaml($document['frontmatter']));
            $html .= '</pre></details>';
            $html .= '</div>';
        }

        $html .= '</div><hr class="openspec-divider">';
        return $html;
    }

    /**
     * Convert array to YAML string (simple)
     *
     * @param array $array Array to convert
     * @param int   $indent Indentation level
     * @return string YAML
     */
    private function array_to_yaml(array $array, int $indent = 0): string {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $yaml .= $prefix . $key . ": []\n";
                } elseif (array_keys($value) === range(0, count($value) - 1)) {
                    // Indexed array
                    $yaml .= $prefix . $key . ":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $prefix . "  -\n" . $this->array_to_yaml($item, $indent + 2);
                        } else {
                            $yaml .= $prefix . "  - " . $this->format_yaml_value($item) . "\n";
                        }
                    }
                } else {
                    // Associative array
                    $yaml .= $prefix . $key . ":\n";
                    $yaml .= $this->array_to_yaml($value, $indent + 1);
                }
            } else {
                $yaml .= $prefix . $key . ": " . $this->format_yaml_value($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Format a value for YAML output
     *
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function format_yaml_value($value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_string($value) && (strpos($value, "\n") !== false || strpos($value, ':') !== false)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return (string) $value;
    }

    /**
     * Format markdown content as HTML
     *
     * @param string $content Markdown content
     * @return string HTML
     */
    private function format_markdown(string $content): string {
        // Process code blocks first (before markdown)
        $content = $this->format_code_blocks($content);

        // Convert headers
        $content = $this->format_headers($content);

        // Convert tables
        $content = $this->format_tables($content);

        // Convert lists
        $content = $this->format_lists($content);

        // Convert inline formatting
        $content = $this->format_inline($content);

        // Convert paragraphs
        $content = $this->format_paragraphs($content);

        // Convert line breaks
        $content = nl2br($content);

        return $content;
    }

    /**
     * Format code blocks with syntax highlighting
     *
     * @param string $content Content
     * @return string Formatted content
     */
    private function format_code_blocks(string $content): string {
        // Match fenced code blocks with optional language
        $pattern = '/```(\w*)\n(.*?)```/s';

        return preg_replace_callback($pattern, function ($matches) {
            $language = !empty($matches[1]) ? $matches[1] : 'plaintext';
            $code = esc_html($matches[2]);

            return '<pre class="openspec-code"><code class="language-' . esc_attr($language) . '">' . $code . '</code></pre>';
        }, $content);
    }

    /**
     * Format markdown headers
     *
     * @param string $content Content
     * @return string Formatted content
     */
    private function format_headers(string $content): string {
        // H1
        $content = preg_replace('/^#\s+(.+)$/m', '<h1 class="openspec-h1">$1</h1>', $content);
        // H2
        $content = preg_replace('/^##\s+(.+)$/m', '<h2 class="openspec-h2">$1</h2>', $content);
        // H3
        $content = preg_replace('/^###\s+(.+)$/m', '<h3 class="openspec-h3">$1</h3>', $content);
        // H4
        $content = preg_replace('/^####\s+(.+)$/m', '<h4 class="openspec-h4">$1</h4>', $content);
        // H5
        $content = preg_replace('/^#####\s+(.+)$/m', '<h5 class="openspec-h5">$1</h5>', $content);
        // H6
        $content = preg_replace('/^######\s+(.+)$/m', '<h6 class="openspec-h6">$1</h6>', $content);

        return $content;
    }

    /**
     * Format markdown tables
     *
     * @param string $content Content
     * @return string Formatted content
     */
    private function format_tables(string $content): string {
        // Match markdown tables
        $pattern = '/^\|(.+)\|\s*\n\|[-:\s|]+\|\s*\n((?:\|.+\|\s*\n?)+)/m';

        return preg_replace_callback($pattern, function ($matches) {
            $header_line = $matches[1];
            $body_lines = $matches[2];

            // Parse header
            $headers = array_map('trim', explode('|', $header_line));
            $headers = array_filter($headers);

            // Parse body
            $rows = array();
            foreach (explode("\n", trim($body_lines)) as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $cells = array_map('trim', explode('|', trim($line, '|')));
                $rows[] = $cells;
            }

            // Build HTML table
            $html = '<table class="openspec-table">';
            $html .= '<thead><tr>';
            foreach ($headers as $header) {
                $html .= '<th>' . esc_html($header) . '</th>';
            }
            $html .= '</tr></thead>';
            $html .= '<tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . esc_html($cell) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';

            return $html;
        }, $content);
    }

    /**
     * Format markdown lists
     *
     * @param string $content Content
     * @return string Formatted content
     */
    private function format_lists(string $content): string {
        // Unordered list items
        $content = preg_replace('/^[\*\-]\s+(.+)$/m', '<li>$1</li>', $content);

        // Ordered list items
        $content = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $content);

        // Wrap consecutive li elements in ul
        $content = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul class="openspec-list">$0</ul>', $content);

        // Checkbox items
        $content = preg_replace('/\[\s*\]/', '<input type="checkbox" disabled>', $content);
        $content = preg_replace('/\[x\]/i', '<input type="checkbox" disabled checked>', $content);

        return $content;
    }

    /**
     * Format inline markdown elements
     *
     * @param string $content Content
     * @return string Formatted content
     */
    private function format_inline(string $content): string {
        // Bold: **text** or __text__
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $content);

        // Italic: *text* or _text_
        $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
        $content = preg_replace('/_(.+?)_/', '<em>$1</em>', $content);

        // Strikethrough: ~~text~~
        $content = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $content);

        // Inline code: `code`
        $content = preg_replace('/`([^`]+)`/', '<code class="openspec-inline-code">$1</code>', $content);

        // Links: [text](url)
        $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="openspec-link">$1</a>', $content);

        // Images: ![alt](url)
        $content = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" class="openspec-image">', $content);

        // Horizontal rules
        $content = preg_replace('/^---+$/m', '<hr class="openspec-hr">', $content);
        $content = preg_replace('/^\*\*\*+$/m', '<hr class="openspec-hr">', $content);

        // Blockquotes
        $content = preg_replace('/^>\s+(.+)$/m', '<blockquote class="openspec-quote">$1</blockquote>', $content);

        return $content;
    }

    /**
     * Format paragraphs
     *
     * @param string $content Content
     * @return string Formatted content
     */
    private function format_paragraphs(string $content): string {
        // Wrap remaining text blocks in paragraphs
        $lines = explode("\n\n", $content);
        $result = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip if already wrapped in block element
            if (preg_match('/^<(h[1-6]|ul|ol|li|table|pre|blockquote|hr|div)/', $line)) {
                $result[] = $line;
            } else {
                $result[] = '<p class="openspec-paragraph">' . $line . '</p>';
            }
        }

        return implode("\n", $result);
    }

    /**
     * Get CSS for document styling
     *
     * @return string CSS
     */
    public function get_css(): string {
        return '
<style>
.openspec-document {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 900px;
    margin: 0 auto;
    line-height: 1.6;
    color: #24292e;
}
.openspec-metadata {
    background: #f6f8fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #0366d6;
}
.openspec-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    color: white;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 10px;
}
.openspec-path {
    margin: 5px 0;
    font-size: 13px;
    color: #586069;
}
.openspec-path code {
    background: #e1e4e8;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}
.openspec-frontmatter {
    margin-top: 10px;
}
.openspec-frontmatter summary {
    cursor: pointer;
    font-size: 13px;
    color: #586069;
}
.openspec-frontmatter-yaml {
    background: #f1f8ff;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    overflow-x: auto;
    margin-top: 10px;
}
.openspec-divider {
    border: none;
    border-top: 1px solid #e1e4e8;
    margin: 20px 0;
}
.openspec-content {
    padding: 10px 0;
}
.openspec-h1 {
    font-size: 28px;
    border-bottom: 1px solid #e1e4e8;
    padding-bottom: 10px;
    margin: 20px 0;
    color: #0366d6;
}
.openspec-h2 {
    font-size: 24px;
    border-bottom: 1px solid #e1e4e8;
    padding-bottom: 8px;
    margin: 18px 0;
}
.openspec-h3 {
    font-size: 20px;
    margin: 16px 0;
}
.openspec-h4 {
    font-size: 18px;
    margin: 14px 0;
}
.openspec-h5, .openspec-h6 {
    font-size: 16px;
    margin: 12px 0;
    color: #586069;
}
.openspec-code {
    background: #24292e;
    color: #f6f8fa;
    padding: 16px;
    border-radius: 6px;
    overflow-x: auto;
    margin: 10px 0;
}
.openspec-code code {
    background: transparent;
    padding: 0;
    font-family: "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 14px;
}
.openspec-inline-code {
    background: #f6f8fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: "SF Mono", Consolas, monospace;
    font-size: 0.9em;
    color: #d73a49;
}
.openspec-table {
    border-collapse: collapse;
    width: 100%;
    margin: 15px 0;
}
.openspec-table th,
.openspec-table td {
    border: 1px solid #dfe2e5;
    padding: 8px 12px;
    text-align: left;
}
.openspec-table th {
    background: #f6f8fa;
    font-weight: bold;
}
.openspec-table tr:nth-child(even) {
    background: #f6f8fa;
}
.openspec-list {
    margin: 10px 0;
    padding-left: 25px;
}
.openspec-list li {
    margin: 5px 0;
}
.openspec-list input[type="checkbox"] {
    margin-right: 8px;
}
.openspec-link {
    color: #0366d6;
    text-decoration: none;
}
.openspec-link:hover {
    text-decoration: underline;
}
.openspec-quote {
    border-left: 4px solid #dfe2e5;
    padding-left: 16px;
    margin: 10px 0;
    color: #586069;
    font-style: italic;
}
.openspec-hr {
    border: none;
    border-top: 2px solid #e1e4e8;
    margin: 20px 0;
}
.openspec-paragraph {
    margin: 10px 0;
}
</style>';
    }
}
