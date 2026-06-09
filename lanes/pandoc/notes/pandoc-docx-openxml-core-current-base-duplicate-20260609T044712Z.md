# DOCX/OpenXML Table Style Inheritance

Slice: `pandoc-docx-openxml-core-current-base-duplicate-20260609T044712Z`

Base accepted HEAD: `4bd0353e68feb117d03d0d43e4710ee88b193cbf`

## Behavior

- `DocxReader` now loads `w:style w:type="table"` records from `word/styles.xml`.
- Table styles resolve `w:basedOn` chains before direct table properties.
- Bounded inherited metadata includes table style name/basedOn, preferred width, alignment, indentation, and fixed/autofit layout.
- Direct `w:tblPr` width and alignment override inherited values and remove stale inherited classes/styles while retaining inherited indent/layout metadata when not overridden.
- Existing `TableGeometry` source-attribute handoff, Markdown table output, and WordPress table block output carry the resulting safe review metadata without rendering Word table styling or invoking office tools.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed before this slice.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3965 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4022 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-table-style-handoff.php --self-test`
  passed with `wordpress-docx-table-style-handoff self-test passed`.
- Focused delta: `+1` PHP PASS case and `+57` focused assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`/OPC fixtures, DOM-based `DocxReader` style and table parsing, `TableGeometry` source-attribute handoff, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane TestRunner. Full upstream Pandoc DOCX runner parity remains a separate upstream-runner dependency task requiring hydrated Pandoc sources and Haskell test executables.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX document defaults, paragraph style inheritance, run style inheritance, table direct preferred width/alignment/indent/layout metadata, table cell/row metadata, table spans, bookmarks, comments/endnotes, tracked revisions, content controls, chart/drawing metadata, theme colors/fonts, or OPC relationship preflight work. It only closes table-style `styles.xml` inheritance and direct `tblPr` override behavior.

## Follow-Up

Useful non-overlapping DOCX/OpenXML follow-ups: conditional table style regions (`w:tblStylePr`), table cell style inheritance, numbering style interactions, or latent style defaults.
