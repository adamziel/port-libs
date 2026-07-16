# Pandoc Table Geometry Current-Base WordPress Missing-Cell Placeholder Slice

Slice: `pandoc-table-geometry-core-current-base-20260608T134021Z`
Base: `c09710161ff2cdca8a8469de31dd5d314260fa0c`
Date: 2026-06-08 UTC

## Scope

Implemented one bounded table-geometry handoff behavior: `WordPressBlockWriter` can now opt in to preserving flat-grid missing visual slots as inert empty `<td>` placeholders. The default WordPress output remains unchanged. Covered rowspan/colspan slots are still represented by their span anchors and are not synthesized as missing cells.

This consumes the existing `TableGeometry::sectionGrid()` / `flatGridFallbacks` metadata for the missing-slot fallback path identified by earlier table geometry notes. It does not run Pandoc, Cabal/Haskell runners, external writers, browser renderers, online services, live provider tests, or live-service provider tests.

## Behavior

- Added `WordPressBlockWriter(['preserveTableMissingCells' => true])`.
- For each table section row, the writer uses table geometry grid slots to insert `<td data-pandoc-missing-cell="true" data-pandoc-missing-row="..." data-pandoc-missing-column="..." aria-hidden="true"></td>` only where the visual grid slot is `missing`.
- Default writer construction still omits placeholders, preserving existing HTML output for ordinary conversions.
- Added a focused PHP test proving three missing visual slots are preserved for a ragged four-column table while covered columns 0 and 1 remain span-backed.
- Extended `wordpress-table-geometry-handoff.php --self-test` with the same opt-in WordPress smoke.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 2137 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` failed with `1 test files, 1646 assertions, 1 failures` because the opt-in writer produced `0` missing-cell placeholders.
- Final focused table test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1651 assertions, 0 failures`.
- Final combined focused table evidence: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 2147 assertions, 0 failures`.
- Lane counters: `phpPass` moves from `1655` to `1656`; `benchmarkDenominator.mapped` moves from `2075` to `2076`; `mappedTableGeometryCoreCases` moves from `9` to `10`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `TableGeometry`, `WordPressBlockWriter`, `TableGeometryTest.php`, and `wordpress-table-geometry-handoff.php`. Full upstream Pandoc runner parity remains out of scope for this isolated support-library slice.

## Non-Overlap

This does not modify prior table geometry behaviors for visual spans, row-head columns, section-scoped rowspans, overflow diagnostics, review packets, footer writer diagnostics, block-cell metadata, abbreviation/source-summary metadata, row group/global coordinates, reader packet attachment, source scope rowgroup/colgroup, header axis, decimal alignment, flat grid metadata, or `flatGridFallbacks` diagnostics. It is the first bounded WordPress writer consumer for missing flat-grid visual slots.

## Next

A follow-up table-geometry slice should choose a distinct consumer, such as covered-slot anchor replay for a specific importer/writer path or target-specific raw HTML fallback diagnostics. Do not shell out to Pandoc, Cabal/Haskell runners, external writers, browser renderers, online services, or live-service provider tests.
