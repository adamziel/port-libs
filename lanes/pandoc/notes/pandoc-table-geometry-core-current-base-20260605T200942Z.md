# Pandoc Table Geometry Current Base - LaTeX Footer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260605T200942Z`

Accepted base: `28a3318b8df99d6bd1d9002362d2936df58d9351`

## Source Truth

The accepted static Pandoc inventory already maps native table head/body/foot
sections, LaTeX table writer handoff, and bounded table writer requirement
packets. Existing PHP support reported LaTeX `multicolumn`, `multirow`,
block-cell, and nested-table requirements, but it did not report that a
`table_foot` section needs the LaTeX longtable footer path before export.

## Implementation

- Added a table-level LaTeX writer requirement diagnostic:
  `latex-longtable-footer-required`.
- The diagnostic is emitted only for tables with actual `table_foot` rows and
  includes caption, column count, row counts, body count, foot row count, and a
  serializable section summary.
- Kept existing LaTeX span/block/nested diagnostics unchanged for tables
  without foot rows.
- Updated the WordPress table-geometry handoff smoke to verify the footer
  requirement packet and rendered `tfoot` review table.

## Focused Evidence

- Baseline focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 664 assertions, 0 failures`
- Red-first focused probe:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 665 assertions, 1 failures`
  - Failure: the new footer test expected `latex-longtable-footer-required`
    but LaTeX writer diagnostics were empty.
- Focused table geometry after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 683 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 989 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax checks:
  `php -l lanes/pandoc/src/TableGeometry.php`
  `php -l lanes/pandoc/tests/TableGeometryTest.php`
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors detected
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed

## Status Delta

- `lane-status.json` `phpPass`: `1061 -> 1062`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1514 -> 1515`
- `mappedTableGeometryCoreCases`: `6 -> 7`
- `tableGeometryCoreAssertions`: `74 -> 93`
- Added `mappedTableGeometryLatexFooterRequirementCases: 1`
- Added `tableGeometryLatexFooterRequirementAssertions: 19`

## Dependency Closure

No new support component is needed. This reuses native PHP `AstNode`,
`TableGeometry`, the WordPress table handoff example, and the focused PHP test
harness. No Pandoc, Cabal solver/build/test command, Haskell runner, stack,
external writer, TeX/PDF engine, browser renderer, online sanitizer, or online
service was executed.

## Non-Overlap And Follow-Up

This does not repeat accepted visual span layout, row-head output, body-local
head rows, section-scoped rowspans, declared-column overflow diagnostics,
source-coordinate metadata, source attributes, `rowspan=0`, colgroup
provenance/vertical alignment, inherited alignment, caption metadata,
Markdown/RST/AsciiDoc writer diagnostics, or existing LaTeX multicolumn,
multirow, block-cell, and nested-table requirement diagnostics. This slice owns
only LaTeX footer-section longtable requirement metadata.

Future table work should keep default accessibility emission policy,
caption/short-caption writer edge cases, col-width formatting, full HTML5 table
algorithm parity, DOCX/ODT table-style column inheritance, and upstream Haskell
runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
