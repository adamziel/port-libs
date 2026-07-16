# Pandoc Markdown Writer Table Caption Span Completion

Snapshot: 2026-06-15T16:04Z rebased onto `origin/main`
`b6a4aca750`.

Scope: native PHP Markdown writer table, caption, and span completion. No
Pandoc, cmark, Node tooling, browser renderer, online service, or external
validator was invoked.

## Coverage Added

- Added 53 additional upstream-mapped Markdown writer table/caption/span cases
  to `lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php`.
- Covered `table_body` `headRowCount` promotion, direct `table_row` children as
  body rows, explicit `headRows` duplicate avoidance, text-only table-cell
  newline and delimiter normalization, hard/soft break normalization in table
  captions and short captions, caption block flattening, and bracketed/semantic
  span output for emoji, raw, math, citation, image, link, quote, script, and
  note-bearing spans.
- Hardened Markdown table-cell and caption rendering without changing the
  default pipe-table output for existing simple tables.

## Verification

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php`
  passed 1 file, 131 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterTablesSurgeTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 2 files, 7150 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 63 files, 96519
  assertions, 0 failures.
