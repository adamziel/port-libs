# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T115757Z`
Base accepted HEAD: `72b10b26c9b892a4b2bc30e8501676c9ce4c2557`

## Behavior Added

- `TableGeometry::cellCoverage()` now records `sourceEndColumn`,
  `sourceColumns`, and `visualShift` for each covered table cell.
- `TableGeometry::reviewPacket()` summaries now include
  `hasSourceCoordinateShifts`, `sourceCoordinateShiftCount`, and
  `maxVisualShift`.
- The new focused table fixture covers an implicit-rowspan handoff where source
  physical columns `0` and `1` render at visual columns `2` and `3` because an
  earlier `rowspan` cell still occupies the leading visual columns.
- The WordPress table geometry handoff smoke verifies the review-packet shift
  metadata while keeping the rendered WordPress table HTML unchanged.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for Pandoc table
  coordinate and table handoff behavior recorded in
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, including `test/pipe-tables.txt`,
  `test/tables.markdown`, `test/tables.native`, `test/html-reader.html`,
  `test/html-reader.native`, and `test/tables/nordics.html5`.
- The local upstream Pandoc checkout was not hydrated in this isolated
  worktree, so this slice relied on accepted manifest/source rows and existing
  fixture-backed native PHP tests rather than running upstream Haskell tests.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip,
  external template engine, TeX/PDF engine, browser renderer, external writer,
  online sanitizer, or online service was executed.

## Verification

- Red-first focused check after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 457 assertions, 1 failures`.
  Failure: missing `hasSourceCoordinateShifts` review-packet summary metadata.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 477 assertions, 0 failures`.
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 667 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/TableGeometry.php`,
  `lanes/pandoc/tests/TableGeometryTest.php`, and
  `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `881 -> 882`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1339 -> 1340`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 102`.
- Added `mappedTableGeometrySourceCoordinateShiftCases: 1`.
- Added `tableGeometrySourceCoordinateShiftAssertions: 28`.

## Non-Overlap

This does not repeat accepted table geometry work for visual spans, colspec
preservation, row-head WordPress output, body-local head rows, section-scoped
rowspans, declared-column overflow, source coordinates, occupied slots,
accessibility relationships, reader packet attachment, nested rollups, source
attributes, `rowspan=0`, Markdown/RST downgrade diagnostics, colgroup
metadata, colgroup mismatch diagnostics, long/short caption metadata, or
overfull-width diagnostics. The new behavior is bounded source-to-visual
coordinate shift metadata for implicit-rowspan handoffs where source physical
columns differ from visual output columns without requiring an overflow
diagnostic.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `AstNode`,
`TableGeometry`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter` paths already present in the lane. Full upstream Pandoc
runner parity remains gated on hydrating the pinned checkout and building the
Haskell test executables.

## Follow-Up

Keep default accessibility emission policy, malformed source-coordinate
diagnostics for truly overfull source tables, richer writer-specific downgrade
policies, block-level caption provenance, and full upstream Pandoc Haskell
runner parity as separate bounded slices.
