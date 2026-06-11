# Pandoc JSON/native table helper native payloads

Slice: `plib-3gwo3`

The JSON and native readers now retain the exact native helper payloads used by
table body and cell helper constructors:

- `RowHeadColumns` on table bodies as `rowHeadColumnsNative`
- table-cell alignment constructors as `alignmentNative`
- `RowSpan` as `rowSpanNative`
- `ColSpan` as `colSpanNative`

The JSON and native writers reuse those payloads only when they still match the
current shared-AST values. Edited row-head, alignment, row-span, or col-span
values regenerate fresh constructor payloads, so stale native provenance cannot
hide reviewer edits.

Verification:

- `php -l` on touched reader, writer, and focused test files
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed 1 file, 936 assertions, 0 failures on `0b4dca730`
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files, 64,653
  assertions, 0 failures on `0b4dca730`
