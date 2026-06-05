# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T070703Z`

Base accepted HEAD: `f5c8efa7a7acc5f8f7506975550909d324c38d52`

## Behavior Added

- Added serializable `occupiedSlots` records for anchored cells in
  `TableGeometry::sectionGrids()` and `TableGeometry::cellCoverage()`.
- Each occupied-slot record reports section-local `row`, visual `column`, and
  whether the slot is the `anchor`, `colspan`, `rowspan`, or
  `rowspan-colspan` portion of the source cell.
- Rowspans remain clamped to their table section before occupied slots are
  emitted, so malformed rowspans that cross from head/body/foot stay visible
  in diagnostics without implying cross-section occupancy.
- Updated the WordPress table-geometry handoff smoke to assert occupied-slot
  review metadata remains JSON-safe while rendered WordPress table HTML stays
  unchanged.

## Source Truth

- Uses the existing pinned Pandoc static inventory for table span shapes:
  `test/command/table-with-column-span.md`,
  `test/command/rst-writer-gridtable-if-rowspans.md`,
  `test/markdown-reader-more.native`, and `test/html-reader.html/native`.
- This slice ports bounded support-library review-packet behavior only. It
  does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
  office tooling, tar, zip/unzip, lz4, external template engines, TeX/PDF
  engines, browser renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 364 assertions, 1 failures`
  - Failure: spanned-cell section-grid records lacked `occupiedSlots`.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 372 assertions, 0 failures`
- Focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 95 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 467 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax and metadata checks:
  `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
  - Result: `manifest json ok`
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `733 -> 734` on the accepted lane counter basis; this slice adds
  one new focused PASS case.
- Manifest mapped native checks: `1192 -> 1193`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- Added `mappedTableGeometryOccupiedSlotCases: 1`.
- `tableGeometryCoreAssertions`: `74 -> 82`.
- Added `tableGeometryOccupiedSlotAssertions: 8`.
- Focused table-family assertions: `458 -> 467` relative to the previous table
  source-attribute handoff note.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, computed/source accessibility handoff, reader-attached
review packets, nested table rollups, section-level nested summaries, or
source-attribute serialization. The new behavior is compact occupied-cell
rectangle metadata layered onto the existing grid and coverage contracts.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/review-packet helpers, native HTML/DOCX/ODT table
reader handoff paths, and the WordPress table smoke. Remaining table follow-up
work is writer-specific table downgrade diagnostics, richer malformed
source-coordinate normalization, default accessibility emission policy, and
full upstream Pandoc Haskell runner execution after the pinned checkout and
Cabal test executables are hydrated.
