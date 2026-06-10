# DOCX/OpenXML webSettings support-part metadata

Scope: native PHP DOCX/OpenXML package ingestion only.

## What changed

- `DocxReader` now follows the document-level `webSettings` relationship and exposes
  the support part as `metadata.docxWebSettings` and `importReport.webSettings`.
- The report preserves relationship target metadata, expected content type,
  missing-part diagnostics, invalid web-settings content-type diagnostics, and
  bounded web-view flags/values:
  - `optimizeForBrowser`, `allowPng`, `doNotSaveAsSingleFile`,
    `doNotOrganizeInFolder`, `doNotRelyOnCss`, `doNotUseLongFileNames`,
    `saveSmartTagsAsXml`
  - `encoding`, `targetScreenSize`, `pixelsPerInch`
- Invalid `webSettings` content types are reported for reviewer handoff without
  blocking import of the readable WordprocessingML body.

## Focused coverage

- Added a focused valid `webSettings` package fixture and assertions in
  `DocxReaderTest.php`.
- Added a focused content-type mismatch fixture proving the body still imports
  while `invalid-web-settings-content-type` is exposed in the support-part
  relationship and top-level web-settings issues.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file
  - 4632 assertions
  - 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files
  - 59909 assertions
  - 0 failures

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service provider
test was run.

## Accounting

- `phpPass`: 2960 -> 2962
- `phpFail`: 0
- Focused DOCX reader assertions after slice: 4632

This slice does not repeat accepted officeDocument readiness, numbering
relationship provenance, numbering content-type diagnostics, picture-bullet
relationships, glossary, settings, altChunk, embedded object, subdocument,
custom XML, section, comments, revision, field-code, or package-root
relationship role work. It owns only DOCX `webSettings` support-part metadata
and diagnostics.
