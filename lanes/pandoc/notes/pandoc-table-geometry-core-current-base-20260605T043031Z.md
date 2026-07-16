# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T043031Z`

Base accepted HEAD: `b36dbe88ba80463d50bb6c0be8e8621b7076aace`

## Behavior Added

- Attached the existing JSON-safe `TableGeometry::reviewPacket()` handoff to
  Markdown reader table constructors that were still returning plain table AST
  nodes.
- Covered native Markdown pipe tables, simple tables, rectangular/spanned grid
  tables, LaTeX `table`/`tabular` imports, and DocBook `informaltable` imports.
- The attached packet preserves normalized column specs, section slot grids,
  cell coverage text, diagnostics, summary counts, and accessibility records
  for importer review without changing WordPress or Markdown rendered table
  output.
- Updated the WordPress table geometry smoke so it now includes a parsed
  Markdown pipe table and verifies the reader-attached packet is serializable.

## Source Truth

- Uses the existing pinned Pandoc static inventory as source truth:
  `test/pipe-tables.txt`, `test/tables.markdown`, command short-caption LaTeX
  table fixtures, command DocBook structural table fixtures, and grid-table
  rows from `test/markdown-reader-more.txt`.
- This slice ports the bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 26 assertions, 0 failures`
- Focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 62 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 368 assertions, 0 failures`
- Affected reader family:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2583 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7038 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownReader.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.
- JSON:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: reconciled to the measured current full-lane PASS-line count
  `618`; this slice adds 1 focused `TableGeometryReaderHandoffTest` PASS case
  (`1 -> 2`).
- Manifest mapped native checks: `1095 -> 1096`.
- Added `mappedTableGeometryMarkdownDocBookReaderPacketCases: 1`.
- Reconciled `mappedTableGeometryReaderHandoffCases: 2` and
  `tableGeometryReaderHandoffAssertions: 62`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, section-scoped rowspans, declared-column overflow
diagnostics, cell coverage reports, serializable review-packet construction,
opt-in WordPress accessibility rendering, source cell attribute preservation,
or prior structured HTML/DOCX/ODT reader packet attachment. The new behavior is
reader-level packet attachment for Markdown pipe/simple/grid, LaTeX tabular,
and DocBook table paths that already produced native table AST nodes.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/review-packet helpers, `MarkdownReader`, and native
WordPress writer smoke. Remaining table follow-up work is default accessibility
emission policy, richer overlap/conflict diagnostics, ODFReader package-table
packet attachment, nested-table packet rollups, and full upstream Pandoc
Haskell runner execution after the pinned checkout and Cabal test executables
are hydrated.
