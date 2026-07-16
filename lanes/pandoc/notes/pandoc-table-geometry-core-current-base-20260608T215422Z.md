# Pandoc Table Geometry Current-Base Height Layout

Slice: `pandoc-table-geometry-core-current-base-20260608T215422Z`
Base accepted HEAD: `d291953d10cb3a81d9c31878d6d7b3226cc33af0`

## Behavior

- Added bounded native HTML table `height` layout provenance to `TableGeometry::tableLayoutMetadata()`.
- Normalizes safe legacy `height` values as positive pixel integers (`1` through `9999`) or percentages (`> 0` through `100%`), matching the existing `width` contract.
- Emits `tableHeight`, `tableHeightType`, and `heightValue` in review packets and summary metadata.
- Adds `table-layout-height` downgrade diagnostics for Markdown, AsciiDoc, and LaTeX writers because these handoffs require raw HTML or reviewer handling.
- Preserves validated `height` attributes in WordPress table output while dropping unsafe values such as CSS expressions.

## Evidence

- Rework notes: none found for `port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 674 assertions, 0 failures`
- Red-first after adding the focused height test:
  - `1 test files, 679 assertions, 1 failures`
  - Failure: missing `height` in `tableLayout` attributes.
- Final focused reader handoff: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 703 assertions, 0 failures`
- Final focused table geometry family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 2382 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `1895 -> 1896`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator `2317 -> 2318`, `mappedTableGeometryCoreCases` `9 -> 10`, `tableGeometryCoreAssertions` `155 -> 184`.

## Dependency Closure

No new support component is needed. This reuses existing MarkdownReader HTML attribute capture, TableGeometry layout metadata, and WordPressBlockWriter safe HTML-attribute replay. No Pandoc, Cabal, Haskell runner, external writer, Word, LibreOffice, zip/unzip, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids the accepted table geometry clusters for width layout, placement alignment, frame/rules/border, cellpadding/cellspacing, directionality, captions, spans, row groups, header associations, colgroup alignment, footer sections, block-cell content, nested tables, empty tables, and WordPress covered/missing visual-slot fallback behavior.

## Follow-Up

Next table-geometry work can target non-overlapping table background provenance, importer-side raw-HTML fallback diagnostics, or another bounded layout attribute not already covered by the accepted table layout and legacy attribute slices.
