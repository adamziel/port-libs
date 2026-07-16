# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T094941Z`

Base accepted HEAD: `6c17a53dace9fb9ba9844a3b8d169184f9cf69ee`

## Behavior Added

- Preserved usable HTML `<colgroup>` alignment, width, and source provenance
  when expanded source columns under- or over-declare the visual table width.
- Added JSON-safe `html-colgroup-underdeclares-columns` and
  `html-colgroup-overdeclares-columns` diagnostics to table geometry review
  packets.
- Underdeclared source columns now keep partial column metadata on the covered
  visual columns while missing source columns stay explicit defaults.
- Overdeclared source columns remain visible as declared/missing geometry slots
  so reviewer queues can see source colspecs that reserve audit columns.
- Updated the WordPress table-geometry smoke with an underdeclared colgroup
  handoff that preserves partial provenance without emitting a misleading
  incomplete `<colgroup>`.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for structured HTML
  table parsing and handoff, especially `test/tables/nordics.html5`,
  `test/html-reader.html`, and `test/html-reader.native` table section,
  caption, colgroup, row-header, span, and source-attribute behavior.
- This ports bounded native PHP support-library behavior only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 159 assertions, 1 failures`
  - Failure: expected `['right', 'right', 'default']` for an underdeclared
    colgroup, actual `['default', 'default', 'default']`.
- Focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 190 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 581 assertions, 0 failures`
- Affected Markdown reader plus handoff:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 2919 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9957 assertions, 0 failures`
  - PASS lines: `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '` -> `803`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownReader.php`: no syntax errors.
  - `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `816 -> 817` on the accepted lane counter basis; this slice adds
  one new focused `TableGeometryReaderHandoffTest` PASS case.
- Manifest mapped native checks: `1276 -> 1277`.
- Added `mappedTableGeometryHtmlColgroupMismatchCases: 1`.
- Added `tableGeometryHtmlColgroupMismatchAssertions: 33`.
- Focused table-family assertions moved from the previous accepted table note
  baseline of `548` assertions to `581` assertions.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, occupied-slot metadata, accessible header relationships,
reader-attached review packets, nested table rollups, source-attribute
serialization, HTML `rowspan=0`, Markdown writer downgrade diagnostics,
HTML colgroup width/alignment expansion, or HTML colgroup provenance. The new
behavior is mismatch diagnostics plus partial metadata preservation when
expanded HTML colgroup source columns do not match the visual table width.

## Dependency Closure

No new support component is needed. This reuses the existing native HTML table
reader, Pandoc-like table AST, `TableGeometry` layout/coverage/review-packet
helpers, and WordPress table handoff smoke. Remaining table follow-up work is
default accessibility emission policy, malformed source-coordinate
normalization, non-HTML writer downgrade policies, richer caption/short-caption
review metadata, and full upstream Pandoc Haskell runner execution after the
pinned checkout and Cabal test executables are hydrated.
