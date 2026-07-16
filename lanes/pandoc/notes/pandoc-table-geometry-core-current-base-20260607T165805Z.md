# Pandoc Table Geometry Cell Width Coverage

Slice: `pandoc-table-geometry-core-current-base-20260607T165805Z`
Base: `df6c68d729948cf7d00f374cd89c93adb4cbf204`
Date: 2026-06-07 UTC

## Behavior

- `TableGeometry::cellCoverage()` now serializes per-cell `normalizedWidths`, `percentWidths`, `widthTotal`, `normalizedWidthTotal`, `percentWidthTotal`, `widthColumnCount`, `missingWidthColumnCount`, `hasCompleteWidths`, and `hasPartialWidths`.
- `TableGeometry::reviewPacket()` carries those additive coverage fields through importer review packets so colspanned cells can be audited without recomputing column specs.
- The WordPress table geometry handoff example keeps rendering unchanged and checks the review-packet width metadata in its local self-test.

## Source Truth

- Reuses the existing mapped Pandoc table geometry fixture surface for pipe/simple/html table `ColSpec` and row-span/column-span handoff behavior.
- No Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external writers, browser renderers, online services, live provider tests, or live-service provider tests were executed.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1322 assertions, 0 failures`.
- Baseline family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1663 assertions, 0 failures`.
- Red-first: after adding focused assertions, `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` failed with `1 test files, 1297 assertions, 1 failures` because `normalizedWidths` was absent from coverage records.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1338 assertions, 0 failures`.
- Final family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1679 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- Syntax checks: `php -l lanes/pandoc/src/TableGeometry.php`, `php -l lanes/pandoc/tests/TableGeometryTest.php`, and `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php` reported no syntax errors.
- Metadata checks: lane JSON decoded successfully and `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Lane `phpPass`: `1537 -> 1538`.
- Manifest mapped denominator: `1956 -> 1957`.
- Table geometry mapped core cases: `8 -> 9`.
- Table geometry focused assertions: `143 -> 159`.
- Focused assertion growth: `TableGeometryTest.php` `1322 -> 1338`, table-family check `1663 -> 1679`.

## Non-Overlap

This slice does not repeat accepted table geometry source-header metadata, duplicate header ids, header abbreviation review metadata, row-group ranges, global row coordinates, block-cell content handoff, footer-section diagnostics, nested-table diagnostics, or RST grid-table writer requirements. It owns only additive per-cell width-span metadata in coverage and review packets.

## Dependency Closure

No new support component is needed. The slice reuses native `TableGeometry` column-spec normalization, existing AST nodes, `MarkdownReader`, `WordPressBlockWriter`, focused table geometry tests, and the lane-local WordPress table geometry handoff example.

## Follow-Up

Next non-overlapping table geometry work should target source row/column provenance from DOCX/ODT/HTML readers, caption placement edge cases, or remaining writer-specific span diagnostics without invoking external conversion tools.
