# DOCX/OpenXML Structured Document Tag Form Metadata

Slice: `pandoc-docx-openxml-core-current-base-20260607T065927Z`
Base accepted HEAD: `d2d9ea88993bebb96d341c8a9132df3b4b90a3ff`

## Behavior

Mapped one bounded DOCX/OpenXML support case for WordprocessingML structured
document tag form controls.

- `DocxReader` now detects the case-sensitive `w:checkBox` control type and
  preserves checkbox checked state plus checked/unchecked glyph value and font
  metadata.
- `w:dropDownList` and `w:comboBox` controls now preserve list kind, last
  selected value, item count, and each option's display text/value.
- `w:date` controls now preserve full-date, date-format, language,
  store-mapped-data-as, and calendar metadata.
- Existing behavior still unwraps `w:sdtContent` into visible AST content for
  Markdown and WordPress handoff; the new metadata is reviewer-only `data-docx-*`
  provenance on the existing content-control spans/divs.

## Evidence

- Baseline accepted focused DOCX test from the latest lane note:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 1972 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 2041 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  passed with `docx body handoff self-test ok`.
- Syntax checks passed:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`.

Delta:

- `+1` PHP PASS case.
- `+69` focused DOCX assertions.
- mapped DOCX/OpenXML support cases: `33 -> 34`.
- mapped denominator: `1880 -> 1881`.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML support for generic content-control
wrapping, docPart/glossary content controls, smart tags, custom XML, tracked
changes, comments, bookmarks, fields, media, text boxes, paragraph borders,
section/header/footer metadata, settings, theme fonts, OMML, altChunk, or
embedded objects. It owns only bounded form-control metadata inside `w:sdtPr`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `DocxReader`,
in-memory `ZipPackage` fixtures, `AstNode` spans/divs, `MarkdownWriter`,
`WordPressBlockWriter`, and the focused lane PHP harness.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external office
tool, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

Next DOCX/OpenXML work should stay bounded to non-overlapping table-cell
vertical alignment, paragraph frame/drop-cap metadata, style inheritance merge
edges, numbering-level metadata, or reader integration of existing OPC
preflight diagnostics.
