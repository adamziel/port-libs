# DOCX/OpenXML conditional table style application

Slice: `pandoc-docx-openxml-core-current-base-20260609T052746Z`
Base: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`

## Behavior

- `DocxReader` now resolves structured `w:tblStylePr` conditional table-style
  regions through table style `basedOn` chains.
- Active `firstRow`, `band1Horz`, and `lastRow` regions are applied while
  importing a table, so row `trPr`, cell `tcPr`, paragraph `pPr`, and run
  `rPr` properties reach the AST and WordPress table output instead of only
  being recorded on the table-level inventory metadata.
- Direct row/cell/paragraph/run properties still merge after inherited
  conditional style properties. Character run styles also remain able to
  override conditional table run properties.
- Existing table-level conditional region inventory metadata remains preserved
  on the table and in `TableGeometry` source attributes for reviewer audit.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4162 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4208 assertions, 0 failures`.
- Updated example smoke:
  `php lanes/pandoc/examples/wordpress-docx-conditional-table-style-handoff.php --self-test`
  passed with `wordpress-docx-conditional-table-style-handoff self-test passed`.
- Syntax checks passed:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-conditional-table-style-handoff.php`.
- Whitespace check:
  `git diff --check -- lanes/pandoc` passed.

## Delta

- Added 1 focused PHP PASS case.
- Focused assertion count moved `4162 -> 4208` (`+46`).
- `lanes/pandoc/lane-status.json` moved `phpPass` `2375 -> 2376`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` moved mapped static inventory
  `2769 -> 2770`; DOCX/OpenXML core case counters moved `33 -> 34`, and
  DOCX/OpenXML core assertion inventory moved `385 -> 431`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`/OPC
fixtures, DOM-based `DocxReader` style and table parsing, `TableGeometry`
source attribute handoff, `MarkdownWriter`, `WordPressBlockWriter`, and the
focused lane TestRunner. Full upstream Pandoc runner parity remains a separate
upstream-runner dependency task requiring hydrated Pandoc sources and Haskell
test executables.

## Non-Overlap

This builds on the prior conditional table-style inventory slice without
repeating it: table-level `data-docx-table-style-region-*` metadata remains the
inventory surface, while this slice applies the referenced row/cell/paragraph/
run properties to imported AST nodes and WordPress blocks. It does not repeat
accepted document defaults, paragraph/run/character style inheritance, table
`basedOn` style inheritance, direct table `tblPr` metadata, direct table cell
metadata, table spans, bookmarks, comments/endnotes, tracked revisions,
content controls, chart/drawing metadata, theme colors/fonts, or OPC
relationship preflight work.

Follow-up remains bounded to non-overlapping table-style semantics such as
first/last column and corner-cell region application, direct cell-style
inheritance, or numbering-style interactions.

