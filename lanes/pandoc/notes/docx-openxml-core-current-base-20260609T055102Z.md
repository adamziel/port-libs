# DOCX/OpenXML conditional column and corner table styles

Slice: `pandoc-docx-openxml-core-current-base-20260609T055102Z`
Base: `0f5df40680da5ed9191360998ab90d0db36f1bca`

## Behavior

- `DocxReader` now computes DOCX table conditional regions per visual grid
  cell, using explicit `w:tblGrid` columns when available and falling back to
  inferred row grid spans.
- Active `firstCol`, `lastCol`, `nwCell`, `neCell`, `swCell`, and `seCell`
  `w:tblStylePr` regions now apply to imported table cells, paragraph metadata,
  and run metadata. Corner regions override first/last column regions for the
  effective cell/run metadata, while inherited row formatting remains intact.
- Cells that span middle grid columns are not misclassified as first/last
  column cells. Direct row/cell/paragraph/run properties still merge after
  inherited conditional table style properties.
- Existing table-level conditional region inventory metadata remains preserved
  on the table and in `TableGeometry` source attributes for reviewer audit.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4208 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4286 assertions, 0 failures`.
- New example smoke:
  `php lanes/pandoc/examples/wordpress-docx-conditional-column-style-handoff.php --self-test`
  passed with `wordpress-docx-conditional-column-style-handoff self-test passed`.
- Syntax checks passed:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-conditional-column-style-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check:
  `git diff --check -- lanes/pandoc` passed.

## Delta

- Added 1 focused PHP PASS case.
- Focused assertion count moved `4208 -> 4286` (`+78`).
- `lanes/pandoc/lane-status.json` moved `phpPass` `2402 -> 2403`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` moved mapped static inventory
  `2792 -> 2793`; DOCX/OpenXML core case counters moved `33 -> 34`, and
  DOCX/OpenXML core assertion inventory moved `385 -> 463`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`/OPC
fixtures, DOM-based `DocxReader` style and table parsing, `TableGeometry`
source attribute handoff, `MarkdownWriter`, `WordPressBlockWriter`, and the
focused lane TestRunner. Full upstream Pandoc runner parity remains a separate
upstream-runner dependency task requiring hydrated Pandoc sources and Haskell
test executables.

## Non-Overlap

This follows the accepted conditional table-style row application slice without
repeating it: `firstRow`, `band1Horz`, and `lastRow` behavior remains unchanged,
while this patch adds visual-column and corner-cell activation. It does not
repeat accepted document defaults, paragraph/run/character style inheritance,
table `basedOn` style inheritance, direct table `tblPr` metadata, direct table
cell metadata, table spans, bookmarks, comments/endnotes, tracked revisions,
content controls, chart/drawing metadata, theme colors/fonts, or OPC
relationship preflight work.

Follow-up remains bounded to non-overlapping table-style semantics such as
vertical banded-column regions, direct cell-style inheritance, table look
bitmask gating, or numbering-style interactions.
