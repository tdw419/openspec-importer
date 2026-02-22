# OpenSpec Importer

Import OpenSpec documents (requirements, design, tasks, proposals) from the `openspec/` folder into WordPress as formatted posts with syntax highlighting.

## Installation

1. Copy `openspec-importer` folder to `wp-content/plugins/`
2. Activate plugin in WordPress admin
3. Navigate to "OpenSpec Docs" menu item

## Requirements

- PHP 8.0+
- WordPress 6.0+
- Filesystem access to the OpenSpec directory

## Configuration

By default, the plugin looks for OpenSpec documents at:
```
~/zion/projects/geometry_os/geometry_os/openspec/
```

You can override this by setting the `GEOMETRY_OS_ROOT` environment variable:
```bash
export GEOMETRY_OS_ROOT=/path/to/geometry_os/geometry_os
```

## Usage

1. Go to **OpenSpec Docs** in WordPress admin
2. Click **Import All Documents**
3. Documents are created as `openspec_doc` custom post type

## Document Types

The importer automatically categorizes documents based on filename and path:

| Type | Color | Pattern |
|------|-------|---------|
| Requirements | Blue | `*requirements*.md`, frontmatter `phase: requirements` |
| Design | Green | `*design*.md`, frontmatter `phase: design` |
| Tasks | Orange | `*tasks*.md`, frontmatter `phase: tasks` |
| Proposal | Purple | `*proposal*.md`, `/proposals/` path |
| Spec | Cyan | `*spec*.md`, `/specs/` path |
| Research | Brown | `*research*.md` |
| Change | Red | `/changes/` path |
| Archived | Gray | `/archive/` path |

## Custom Post Type

Documents are stored as `openspec_doc` custom post type with:

- Title from frontmatter `title`, first heading, or filename
- Content formatted with Prism.js syntax highlighting
- YAML frontmatter preserved and displayed

### Post Meta

Each imported document has:
- `_openspec_document_id` - Unique ID derived from path
- `_openspec_filepath` - Absolute file path
- `_openspec_relative_path` - Path from openspec root
- `_openspec_type` - Document type (requirements, design, etc.)
- `_openspec_filemtime` - File modification time
- `_openspec_imported_at` - Import timestamp
- `_openspec_frontmatter` - JSON-encoded frontmatter

### Taxonomies

- `openspec_type` - Document type taxonomy
- `openspec_project` - Project name from path

## Features

- Parses YAML frontmatter
- Markdown to HTML conversion
- Prism.js syntax highlighting for code blocks
- Automatic duplicate detection (skips unchanged files)
- Update detection (updates posts when files change)
- Table formatting
- Task list checkboxes
- Type-based categorization

## Frontmatter Support

The parser recognizes standard YAML frontmatter:

```yaml
---
spec: my-spec-name
phase: requirements
created: 2026-02-21
title: My Spec Title
---

# Content starts here
```

Supported frontmatter fields:
- `title` - Document title
- `phase` - Document type (requirements, design, tasks)
- `spec` - Spec identifier
- `created` - Creation date
- Any custom fields are preserved

## REST API

OpenSpec documents are available via WordPress REST API:

```bash
# List all documents
curl http://localhost:8080/wp-json/wp/v2/openspec_doc

# Get specific document
curl http://localhost:8080/wp-json/wp/v2/openspec_doc/123

# Filter by type
curl "http://localhost:8080/wp-json/wp/v2/openspec_doc?openspec_type=requirements"

# Filter by project
curl "http://localhost:8080/wp-json/wp/v2/openspec_doc?openspec_project=my-project"
```

## Development

### File Structure

```
openspec-importer/
├── openspec-importer.php      # Main plugin file
├── README.md                   # This file
└── includes/
    ├── class-markdown-parser.php  # YAML + Markdown parser
    ├── class-html-formatter.php   # HTML formatter with syntax highlighting
    └── class-importer.php         # Import logic with duplicate detection
```

### Customization

To customize the OpenSpec directory path, filter:

```php
add_filter('openspec_directory', function($path) {
    return '/custom/path/to/openspec/';
});
```

## Related

- [Claude Conversations Importer](../claude-conversations/) - Import Claude CLI sessions
- [Geometry OS WebMCP](../geometry-os-webmcp/) - WebMCP integration
