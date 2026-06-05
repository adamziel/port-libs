# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T012741Z`

Base accepted HEAD: `72233410189f75bf7ebbabd39de1ea39ec033f70`

## Behavior Added

- Added `TableGeometry::sectionRowEntryGroups()` so table geometry audits can
  distinguish top-level table head rows, Pandoc body-local `TableBody` head
  rows, ordinary body rows with `rowHeadColumns`, and foot rows.
- Extended `TableGeometry::sectionGrids()` and `TableGeometry::cellCoverage()`
  with row-role metadata: `rowRole`, `headerRow`, `rowHeadColumns`, and
  `headerCell`.
- Added `TableGeometry::isHeaderCell()` and reused it in
  `WordPressBlockWriter`, keeping WordPress `<th>` output and table-geometry
  audit metadata on the same header-cell policy.
- Updated the table-geometry WordPress smoke to cover a body-local head row in
  `<tbody>` plus row-head-column cells and rowspanned covered slots.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth. Pandoc table
  ASTs carry table sections, body-local head rows, body row-head columns,
  colspec alignment/width metadata, row spans, and column spans. The manifest
  already maps upstream `test/html-reader.html` body-local `TableBody` head-row
  shapes plus native table span fixtures.
- This slice ports a bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber,
  Word, LibreOffice, office tools, tar, zip/unzip, lz4, external template
  engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax, KaTeX,
  online sanitizers, or online services.

## Verification

- Baseline before editing:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 159 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 184 assertions, 0 failures`
  - PASS lines: 11
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5179 assertions, 0 failures`
  - PASS lines: 496

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 497 -> 498.
- Manifest mapped native checks: 972 -> 973.
- `mappedTableGeometryCoreCases`: reconciled to 11 current focused cases.
- `mappedTableGeometryBodyHeadRoleCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: reconciled to 184 for the focused
  `TableGeometryTest.php` coverage.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, normalized column specs, cell coverage metadata,
DOCX `w:gridSpan` / `w:vMerge` parsing, DocBook span parsing, HTML-reader
table parsing, or Markdown pipe-table parsing. The new behavior is explicit
row-role/header-cell metadata for body-local `TableBody` head rows and the
shared WordPress header-cell handoff predicate after a Pandoc-like AST table
already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, native Markdown writer, and native
WordPress writer. Remaining table follow-up work is importer-level attachment
of section grid and coverage role reports to DOCX/ODT/HTML review packets,
richer overlap conflict diagnostics, accessibility scope/id policy, and full
upstream Pandoc Haskell runner execution after the pinned checkout and Cabal
test executables are hydrated.
