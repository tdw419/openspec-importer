# OpenSpec Importer

A WordPress plugin that imports OpenSpec markdown documents with YAML frontmatter into WordPress as a searchable knowledge base.

[![WordPress Plugin](https://img.shields.io/badge/WordPress-1.0.0-blue.svg)](https://wordpress.org/plugins/openspec-importer/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net/)

## Description

OpenSpec Importer transforms your markdown specification documents into a beautiful, searchable WordPress knowledge base. Perfect for development teams, project managers, and anyone who wants to publish technical specifications.

**Features:**

* YAML frontmatter parsing with metadata extraction
* Full markdown to HTML conversion (tables, lists, code blocks)
* Custom post type (`openspec_doc`) with dedicated archive
* Automatic document type and project categorization
* Prism.js syntax highlighting for 12+ languages
* Smart updates - only re-imports changed files
* Configurable documents directory via settings

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- A directory containing markdown files

## Installation

1. Upload the `openspec-importer` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > OpenSpec Importer** to configure your documents directory
4. Navigate to **OpenSpec Docs** to import documents

## Configuration

1. Go to **Settings > OpenSpec Importer**
2. Enter the path to your markdown documents directory
   - Use `/absolute/path/to/docs/` for absolute paths
   - Use `~/relative/to/home/` for paths relative to your home directory
3. Save changes

You can also use the `OPENSPEC_ROOT` environment variable as a fallback.

## Document Types

The plugin automatically categorizes documents based on filename and path:

| Type | Detection Pattern |
|------|-------------------|
| Requirements | Filename contains "requirements" |
| Design | Filename contains "design" |
| Tasks | Filename contains "tasks" |
| Proposal | Filename contains "proposal" or path contains "/proposals/" |
| Spec | Path contains "/specs/" |
| Research | Filename contains "research" |
| Change | Path contains "/changes/" |

## Custom Post Type

Documents are stored as `openspec_doc` custom post type with:

- Title from frontmatter `title`, first heading, or filename
- Content formatted with Prism.js syntax highlighting
- YAML frontmatter preserved and displayable

### Post Meta Fields

| Meta Key | Description |
|----------|-------------|
| `_openspec_document_id` | Unique ID derived from path |
| `_openspec_filepath` | Absolute file path |
| `_openspec_relative_path` | Path from documents root |
| `_openspec_type` | Document type |
| `_openspec_filemtime` | File modification time |
| `_openspec_imported_at` | Import timestamp |
| `_openspec_frontmatter` | JSON-encoded frontmatter |

### Taxonomies

- `openspec_type` - Document type taxonomy
- `openspec_project` - Project name from path

## YAML Frontmatter

The parser recognizes standard YAML frontmatter:

```yaml
---
title: My Specification
phase: design
created: 2026-02-21
status: draft
---

# Content starts here
```

Supported fields:
- `title` - Document title (highest priority)
- `phase` - Document type override
- `spec` - Spec identifier
- Any custom fields are preserved in `_openspec_frontmatter`

## Markdown Support

| Feature | Support |
|---------|---------|
| Headers (H1-H6) | ✅ |
| Tables | ✅ |
| Ordered lists | ✅ |
| Unordered lists | ✅ |
| Task lists with checkboxes | ✅ |
| Fenced code blocks with language | ✅ |
| Inline code | ✅ |
| Bold, italic, strikethrough | ✅ |
| Links and images | ✅ |
| Blockquotes | ✅ |
| Horizontal rules | ✅ |

## REST API

OpenSpec documents are available via WordPress REST API:

```bash
# List all documents
curl https://yoursite.com/wp-json/wp/v2/openspec_doc

# Filter by type
curl "https://yoursite.com/wp-json/wp/v2/openspec_doc?openspec_type=requirements"

# Filter by project
curl "https://yoursite.com/wp-json/wp/v2/openspec_doc?openspec_project=my-project"
```

## Customization

### Add Prism.js Languages

```php
add_filter('openspec_importer_prism_languages', function($languages) {
    $languages[] = 'go';
    $languages[] = 'sql';
    return $languages;
});
```

### Custom CSS

Override these CSS classes in your theme:

- `.openspec-document` - Document container
- `.openspec-metadata` - Metadata header
- `.openspec-type-badge` - Type badge
- `.openspec-content` - Content area
- `.openspec-code` - Code blocks
- `.openspec-table` - Tables

## File Structure

```
openspec-importer/
├── openspec-importer.php          # Main plugin file
├── includes/
│   ├── class-markdown-parser.php  # YAML + Markdown parsing
│   ├── class-html-formatter.php   # HTML conversion
│   └── class-importer.php         # Import engine
├── readme.txt                     # WordPress.org readme
├── uninstall.php                  # Cleanup script
├── LICENSE                        # GPL v2
└── README.md                      # This file
```

## Changelog

### 1.0.0
- Initial release
- YAML frontmatter parsing
- Markdown to HTML conversion
- Custom post type with taxonomies
- Prism.js syntax highlighting
- Configurable documents directory

## License

GPL v2 or later. See [LICENSE](LICENSE) for more information.

## Credits

- [Prism.js](https://prismjs.com/) - Syntax highlighting
- [GitHub Markdown CSS](https://github.com/sindresorhus/github-markdown-css) - Styling inspiration
