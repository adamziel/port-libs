# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T084201Z`

Base accepted HEAD: `80b373e90e1c3df6aabeea77b198f3f317bb03d9`

## Behavior Added

- Added bounded HTML table column metadata expansion for `<col span>`.
- The native HTML reader now expands `span` across column widths and
  alignments before building the Pandoc-like table AST.
- Expanded colgroup metadata feeds existing `TableGeometry::columnSpecs()`,
  cell coverage records, review packets, Markdown writer alignment metadata,
  and WordPress table output.
- WordPress table rendering now preserves imported reviewer tables whose
  source column metadata is declared once in `<colgroup>` instead of repeated
  on every `<td>` or `<th>`.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for structured HTML
  table parsing and handoff, especially `test/tables/nordics.html5`,
  `test/html-reader.html`, and `test/html-reader.native` table section,
  caption, colgroup, row-header, and span behavior.
- This ports bounded native PHP support-library behavior only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 123 assertions, 1 failures`
  - Failure: expected `['right', 'right', 'center']` colgroup-derived
    alignments, actual `['default', 'default', 'default']`.
- Focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 139 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 530 assertions, 0 failures`
- Focused Markdown/HTML reader plus table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `3 test files, 3223 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9326 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `783 -> 784` on the accepted lane counter basis; this slice adds
  one new focused `TableGeometryReaderHandoffTest` PASS case.
- Focused table family assertion count moved from the earlier selected-family
  baseline of `511` assertions to `530` assertions.
- Manifest mapped native checks: added one table geometry support case.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 94`.
- Added `mappedTableGeometryHtmlColgroupMetadataCases: 1`.
- Added `tableGeometryHtmlColgroupMetadataAssertions: 20`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, occupied-slot metadata, accessible header relationships,
reader-attached review packets, nested table rollups, source-attribute
serialization, HTML `rowspan=0`, or Markdown writer downgrade diagnostics.
The new behavior is reader-side expansion of compact HTML `<col span>`
width/alignment metadata into the existing table geometry contract.

## Dependency Closure

No new support component is needed. This reuses the existing native HTML
reader, Pandoc-like table AST, `TableGeometry` layout/coverage/review-packet
helpers, Markdown table writer alignment metadata, and WordPress table handoff
smoke. Remaining table follow-up work is richer column-group provenance
packets, default accessibility emission policy, malformed source-coordinate
normalization, non-HTML writer downgrade policies, and full upstream Pandoc
Haskell runner execution after the pinned checkout and Cabal test executables
are hydrated.
