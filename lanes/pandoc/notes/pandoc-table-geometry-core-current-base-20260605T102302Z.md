# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T102302Z`

Base accepted HEAD: `339f124190d9d276d42f196db494286344048c17`

## Behavior Added

- Added optional writer-list support to `TableGeometry::reviewPacket()` so
  table geometry handoff packets can include more than the default Markdown
  downgrade policy without changing rendered WordPress or Markdown output.
- Added reStructuredText writer alias normalization for `rst`,
  `rst-grid-table`, `restructuredtext`, and related names.
- Added bounded `rst-grid-table-required` diagnostics for rowspanned table
  cells, including the rowspanned slots that require a grid-table handoff.
- Updated the WordPress table-geometry self-test to verify the multi-writer
  review-packet summary and RST required-slot metadata.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for table writer and
  command fixture behavior, especially `test/command/rst-writer-gridtable-if-rowspans.md`
  plus the existing table span/alignment rows from `test/pipe-tables.txt`,
  `test/tables.markdown`, and `test/tables.native`.
- This ports bounded native PHP support-library behavior only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, external writers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 392 assertions, 1 failures`
  - Failure: expected `['rst-grid-table-required']`, actual `[]`.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 407 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 597 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `829 -> 830` on the accepted lane counter basis; this slice adds
  one new focused `TableGeometryTest` PASS case.
- Manifest mapped native checks: `1289 -> 1290`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- Added `mappedTableGeometryRstGridRequirementCases: 1`.
- Added `tableGeometryRstGridRequirementAssertions: 16`.
- Focused table geometry assertions moved from `391` to `407`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, occupied-slot metadata, accessible header relationships,
reader-attached review packets, nested table rollups, source-attribute
serialization, HTML `rowspan=0`, Markdown writer downgrade diagnostics, HTML
colgroup width/alignment expansion, colgroup provenance, or colgroup mismatch
diagnostics. The new behavior is opt-in non-Markdown writer handoff metadata
for reStructuredText rowspans that require grid-table output.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`TableGeometry` review-packet helper, Pandoc-like table AST, and WordPress
table handoff smoke. Remaining table follow-up work is default accessibility
emission policy, malformed source-coordinate normalization, non-RST writer
downgrade policies, richer caption/short-caption review metadata, and full
upstream Pandoc Haskell runner execution after the pinned checkout and Cabal
test executables are hydrated.
