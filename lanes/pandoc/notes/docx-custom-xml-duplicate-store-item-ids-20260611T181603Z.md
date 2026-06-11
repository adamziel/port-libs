# DOCX Custom XML Duplicate Store Item IDs

Bead: `plib-sixjz`
Base: `03e2b8157`

This slice keeps DOCX/OpenXML custom XML package ingestion reviewable when
multiple custom XML properties parts reuse the same `ds:itemID`. The native PHP
reader now aggregates custom XML store item IDs, reports duplicate IDs with the
affected customXml and properties relationship/part references, and annotates
the affected custom XML item and properties records with
`duplicate-store-item-id`.

Verification on 2026-06-11 UTC:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 978 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66364 assertions, 0 failures

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
