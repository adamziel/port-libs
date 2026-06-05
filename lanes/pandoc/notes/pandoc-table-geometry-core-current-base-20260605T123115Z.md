# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T123115Z`
Base accepted HEAD: `d75f7b1237c572201f69635bb0607f52717991ff`

## Behavior Added

- Added `TableGeometry::columnGroups()` to group adjacent visual columns that
  came from the same source HTML `<colgroup span>` or child `<col span>`.
- `TableGeometry::reviewPacket()` now includes `columnGroups` and summary
  fields `columnGroupCount` / `hasColumnGroups`.
- Column-group records preserve visual column ranges, source span offsets,
  repeated alignments/widths, declared-column flags, and sanitized source
  provenance without duplicating per-column `column` / `spanOffset` noise.
- The WordPress table handoff smoke now verifies grouped source-span metadata
  while keeping rendered table HTML unchanged.

## Source Truth

- Uses accepted pinned Pandoc static inventory rows for structured HTML table
  parsing and handoff: `test/html-reader.html`, `test/html-reader.native`, and
  `test/tables/nordics.html5` table cases with caption, colgroup widths,
  table sections, row-header cells, and spans.
- This is bounded native PHP support-library behavior. No Pandoc, Cabal build,
  Haskell runner, Word, LibreOffice, zip/unzip, external writer, browser
  renderer, online sanitizer, or online service was executed.

## Verification

- Baseline focused family before change:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 667 assertions, 0 failures`.
- Red-first focused reader check after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 178 assertions, 2 failures`.
  Failure: `columnGroups` was absent from review packets.
- Focused reader after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 238 assertions, 0 failures`.
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 715 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/TableGeometry.php`,
  `lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`, and
  `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `895 -> 896`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1352 -> 1353`.
- Focused table family assertions: `667 -> 715`.
- Added `mappedTableGeometryColumnGroupCases: 1`.
- Added `tableGeometryColumnGroupAssertions: 48`.

## Non-Overlap

This does not repeat accepted table geometry work for visual spans, colspec
preservation, row-head WordPress output, body-local head rows, section-scoped
rowspans, declared-column overflow diagnostics, source-coordinate shift
metadata, occupied slots, accessibility relationships, reader-attached review
packets, nested rollups, source attributes, `rowspan=0`, Markdown/RST
downgrade diagnostics, HTML colgroup width/alignment expansion, per-column
colgroup provenance, colgroup mismatch diagnostics, long/short caption
metadata, or overfull-width diagnostics. The new behavior is grouped
source-span metadata for adjacent HTML colgroup/col declarations already
expanded into visual columns.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`MarkdownReader`, `AstNode`, `TableGeometry`, and `WordPressBlockWriter`
paths already present in the lane. Full upstream Pandoc runner parity remains
gated on hydrating the pinned checkout and building the Haskell test
executables.

## Follow-Up

Keep default accessibility emission policy, richer non-Markdown writer
downgrade policies, full HTML5 table algorithm parity, and upstream Pandoc
Haskell runner parity as separate bounded slices.
