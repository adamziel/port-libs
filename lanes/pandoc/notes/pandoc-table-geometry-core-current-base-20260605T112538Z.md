# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T112538Z`
Base accepted HEAD: `68d3532d2b6b3f3f64194412562f0acd10cc9b73`

## Behavior Added

- Added `TableGeometry::columnWidthSummary()` for JSON-safe table width audits.
- `TableGeometry::columnSpecs()` now includes per-column `normalizedWidth` and
  `percentWidth` metadata while preserving the existing raw `width` value.
- `TableGeometry::diagnostics()` now reports
  `table-widths-exceed-full-width` when positive source colspec widths add up
  past a full table width.
- `TableGeometry::reviewPacket()` now carries `widthSummary`, so WordPress
  import review queues can see raw, normalized, percent, missing-column, and
  overfull-width state without changing rendered table HTML.
- The WordPress table geometry handoff smoke verifies the overfull-width review
  packet and keeps that synthetic audit table out of normal rendered output.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for Pandoc table
  colspec widths and table handoff behavior recorded in
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, including `test/pipe-tables.txt`,
  `test/tables.markdown`, `test/tables.native`, `test/html-reader.html`,
  `test/html-reader.native`, and `test/tables/nordics.html5`.
- The local upstream checkout was not hydrated in this isolated worktree or the
  shared upstream cache, so this slice relied on accepted manifest/source rows
  and existing fixture-backed native PHP tests rather than running upstream
  Haskell tests.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip,
  external template engine, TeX/PDF engine, browser renderer, external writer,
  online sanitizer, or online service was executed.

## Verification

- Baseline before change:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 619 assertions, 0 failures`.
- Red-first focused check after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 429 assertions, 1 failures`.
  Failure: missing `PortLibs\Pandoc\TableGeometry::columnWidthSummary()`.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 449 assertions, 0 failures`.
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 639 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  -> `20 test files, 10844 assertions, 0 failures`.
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

- `lanes/pandoc/lane-status.json` `phpPass`: `864 -> 865`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1322 -> 1323`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 94`.
- Added `mappedTableGeometryOverfullWidthCases: 1`.
- Added `tableGeometryOverfullWidthAssertions: 20`.

## Non-Overlap

This does not repeat accepted table geometry work for visual spans, colspec
preservation, row-head WordPress output, body-local head rows, section-scoped
rowspans, declared-column overflow, source coordinates, occupied slots,
accessibility relationships, reader packet attachment, nested rollups, source
attributes, `rowspan=0`, Markdown/RST downgrade diagnostics, colgroup metadata,
colgroup mismatch diagnostics, or long/short caption metadata. The new behavior
is bounded relative-width normalization and overfull-width diagnostics inside
the table geometry review-packet contract.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `AstNode`,
`TableGeometry`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`
paths already present in the lane. Full upstream Pandoc runner parity remains
gated on hydrating the pinned checkout and building the Haskell test
executables.

## Follow-Up

Keep default accessibility emission policy, malformed source-coordinate
normalization, richer writer-specific downgrade policies, block-level caption
provenance, and full upstream Pandoc Haskell runner parity as separate bounded
slices.
