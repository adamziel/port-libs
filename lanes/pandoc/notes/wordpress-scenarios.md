# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings, paragraphs,
Pandoc-style inline emphasis/strong/link/code spans, bullet lists, ordered
lists, nested lists, and definition lists. Code spans now preserve
list-marker-looking text such as `- x` and `#. x` inside imported list items.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, and conversion steps.
- `examples/wordpress-import-markdown.php` converts that fixture to WordPress
  block comments and HTML without shelling out to pandoc.
- Definition-list support maps Pandoc `Tests.Readers.Markdown` glossary-style
  cases into `<dl>` output inside a WordPress HTML block, which is useful for
  imported FAQs, term lists, release-note metadata, and migration checklists.

## Next Task

Map block quotes, fenced code blocks, or loose definition-list paragraphs from
`Tests.Readers.Markdown`.
