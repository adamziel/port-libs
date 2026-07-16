# DOCX/OpenXML run field marker handoff

Slice: `pandoc-docx-openxml-core-current-base-20260609T025145Z`
Base accepted HEAD: `cb8eb4f51cf712622b553d57173410c449f7e04d`

## Behavior

WordprocessingML run content can carry layout-dependent field markers such as
`w:pgNum` and date marker elements (`w:dayShort`, `w:dayLong`,
`w:monthShort`, `w:monthLong`, `w:yearShort`, `w:yearLong`). The native DOCX
reader now preserves those markers as metadata-only reviewer spans instead of
dropping them or pretending to compute Word layout values.

The Markdown/WordPress handoff emits:

- `docx-run-field-marker docx-page-number-marker` with
  `data-docx-run-field-marker="page-number"` for `w:pgNum`.
- `docx-run-field-marker docx-date-field-marker` plus
  `data-docx-date-field` for the supported date marker elements.

## Evidence

- Baseline focused DOCX suite before the patch:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` ->
  `1 test files, 3590 assertions, 0 failures`.
- Red-first check after adding the focused fixture:
  `preserves DOCX page number and date field run markers as reviewer spans`
  failed because the new fixture collapsed to one text node.
- Final focused DOCX suite:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` ->
  `1 test files, 3618 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` ->
  `docx body handoff self-test ok`.
- Syntax:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php` all passed.

## Status Delta

- Adds one DOCX/OpenXML mapped support case.
- Adds one PHP PASS case in `DocxReaderTest.php`.
- Adds 28 focused assertions (`3590 -> 3618`).
- Updates `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` to record
  `mappedDocxOpenXmlCoreCases = 34`, `docxOpenXmlCoreAssertions = 413`, and
  `benchmarkDenominator.mapped = 2599`.

## Dependency Closure

No new support component is needed. This reuses native PHP `DocxReader` DOM
parsing, `ZipPackage` test fixtures, and existing Markdown/WordPress writer
span serialization. Full upstream Pandoc runner parity remains out of scope for
this isolated support-library slice because it requires hydrated Pandoc/Haskell
test executables.

## Non-overlap

This does not repeat recent DOCX work for proof/permission ranges, directional
inline wrappers, typographic run metrics, DrawingML textbox/geometry/picture
metadata, subdocuments, field-code provenance, section header/footer
relationships, note separators, or legacy form fields. It is limited to
previously dropped run-content field marker elements.
