# DOCX/OpenXML Custom XML External Target Policy

Slice: `plib-cnuxr`, DOCX/OpenXML package ingestion.

This slice keeps DOCX custom XML package ingestion metadata-only while exposing
safe versus unsafe external target policy for both document-level `customXml`
relationships and nested `customXmlProps` relationships. `DocxOpenXmlReader`
now carries external target kind, scheme, allowed flag, issue codes, and summary
rollups for custom XML data-store parts without fetching external targets or
exposing external payload bytes.

Focused accounting:

- Added one focused DOCX/OpenXML behavior case:
  `summarizes docx custom xml external target policy for package review`.
- Focused `DocxOpenXmlReaderTest.php` moved to 9,783 assertions with 0 failures.
- This is a package-provenance slice only; it does not add a new direct format
  token or invoke Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`,
  ZipArchive, browser renderers, Node tooling, external validators, online
  services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 9,783 assertions, 0 failures
