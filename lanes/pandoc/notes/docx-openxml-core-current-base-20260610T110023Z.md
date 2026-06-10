# DOCX/OpenXML officeDocument readiness

Scope: native PHP DOCX/OpenXML package ingestion only.

## What changed

- `DocxReader` now runs the existing OPC `officeDocument` root preflight before
  parsing the WordprocessingML body.
- Invalid root office-document content types now stop ingestion before body
  parsing instead of allowing a package with `application/xml` (or another
  non-WordprocessingML type) to be treated as a DOCX body.
- Successful DOCX imports expose the root `officeDocument` preflight under
  `importReport.officeDocument`, including the selected target part, content
  type, validity, and relationship issues.

## Focused coverage

- Added one focused `DocxReaderTest.php` case for invalid office-document
  content-type rejection.
- Extended the existing DOCX package-ingestion test with import-report
  `officeDocument` readiness assertions.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file
  - 4550 assertions
  - 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files
  - 59564 assertions
  - 0 failures

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service provider
test was run.

## Accounting

- `phpPass`: 2952 -> 2953
- mapped denominator: 3124 -> 3125
- DOCX/OpenXML core cases: 34 -> 35
- DOCX/OpenXML core assertion inventory: 401 -> 409

This slice does not repeat numbering relationship provenance, numbering content
type diagnostics, picture-bullet relationships, glossary, settings,
altChunk, embedded object, subdocument, custom XML, section, comments, revision,
or field-code work. It owns only root office-document package readiness.
