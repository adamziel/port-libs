# Pandoc Table Geometry Current Base - Colgroup Vertical Alignment

Slice: `pandoc-table-geometry-core-current-base-20260605T193321Z`

Accepted base: `6ff88ec34ca05033c964fe86bcd7b8e0e8bce591`

## Source Truth

Pandoc's native table model carries cell alignment metadata through reader and
writer handoff. The accepted PHP table-geometry path already preserved
cell/row/section/table vertical alignment; this slice closes the bounded HTML
`colgroup`/`col` column metadata gap so imported column-level `valign` and
CSS `vertical-align` do not disappear before WordPress review output.

## Implementation

- `MarkdownReader` now reads bounded `valign` and CSS `vertical-align` from
  HTML `colgroup` and `col` elements.
- Expanded column metadata now includes `verticalAlignment` provenance in
  `columnSources`, `columns[*].source`, and grouped column-source packets.
- Column-level vertical alignment is applied to cells that do not already have
  explicit cell, row, row-group, or table vertical alignment metadata.
- The WordPress table-geometry handoff example now verifies colgroup-derived
  vertical alignment in the AST, review packet, grouped column provenance, and
  rendered table-cell styles.

## Focused Evidence

- Red-first probe:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Failed before implementation with `Expected: 'bottom' Actual: NULL` for a
    colgroup-derived vertical-alignment cell assertion.
- Focused table geometry:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 306 assertions, 0 failures`
- Focused table family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/TableGeometryTest.php`
  - `2 test files, 970 assertions, 0 failures`
- Coupled reader/table regression:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - `3 test files, 3950 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownReader.php`: no syntax errors
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors
- Metadata and diff hygiene:
  - JSON decode check for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `pandoc json ok`
  - `git diff --check -- lanes/pandoc`: passed with no output

## Status Delta

- `lane-status.json` `phpPass`: `1050` to `1051`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1503` to `1504`
- New native case markers:
  - `mappedTableGeometryColumnVerticalAlignmentCases`: `1`
  - `tableGeometryColumnVerticalAlignmentAssertions`: `21`

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader`, `AstNode`, `TableGeometry`, `WordPressBlockWriter`,
table-geometry handoff example, and focused PHP test harness. No Pandoc, Cabal
solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip,
external writer, browser renderer, JavaScript, online sanitizer, or online
service was executed.

## Non-Overlap And Follow-Up

This slice does not overlap the accepted cell/row/section/table vertical
alignment slice, section-boundary rowspan, declared-column overflow,
nested-table, accessibility-header, RST/AsciiDoc/LaTeX writer requirement,
block-cell content, caption metadata, colgroup provenance, or inherited
horizontal-alignment slices. It covers only bounded HTML `colgroup`/`col`
vertical-alignment expansion into native table geometry and WordPress output.

Future table work should keep full CSS cascade and specificity, writing-mode
and baseline layout, DOCX/ODT table-style column inheritance, and full upstream
Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
