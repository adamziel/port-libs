# Pandoc Table Geometry Current Base - Underfull Widths

Slice: `pandoc-table-geometry-core-current-base-20260605T154911Z`

Accepted base: `2069ed7e1febba5c2afce1b99c380343613b723c`

## Source Truth

Pandoc table widths are relative source metadata. The native PHP handoff should
not silently lose complete width lists that add up to less than the full table
width, because WordPress review packets need to distinguish source underfill
from partial or invalid width declarations while preserving the original
colgroup percentages for visible output.

## Implementation

- `TableGeometry::columnWidthSummary()` now exposes `underflowAmount` for
  complete positive relative widths below `1.0`.
- `TableGeometry::diagnostics()` now emits
  `table-widths-underfill-full-width` with `widthTotal`, `underflowAmount`,
  `normalizedWidths`, and source `percentWidths`.
- The underfull diagnostic is suppressed when structural column diagnostics
  already exist, preserving existing malformed colgroup mismatch behavior.
- `wordpress-table-geometry-handoff.php --self-test` now includes an underfull
  source-width review table and verifies that the original WordPress colgroup
  output remains unchanged.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 845 assertions, 0 failures`
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 614 assertions, 1 failures`
  - Failed on missing `underflowAmount` metadata before the source change.
- Green: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 868 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors
  - `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`: passed

## Status Delta

- `lane-status.json` `phpPass`: `982` to `983`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1437` to `1438`
- `mappedTableGeometryCoreCases`: `6` to `7`
- `tableGeometryCoreAssertions`: `74` to `97`
- New native case markers:
  - `mappedTableGeometryUnderfullWidthCases`: `1`
  - `tableGeometryUnderfullWidthAssertions`: `23`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`AstNode`, `TableGeometry`, `MarkdownReader`, `WordPressBlockWriter`, and table
review-packet support paths. No Pandoc, Cabal solver/build/test command,
Haskell runner, external writer, Word, LibreOffice, zip/unzip, browser renderer,
online sanitizer, or online service was executed.

## Non-Overlap And Follow-Up

This slice does not overlap the recent table geometry section-boundary,
declared-column overflow, nested-table, accessibility-header, RST/AsciiDoc
handoff, or block-cell content slices. It covers only complete underfull source
width metadata and WordPress colgroup preservation.

Future table work should keep automatic width repair, CSS table layout/cascade,
richer DOCX/ODT table style rendering, target-specific writer width policies,
and full upstream Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
