# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T105319Z`
Base accepted HEAD: `2c28766f97fc2cabb135620f3384effb2a2c3d2b`

## Behavior Added

- `TableGeometry::reviewPacket()` now includes JSON-safe `captions.long` and `captions.short` records while preserving the existing top-level `caption` field.
- Caption records prefer `captionInlines` and `shortCaptionInlines` when present, preserve scalar fallback text otherwise, expose inline counts/types/formatting flags, and serialize nested inline metadata such as link URL/title without leaking `AstNode` references.
- Review-packet summaries now expose `hasCaption`, `hasShortCaption`, `captionInlineTypes`, and `shortCaptionInlineTypes`.
- The WordPress table geometry handoff example now includes a formatted long caption plus short caption path and self-tests the review-packet metadata and rendered block output.

## Source Truth

- This slice maps the existing pinned Pandoc table inventory rows around table captions and short captions, including `test/command/short-caption.md` and the table/caption rows already recorded in `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- The local upstream checkout path was not present in this isolated worktree, so this patch relied on accepted manifest/source rows and existing fixture-backed native PHP tests rather than running upstream Haskell tests.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, external writer, online sanitizer, or online service was executed.

## Verification

- Baseline before change: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` -> `1 test files, 407 assertions, 0 failures`.
- Red-first after adding caption metadata expectations: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` -> `1 test files, 409 assertions, 1 failures`; failure was missing `captions.long.text` metadata.
- Focused after implementation: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` -> `1 test files, 429 assertions, 0 failures`.
- Focused table-geometry family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` -> `2 test files, 619 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` -> `table geometry handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/TableGeometry.php`, `lanes/pandoc/tests/TableGeometryTest.php`, and `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `847` -> `848`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1306` -> `1307`.
- `mappedTableGeometryCoreCases`: `6` -> `7`.
- `tableGeometryCoreAssertions`: `74` -> `96`.
- Added `mappedTableGeometryCaptionMetadataCases: 1` and `tableGeometryCaptionMetadataAssertions: 22`.

## Non-Overlap

This patch does not repeat accepted table geometry work for visual spans, colspec preservation, row-head output, body-local head rows, section-scoped rowspans, declared-column overflow, source coordinates, occupied slots, accessibility relationships, reader packet attachment, nested rollups, source attributes, `rowspan=0`, Markdown/RST downgrade diagnostics, or colgroup metadata. It owns only compact long/short caption metadata for importer review packets and the directly coupled WordPress handoff smoke.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `AstNode`, `TableGeometry`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter` paths already present in the lane.

## Follow-Up

Keep default accessibility emission policy, malformed source-coordinate normalization, non-RST writer downgrade policies, richer caption block-level provenance, and full upstream Pandoc Haskell runner parity as separate bounded slices.
