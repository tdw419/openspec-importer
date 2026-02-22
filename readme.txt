=== OpenSpec Importer ===
Contributors: geometryos
Tags: markdown, specifications, documents, import, yaml, frontmatter, syntax-highlighting
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import OpenSpec markdown documents with YAML frontmatter into WordPress as a searchable knowledge base.

== Description ==

OpenSpec Importer transforms your markdown specification documents into a beautiful, searchable WordPress knowledge base. Perfect for development teams, project managers, and anyone who wants to publish technical specifications.

**Features:**

* **YAML Frontmatter Parsing** - Extracts metadata from document headers
* **Markdown to HTML** - Full markdown conversion including tables, lists, and code blocks
* **Custom Post Type** - Documents stored as `openspec_doc` with dedicated archive
* **Document Taxonomies** - Automatic categorization by type (requirements, design, tasks, proposals)
* **Project Organization** - Documents grouped by project
* **Prism.js Integration** - Syntax highlighting for 12+ programming languages
* **Smart Updates** - Only re-imports changed documents
* **Duplicate Detection** - Prevents duplicate imports

**Supported Document Types:**

* Requirements documents
* Design specifications
* Task lists
* Proposals
* Research notes
* Change logs

**Markdown Features:**

* Headers (H1-H6)
* Tables with styling
* Ordered and unordered lists
* Task lists with checkboxes
* Code blocks with syntax highlighting
* Inline code, bold, italic, strikethrough
* Links and images
* Blockquotes
* Horizontal rules

== Installation ==

1. Upload the `openspec-importer` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > OpenSpec Importer** to configure the documents directory
4. Navigate to **OpenSpec Docs** in the admin sidebar to import

== Frequently Asked Questions ==

= What is OpenSpec? =

OpenSpec is a convention for organizing specification documents in markdown format with YAML frontmatter. Documents are typically organized in directories like `specs/`, `changes/`, and `proposals/`.

= What is YAML frontmatter? =

YAML frontmatter is metadata at the top of a markdown file, enclosed in `---` delimiters:

`
---
title: My Document
phase: design
status: draft
---
# Content starts here
`

= Can I use this without OpenSpec? =

Yes! The plugin works with any directory containing markdown files. Just set your custom path in Settings.

= What languages are supported for syntax highlighting? =

Python, Bash, JavaScript, Rust, PHP, YAML, JSON, Markdown, TypeScript, WGSL, and GLSL.

= How does duplicate detection work? =

The plugin generates a unique document ID from the file path. If a document with that ID already exists, it will be skipped or updated (if the file has changed).

= Can I customize the styling? =

Yes! The plugin uses CSS classes prefixed with `openspec-` that can be overridden in your theme. See the README for details.

== Screenshots ==

1. **Admin Import Page** - Import documents with one click
2. **Document Preview** - Test parse to see formatting
3. **Document Archive** - Custom post type archive view
4. **Single Document** - Formatted specification display
5. **Settings Page** - Configure import directory

== Changelog ==

= 1.0.0 =
* Initial release
* YAML frontmatter parsing
* Markdown to HTML conversion with tables and lists
* Custom post type (openspec_doc)
* Document type and project taxonomies
* Prism.js syntax highlighting
* Settings page for configurable directory
* Smart update detection

== Upgrade Notice ==

= 1.0.0 =
Initial release. Welcome to OpenSpec Importer!

== Privacy ==

This plugin processes markdown files stored locally on your server. No data is sent to external services. All imported documents are stored as WordPress posts in your database.

== Credits ==

* [Prism.js](https://prismjs.com/) - Syntax highlighting
* [GitHub Markdown CSS](https://github.com/sindresorhus/github-markdown-css) - Styling inspiration
