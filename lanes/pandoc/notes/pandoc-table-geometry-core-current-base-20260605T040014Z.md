# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T040014Z`

Base accepted HEAD: `a18cd7e7f0c3b2dde3f61187f659f01e7ea565cc`

## Behavior Added

- Added `TableGeometry::withReviewPacket()` to attach the existing
  JSON-safe table geometry review packet to finalized table AST nodes.
- Wired structured HTML reader tables, DOCX `gridSpan`/`vMerge` tables, and
  ODT repeated/spanned tables to carry a `tableGeometry` packet.
- The packet preserves normalized columns, section slot grids, coverage text,
  row roles, diagnostics, summary counts, and computed accessibility
  relationships without changing WordPress table rendering.
- Added a focused HTML-reader handoff test and extended the existing DOCX/ODT
  span tests so package-import table ASTs prove the packet is present and
  serializable.

## Source Truth

- Uses accepted Pandoc static-inventory table sources as source truth:
  structured HTML tables from `test/html-reader.html` and
  `test/tables/nordics.html5`, DOCX/OpenXML table spans, and ODT table spans.
- This ports the bounded support-library handoff contract only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Focused table/reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `4 test files, 1045 assertions, 0 failures`
- PASS-line checks:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php | rg -c '^PASS '`
  - Result: `17`
  `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/DocxReaderTest.php | rg -c '^PASS '`
  - Result: `29`
- Syntax:
  `php -l lanes/pandoc/src/TableGeometry.php`
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/src/OdtReader.php`
  `php -l lanes/pandoc/src/DocxReader.php`
  `php -l lanes/pandoc/tests/TableGeometryTest.php`
  `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  `php -l lanes/pandoc/tests/OdtReaderTest.php`
  `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors.
- JSON:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `600 -> 602`.
- Manifest mapped native checks: `1074 -> 1076`.
- `mappedTableGeometryCoreCases`: reconciled to `16`.
- `mappedTableGeometryReaderHandoffCases`: `0 -> 2`.
- `tableGeometryCoreAssertions`: reconciled to `306`.
- `tableGeometryReaderHandoffAssertions`: `0 -> 62`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, section-scoped rowspans, declared-column overflow
diagnostics, cell coverage reports, serializable review-packet construction,
or opt-in WordPress accessibility rendering. The new behavior is reader-level
attachment of that accepted packet to structured HTML, DOCX, and ODT table AST
nodes for importer review.

Full `MarkdownReaderTest.php` was not used as the acceptance command because
the lane status already records an unrelated existing structured HTML footer-id
expectation mismatch. The new HTML-reader behavior is covered by the focused
`TableGeometryReaderHandoffTest.php`.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/review-packet helpers, native HTML/DOCX/ODT reader
paths, and the native WordPress writer smoke surface. Remaining table follow-up
work is broader reader attachment for Markdown grid/simple/pipe and DocBook
table paths, default reader policy for accessibility emission, richer overlap
conflict diagnostics, and full upstream Pandoc Haskell runner execution after
the pinned checkout and Cabal test executables are hydrated.
