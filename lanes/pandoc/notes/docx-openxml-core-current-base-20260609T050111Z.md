# DOCX/OpenXML conditional table style regions

Slice: `pandoc-docx-openxml-core-current-base-20260609T050111Z`
Base: `945c3c6f54718c2e2a84ea6013a7f69ab7cd1d9a`

## Behavior

- `DocxReader` now preserves bounded `w:tblStylePr` conditional table style
  regions from `word/styles.xml` table styles.
- The initial native handoff records `firstRow`, `band1Horz`, and `lastRow`
  region metadata on the table AST and WordPress table output, including row
  policy, cell width/shading/vertical-align, paragraph alignment, and run
  formatting metadata.
- The metadata is carried through `TableGeometry` source attributes for review
  packets. Markdown pipe-table output still omits DOCX review metadata.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4050 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4108 assertions, 0 failures`.
- Added example smoke:
  `php lanes/pandoc/examples/wordpress-docx-conditional-table-style-handoff.php --self-test`
  passed with `wordpress-docx-conditional-table-style-handoff self-test passed`.
- Syntax checks passed:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-conditional-table-style-handoff.php`.

## Delta

- Added 1 focused PHP PASS case.
- Focused assertion count moved `4050 -> 4108` (`+58`).
- `lanes/pandoc/lane-status.json` moved `phpPass` `2343 -> 2344`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` moved mapped static inventory
  `2738 -> 2739`; DOCX/OpenXML core case counters moved `33 -> 34`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`/OPC
fixtures, DOM-based `DocxReader` style and table parsing, `TableGeometry`
source attribute handoff, `MarkdownWriter`, `WordPressBlockWriter`, and the
focused lane TestRunner. Full upstream Pandoc runner parity remains a separate
upstream-runner dependency task requiring hydrated Pandoc sources and Haskell
test executables.

## Non-Overlap

This does not repeat accepted document defaults, paragraph/run/character style
inheritance, table `basedOn` style inheritance, direct `tblPr` width/alignment/
indent/layout overrides, direct table cell shading/width/margin/border metadata,
table spans, bookmarks, comments/endnotes, tracked revisions, content controls,
chart/drawing metadata, theme colors/fonts, or OPC relationship preflight work.
It only closes table-style conditional region metadata. Applying conditional
style properties onto individual table cells remains a separate follow-up.

No Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external
converters, online services, live provider tests, or live-service provider tests
were executed. Root harness was not run for this isolated micro-slice.
