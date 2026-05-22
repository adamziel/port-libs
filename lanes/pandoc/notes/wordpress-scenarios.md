# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings, paragraphs,
Pandoc-style inline emphasis/strong/link/code spans, bullet lists, and ordered
lists.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, and conversion steps.
- `examples/wordpress-import-markdown.php` converts that fixture to WordPress
  block comments and HTML without shelling out to pandoc.

## Next Task

Add nested list blocks and begin mapping Pandoc reader golden cases into PHP
fixtures.
