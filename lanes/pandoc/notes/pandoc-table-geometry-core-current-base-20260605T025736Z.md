# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T025736Z`

Base accepted HEAD: `1190c5c4c581e1086fde51c3df6cec110063ec0f`

## Behavior Added

- Added `TableGeometry::reviewPacket()` as a bounded serializable table
  geometry report for importer handoff.
- The packet includes normalized Pandoc column specs, section row/slot reports,
  serializable cell coverage with plain text, diagnostics, summary counts, and
  optional accessibility relationships.
- The report strips `AstNode` objects from section slots and coverage records,
  so DOCX/ODT/HTML reader queues can JSON-encode geometry evidence for reviewer
  packets without leaking live AST nodes.
- Updated the WordPress table geometry smoke so the existing table handoff
  example checks the review packet summary, row roles, coverage text, and
  accessibility headers.

## Source Truth

- Uses the accepted static Pandoc table inventory as source truth. Pandoc table
  ASTs preserve ordered head/body/foot sections, `TableBody` local head rows,
  row-head columns, colspans, rowspans, column alignments, column widths, and
  table/cell attributes.
- This slice ports the bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, or online services.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 236 assertions, 0 failures`
- Red run during implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed in the new review-packet case on an incorrect expected
    overflow coverage index (`Full width audit note` was asserted at the
    ordinary `Overflow note` cell index).
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 281 assertions, 0 failures`
  - PASS lines: 14
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6035 assertions, 0 failures`
  - PASS lines by `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`: `556`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: reconciled to measured focused lane output, `556`.
- Manifest mapped native checks: `1034 -> 1035`.
- `mappedTableGeometryCoreCases`: reconciled to `14` current focused
  `TableGeometryTest.php` cases.
- `mappedTableGeometryReviewPacketCases`: `0 -> 1`.
- `tableGeometryCoreAssertions`: reconciled to `281` for the focused
  `TableGeometryTest.php` coverage.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, normalized column specs, cell coverage metadata,
body-local head-row role metadata, overlap diagnostics, or accessibility
relationship emission in WordPress HTML. The new behavior is the serializable
review-packet packaging layer on top of those already accepted geometry
primitives.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, diagnostics, accessibility helper, and
native WordPress writer smoke. Remaining table follow-up work is importer-level
attachment of the review packet to DOCX/ODT/HTML reader reports, plus full
upstream Pandoc Haskell runner execution after the pinned checkout and Cabal
test executables are hydrated.
