# Pandoc Markdown Writer Table Caption Span Surge

Snapshot: 2026-06-15T15:03:29Z on `origin/main` `4db7931e23`.

Scope: native PHP Markdown writer table/caption/span completion. No Pandoc,
cmark, Node tooling, browser renderer, online service, or external validator was
invoked.

## Coverage Added

- Added `lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php` with 60
  upstream-mapped Markdown writer cases.
- Covered pipe-table alignment and relative width delimiters, caption and short
  caption forms, table attributes, headerless/body-head/footer section
  degradation, generic bracketed spans, semantic spans, abbreviation definitions,
  and table-cell pipe escaping for text, code, links, images, spans, raw
  markdown, raw HTML, math, and citations.
- Hardened Markdown pipe-table cell rendering so inline-rich cells pass through
  the same unescaped pipe delimiter guard as block-rich cells.

## Verification

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php`
  passed 1 file, 66 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 2 files, 7085 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 47 files, 90436
  assertions, 0 failures.
