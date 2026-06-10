# DOCX/OpenXML webSettings package ingestion

Scope: native PHP DOCX/OpenXML package ingestion only.

## What changed

- `DocxReader` now resolves the document-level `webSettings` relationship
  through the OPC graph and exposes part, content type, relationship, target,
  existence, and issue provenance as `metadata.docxWebSettings`.
- The same bounded metadata is also surfaced under
  `importReport.webSettings` for reviewer handoff.
- Parsed `word/webSettings.xml` now preserves common browser/export policy
  fields: optimize-for-browser, PNG/VML/CSS flags, single-file/folder/name
  policy, encoding, target screen size, and pixels-per-inch.
- Content-type comparison uses the base media type so parameterized
  `webSettings+xml` values remain accepted while still preserving the raw
  content type.

## Focused coverage

- Added one `DocxReaderTest.php` case for relationship-selected
  `word/webSettings.xml` with a query/fragment target, parameterized content
  type, parsed export-policy fields, and import-report parity.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file
  - 4692 assertions
  - 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files
  - 60673 assertions
  - 0 failures

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, browser
renderer, external validator, online service, live provider test, or
live-service provider test was run.

## Accounting

- `phpPass`: 2988 -> 2989 on the current lane-status counter basis.
- mapped denominator: 3143 -> 3144 on the current manifest counter basis.
- DOCX/OpenXML core cases: 35 -> 36.
- DOCX/OpenXML core assertions: 476 -> 497.

This slice does not repeat numbering, settings/font-table, theme, section,
glossary, altChunk, embedded object, subdocument, custom XML, comment, revision,
field-code, or package-root relationship work. It owns only document-level
`webSettings` package ingestion.
