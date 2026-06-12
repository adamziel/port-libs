# Pandoc DOCX/OpenXML package-root escape relationships - 20260612T035217Z

Bead: plib-64og1
Base: origin/main 39b7b38bf6

Focused verification:
`php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
Result: 1 test file, 1776 assertions, 0 failures.

Full verification:
`php tools/run-tests.php lanes/pandoc/tests`
Result: 44 test files, 70507 assertions, 0 failures.

Mapped one native `DocxOpenXmlReader` package-ingestion boundary case. Relationship targets with parent traversal beyond the package root now remain accepted for bounded ingestion but carry package-root escape provenance in relationship summaries, relationship-type buckets, and package review summaries.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.
