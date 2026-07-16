# DOCX OpenXML Embedded Object Package Handoff

Slice: `plib-oaaou` DOCX OpenXML package ingestion core blocker.
Base: current main `af666c9e0`.

## Change

- Added inert `docx.embeddedObjects` metadata for `o:OLEObject` relationship handoff.
- Summarized referenced and unreferenced embedded package/OLE relationships, including package target query/fragment suffixes, bytes, content-type parameter provenance, and part-local relationship counts.
- Preserved diagnostics for missing package targets, missing content types, external OLE links, and unknown object relationship IDs.

No Pandoc, Word, LibreOffice, office suites, zip/unzip tools, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 650 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64492 assertions, 0 failures
