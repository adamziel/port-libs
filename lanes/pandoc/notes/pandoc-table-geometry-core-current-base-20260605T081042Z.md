# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T081042Z`

Base accepted HEAD: `7a8bfe458c7cf3f121b479b45379fc05e74c094d`

## Behavior Added

- Added bounded HTML `rowspan=0` handling for native table geometry.
- The HTML reader now preserves `rowspan=0` as a row-group span sentinel
  instead of normalizing it to `1`.
- `TableGeometry` resolves that sentinel to the remaining rows in the current
  table section, so coverage packets report the finite effective rowspan,
  covered slots, missing slots, and `rowspanToEnd`.
- The resolved span is scoped to the current `tbody`; following `tbody`
  sections stay independent.
- WordPress table output receives a finite `rowspan` attribute, so imported
  reviewer tables remain valid HTML without leaking the source sentinel.

## Source Truth

- Uses the pinned Pandoc static inventory for structured HTML table reader
  behavior around `test/html-reader.html`, `test/html-reader.native`, and the
  mapped table-section/rowspan cases already tracked in
  `UPSTREAM_TEST_MANIFEST.json`.
- Applies the HTML table row-group contract for `rowspan=0` as bounded PHP
  support-library behavior; it does not invoke Pandoc, Cabal, Haskell test
  binaries, browser renderers, online sanitizers, or online services.

## Verification

- Baseline focused check before edits:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 95 assertions, 0 failures`
- Red-first focused check after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 104 assertions, 1 failures`
  - Failure: the new tbody covered-slot count stayed `0` instead of `2`.
- Focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 120 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 511 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `765 -> 766` on the accepted lane counter basis; this slice adds
  one new focused `TableGeometryReaderHandoffTest` PASS case.
- Manifest mapped native checks: `1224 -> 1225`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 99`.
- Added `mappedTableGeometryHtmlRowspanZeroCases: 1`.
- Added `tableGeometryHtmlRowspanZeroAssertions: 25`.

## Non-Overlap

This does not repeat accepted visual span layout, declared-column overflow,
section-boundary overlarge rowspan diagnostics, source-coordinate overlap
diagnostics, Markdown writer downgrade reporting, nested table review-packet
rollups, source attribute serialization, body-local head rows, or accessibility
header relationship generation. The new behavior is specifically the HTML
reader and geometry handoff for `rowspan=0` as a current-section row-group
span.

## Dependency Closure

No new support component is needed. This reuses the existing native HTML reader,
Pandoc-like table AST, `TableGeometry` layout/coverage/review-packet helpers,
and WordPress block writer. Full upstream Pandoc runner parity remains gated on
hydrating the pinned Pandoc checkout and Cabal test executable metadata.
