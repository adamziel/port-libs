# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings, paragraphs,
Pandoc-style inline emphasis/strong/link/code spans, bullet lists, ordered
lists, nested lists, and definition lists. Code spans now preserve
list-marker-looking text such as `- x` and `#. x` inside imported list items.
Fenced code blocks map the upstream `test/command/indented-fences.md`
indentation-stripping behavior and render as WordPress code blocks.
Block quotes now map Pandoc's `test/testsuite.txt` block quote section,
including quoted paragraphs, nested quotes, ordered lists, and indented code
inside a quote.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, a reviewer quote, conversion steps, and a fenced PHP migration
  snippet.
- `examples/wordpress-import-markdown.php` converts that fixture to WordPress
  block comments and HTML without shelling out to pandoc.
- Definition-list support maps Pandoc `Tests.Readers.Markdown` glossary-style
  cases into `<dl>` output inside a WordPress HTML block, which is useful for
  imported FAQs, term lists, release-note metadata, and migration checklists.
- Quote support maps imported reviewer notes, citations, and legacy editorial
  callouts into core WordPress quote blocks instead of flattening them into
  paragraphs.

## Next Task

Map loose definition-list paragraphs from `Tests.Readers.Markdown` or another
bounded block family from `test/testsuite.txt`.
