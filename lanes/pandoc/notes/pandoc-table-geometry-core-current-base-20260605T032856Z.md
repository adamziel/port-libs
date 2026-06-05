# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T032856Z`

Base accepted HEAD: `9c236555f1b2ca3e0d63b5c5e217c1306139dab6`

## Behavior Added

- Preserved source table cell `id`, `class`, and allowed data attributes in
  WordPress table output.
- Updated `TableGeometry::accessibilityAttributes()` so generated data-cell
  `headers` relationships point at a source header cell ID when one is already
  present, instead of inventing an unreachable computed ID.
- Kept source `headers` attributes intact on table data cells, so imported
  DOCX/HTML/ODT review packets with existing accessibility metadata are not
  overwritten by the computed relationship pass.
- Extended the WordPress table geometry smoke with a source-attributed table
  proving source ID/class/data provenance plus computed header references.

## Source Truth

- Uses the accepted static Pandoc table inventory as source truth. Pandoc
  table cells carry an `Attr` alongside alignment, rowspan, colspan, and block
  content, and the WordPress handoff must preserve that source identity while
  layering computed accessibility only where the source did not already supply
  one.
- This slice ports the bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, or online services.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 281 assertions, 0 failures`
- Red-style run during implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed in the new source-cell attribute case on the exact source
    `headers` attribute ordering assertion.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 295 assertions, 0 failures`
  - PASS lines: 15
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `578 -> 579`.
- Manifest mapped native checks: `1055 -> 1056`.
- `mappedTableGeometryCoreCases`: reconciled to `15` current focused
  `TableGeometryTest.php` cases.
- `mappedTableGeometryCellAttributeCases`: `0 -> 1`.
- `tableGeometryCoreAssertions`: reconciled to `295` for the focused
  `TableGeometryTest.php` coverage.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, normalized column specs, cell coverage metadata,
body-local head-row role metadata, overlap diagnostics, accessibility
relationship generation, or serializable review-packet packaging. The new
behavior is source table cell Attr preservation and source-ID-aware
accessibility handoff after the table geometry already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/accessibility helper, and native WordPress writer.
Remaining table follow-up work is importer-level attachment of review packets
to DOCX/ODT/HTML reader reports, richer multi-block cell text summaries, reader
policy for default accessibility emission, and full upstream Pandoc Haskell
runner execution after the pinned checkout and Cabal test executables are
hydrated.
