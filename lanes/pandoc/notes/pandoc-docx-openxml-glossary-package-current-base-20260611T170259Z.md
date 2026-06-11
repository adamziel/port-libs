# DOCX/OpenXML glossary package slice

- Bead: plib-gmrbe
- Base: current main 16f638244
- Scope: native PHP DOCX/OpenXML package ingestion for glossary/building-block package parts.
- Change: `DocxOpenXmlReader` now exposes `glossaryDocument` relationship targets as metadata-only `glossaryDocuments` summaries with existing/missing counts, valid root state, docPart entry metadata, text summaries, target query/fragment provenance, content-type source, and part-local relationship counts.
- Boundary: native PHP package/XML handling only; no Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
- Verification: `php -l lanes/pandoc/src/DocxOpenXmlReader.php`; `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`; `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed 1 test file, 833 assertions, 0 failures; `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 65476 assertions, 0 failures.
