# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T130434Z`
Base accepted HEAD: `09886c645688c9a166f9999aa9a328c2f8bdb025`

## Behavior Added

- `TableGeometry::reviewPacket()` now prefers Pandoc-style `captionBlocks`
  and `shortCaptionBlocks` when present instead of falling back to inline or
  scalar caption text.
- Caption records now expose block provenance, block counts, block types,
  `hasBlockContent`, JSON-safe serialized caption blocks, and `rawText` when a
  scalar fallback caption differs from the block-derived text.
- Review-packet summaries now include long and short caption block flags,
  counts, and block-type lists for importer audit queues.
- `WordPressBlockWriter` now renders block-level table captions inside the
  table `<figcaption>` while preserving the existing inline/scalar caption
  fallback path.
- The table geometry WordPress handoff example now includes a block-level
  caption table and self-tests both review-packet metadata and rendered
  figcaption HTML.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for table caption and
  table handoff behavior recorded in `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
  including the known Pandoc table caption shapes from `test/tables.native`,
  `test/tables.markdown`, `test/pipe-tables.txt`,
  `test/command/short-caption.md`, and HTML table fixtures already mapped in
  this lane.
- The local upstream Pandoc checkout was not hydrated in this isolated
  worktree, so this slice relied on accepted manifest/source rows and existing
  fixture-backed native PHP tests rather than running upstream Haskell tests.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip,
  external writer, external template engine, TeX/PDF engine, browser renderer,
  online sanitizer, or online service was executed.

## Verification

- Red-first focused check after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 478 assertions, 1 failures`.
  Failure: block caption expectations saw fallback inline caption metadata
  because `captionBlocks` was not yet serialized.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 494 assertions, 0 failures`.
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 732 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/TableGeometry.php`,
  `lanes/pandoc/src/WordPressBlockWriter.php`,
  `lanes/pandoc/tests/TableGeometryTest.php`, and
  `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `909 -> 910`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1367 -> 1368`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 91`.
- Added `mappedTableGeometryCaptionBlockCases: 1`.
- Added `tableGeometryCaptionBlockAssertions: 17`.

## Non-Overlap

This does not repeat accepted table geometry work for visual spans, colspec
preservation, row-head WordPress output, body-local head rows, section-scoped
rowspans, declared-column overflow, source coordinates, source-coordinate
shifts, occupied slots, accessibility relationships, reader packet attachment,
nested rollups, source attributes, `rowspan=0`, Markdown/RST downgrade
diagnostics, colgroup metadata, colgroup mismatch diagnostics, inline/short
caption metadata, or overfull-width diagnostics. The new behavior is bounded
block-level caption provenance and directly coupled WordPress figcaption block
rendering.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `AstNode`,
`TableGeometry`, and `WordPressBlockWriter` paths already present in the lane.
Full upstream Pandoc runner parity remains gated on hydrating the pinned
checkout and building the Haskell test executables.

## Follow-Up

Keep default accessibility emission policy, malformed source-coordinate
diagnostics, writer-specific downgrade policies beyond RST, caption AST
round-trip normalization, and full upstream Pandoc Haskell runner parity as
separate bounded slices.
