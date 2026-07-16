# Pandoc Table Geometry Current Base - Vertical Alignment Handoff

Slice: `pandoc-table-geometry-core-current-base-20260605T190103Z`

Accepted base: `6eabc470c32c0f122118ac788fbbcb8021d0420e`

## Source Truth

Pandoc table imports preserve cell-level vertical alignment from HTML and
DocBook source tables. Native table geometry handoff must not lose that
metadata when WordPress review packets and table-cell output are generated
without invoking Pandoc or external document converters.

## Implementation

- `MarkdownReader` now normalizes HTML `valign` and CSS `vertical-align` from
  cells, rows, row groups, and tables into `table_cell` `valign` metadata.
- DocBook `entry valign` is now normalized into the same `table_cell`
  metadata.
- `TableGeometry` now exposes `verticalAlignment` in cell coverage records and
  section grid cell slots.
- `WordPressBlockWriter` emits normalized table-cell `vertical-align` styles
  unless raw HTML source `valign` or source `vertical-align` style already owns
  the output.
- The WordPress table-geometry handoff example now includes an HTML vertical
  alignment review table and verifies AST, packet, and WordPress output.

## Focused Evidence

- Baseline focused table geometry run:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 930 assertions, 0 failures`
- Green focused table geometry run:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 949 assertions, 0 failures`
- Coupled reader/writer verification:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - `1 test files, 2980 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors
  - `php -l lanes/pandoc/src/MarkdownReader.php`: no syntax errors
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: no syntax errors
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors

## Status Delta

- `lane-status.json` `phpPass`: `1044` to `1045`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1496` to `1497`
- `mappedTableGeometryCoreCases`: `6` to `7`
- `tableGeometryCoreAssertions`: `74` to `93`
- New native case markers:
  - `mappedTableGeometryVerticalAlignmentCases`: `1`
  - `tableGeometryVerticalAlignmentAssertions`: `19`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`MarkdownReader`, `AstNode`, `TableGeometry`, and `WordPressBlockWriter`
support paths. No Pandoc, Cabal solver/build/test command, Haskell runner,
Word, LibreOffice, external writer, browser renderer, online sanitizer, or
online service was executed.

## Non-Overlap And Follow-Up

This slice does not overlap the accepted table geometry section-boundary,
declared-column overflow, nested-table, accessibility-header, RST/AsciiDoc/
LaTeX writer requirement, underfull-width, overfull-width, block-cell content,
caption metadata, colgroup provenance, or inherited horizontal-alignment
slices. It covers only bounded HTML and DocBook vertical alignment handoff for
native review packets and WordPress table-cell output.

Future table work should keep CSS writing-mode, full stylesheet cascade,
col/colgroup-to-cell cascade details, richer DOCX/ODT table-style rendering,
and full upstream Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
