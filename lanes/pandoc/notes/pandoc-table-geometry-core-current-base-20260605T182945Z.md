# Pandoc Table Geometry Current Base - Inherited HTML Alignment

Slice: `pandoc-table-geometry-core-current-base-20260605T182945Z`

Accepted base: `8a209745d849ff74146dd38c58413945e1e6a43c`

## Source Truth

Pandoc's HTML reader preserves table alignment metadata at table, row group,
row, and cell scopes. Native table geometry handoff must not drop imported
HTML alignment when a `th` or `td` omits its own alignment but inherits one
from `<thead>`, `<tbody>`, `<tfoot>`, `<tr>`, or `<table>` style/align
metadata.

## Implementation

- `MarkdownReader` now passes the containing table into HTML table row parsing.
- HTML table cell alignment now resolves from the cell first, then row, row
  group/section, and table align/style metadata.
- The inherited alignment is stored on AST table cells, carried into
  `tableGeometry` coverage packets, and emitted as WordPress `th`/`td`
  `text-align` styles.
- The WordPress table-geometry handoff example now includes an inherited
  alignment table and verifies AST, packet, and WordPress output.

## Focused Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 241 assertions, 1 failures`
  - Failed with expected `center` but actual `NULL` for inherited section
    alignment.
- Green reader run: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 266 assertions, 0 failures`
- Final focused table geometry run: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 930 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownReader.php`: no syntax errors
  - `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`: passed

## Status Delta

- `lane-status.json` `phpPass`: `1036` to `1037`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1488` to `1489`
- `mappedTableGeometryCoreCases`: `6` to `7`
- `tableGeometryCoreAssertions`: `74` to `102`
- New native case markers:
  - `mappedTableGeometryInheritedHtmlAlignmentCases`: `1`
  - `tableGeometryInheritedHtmlAlignmentAssertions`: `28`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`MarkdownReader`, `AstNode`, `TableGeometry`, and `WordPressBlockWriter`
support paths. No Pandoc, Cabal solver/build/test command, Haskell runner,
external writer, browser renderer, online sanitizer, or online service was
executed.

## Non-Overlap And Follow-Up

This slice does not overlap the accepted table geometry section-boundary,
declared-column overflow, nested-table, accessibility-header, RST/AsciiDoc/
LaTeX writer requirement, underfull-width, overfull-width, block-cell content,
caption metadata, or colgroup provenance slices. It covers only bounded HTML
reader alignment inheritance from row, row-group section, and table scopes.

Future table work should keep full CSS stylesheet cascade, col/colgroup-to-cell
cascade details, writing-mode and vertical-align handoff, richer DOCX/ODT
table style rendering, and full upstream Pandoc Haskell runner parity as
separate bounded slices.

Root harness: not run - isolated micro-slice.
