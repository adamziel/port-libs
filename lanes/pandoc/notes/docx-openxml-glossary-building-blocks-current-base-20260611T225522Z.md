# DOCX OpenXML glossary building blocks slice

- Bead: `plib-iq6l9`
- Base: `4d330e2a13f167c910bcb5a1e0cf0e68e4c8f9d6`
- Scope: native PHP DOCX/OpenXML package ingestion only, under `lanes/pandoc`.

This slice extends `DocxOpenXmlReader` beyond glossaryDocument package relationship
provenance by summarizing relationship-selected glossary building blocks. The reader
now exposes docPart names, categories, galleries, type tokens, descriptions, GUIDs,
text/block rollups, relationship target suffixes, parameterized content types,
glossary-local relationship sidecars, and package summary counts without invoking
Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests.

Verification on current main:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 1141 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67089 assertions, 0 failures`
