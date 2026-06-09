# Pandoc Table Geometry Row Background Handoff

Slice: `pandoc-table-geometry-core-current-base-20260609T025545Z`
Base accepted HEAD: `f3cb4f0219cafa35ccd839e4b1e650317d63e7bb`

## Behavior

- Added bounded HTML `tr` row background metadata to `TableGeometry::reviewPacket()`.
- Row `bgcolor` and CSS `background-color` are normalized through the existing table background color parser and exposed as `rowBackgrounds` records with section, row, global-row, row-role, header-row, source attributes, normalized attributes, and row text.
- Packet summaries now report row-background counts, local rows, global rows, sections, colors, and source kinds.
- Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics now report row-background review requirements because those writers need raw HTML or reviewer-specific table handling.
- The existing WordPress table handoff path preserves the source row background attributes for review.

## Source Truth And Non-Overlap

- Source truth came from the accepted static Pandoc table inventory plus the lane-local HTML table reader and WordPress table handoff contract. The isolated worktree did not contain a hydrated pinned Pandoc upstream checkout for additional targeted reads.
- This avoids accepted table geometry clusters for visual spans, section-scoped rowspans, row-head columns, declared-column overflow, source-coordinate shifts, colgroup provenance, decimal alignment, source attributes, source `scope` and `headers`, table/cell backgrounds, table/cell border presentation including side borders, cell nowrap, directionality, caption placement, footer sections, empty tables, nested tables, block cells, and body-group writer diagnostics.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, external writer, browser renderer, Word, LibreOffice, zip/unzip, online service, live provider test, or live-service provider test was executed.

## Focused Evidence

- PHP lint:
  - `php -l lanes/pandoc/src/TableGeometry.php`
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: all reported no syntax errors.
- Focused reader handoff:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 992 assertions, 0 failures`
- Focused table family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 2756 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2189 -> 2190`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2602 -> 2603`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 202`.
- Added `mappedTableGeometryRowBackgroundCases: 1`.
- Added `tableGeometryRowBackgroundAssertions: 47`.
- Focused reader assertions increased from latest table-geometry baseline `945` to `992` (`+47`).

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`MarkdownReader` HTML table path, `AstNode` table row attributes,
`TableGeometry` review-packet and writer-diagnostic plumbing, and the
WordPress table geometry handoff example. Full upstream Pandoc runner parity
remains gated on a hydrated pinned Pandoc checkout plus reviewed non-mutating
Cabal/Haskell test executable planning.

## Follow-Up

Potential non-overlapping follow-ups: row-level border presentation provenance,
row/section style inheritance summaries for non-HTML writers, or richer DOCX/ODF
table-style inheritance into the same row/section geometry packet shape.
