# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T060125Z`

Base accepted HEAD: `e6c2c65a1e2231590eb2d76057253666c5d01998`

## Behavior Added

- Added per-section summary records to serialized `TableGeometry::reviewPacket()`
  section packets.
- Each section summary now reports cell/header/covered/missing slot counts plus
  nested table counts, nested-table-containing cell counts, nested table
  captions, descendant nested table captions, and nested diagnostic rollups.
- The top-level coverage rollup remains intact, but WordPress importer queues
  can now identify whether nested tables occur in the table head, body, later
  body groups, or foot without walking every cell coverage record.
- Updated the WordPress table geometry handoff smoke to prove body-local nested
  table section counts and captions remain JSON-safe.

## Source Truth

- Uses the existing pinned Pandoc static inventory as source truth for table
  section and nested-table behavior:
  `test/command/nested-table-to-asciidoc-6942.md`,
  `test/html-reader.html/native`, `test/tables/nordics.html5`, and
  `test/markdown-reader-more.native`.
- This slice ports bounded support-library behavior only. It does not invoke
  Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office tooling,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 320 assertions, 1 failures`
  - Failure: serialized sections lacked `summary.nestedTableCount`.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 344 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 419 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7878 assertions, 0 failures`
  - PASS lines remain `677`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: remains `677`; this slice adds assertion coverage to the existing
  focused table geometry PASS case rather than adding a new PASS case.
- Manifest mapped native checks: `1153 -> 1154`.
- `mappedTableGeometryCoreCases`: reconciled to `18`.
- Added `mappedTableGeometrySectionNestedSummaryCases: 1`.
- `tableGeometryCoreAssertions`: `336 -> 344`.
- Added `tableGeometrySectionNestedSummaryAssertions: 8`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, computed/source accessibility handoff, reader-attached
review packets, or cell-level nested table rollups. The new behavior is the
section-level aggregate report layered on top of the existing nested-table
coverage records.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/review-packet helpers, `MarkdownReader`, and native
WordPress writer smoke. Remaining table follow-up work is malformed
source-coordinate attribute normalization, default accessibility emission
policy, writer-specific table downgrade diagnostics, and full upstream Pandoc
Haskell runner execution after the pinned checkout and Cabal test executables
are hydrated.
