# DOCX OpenXML commentsExtended package ingestion slice

Mapped one bounded DOCX/OpenXML package-ingestion case on current base `97dc5bbca2a7`: `DocxOpenXmlReader` now loads relationship-selected Word commentsExtended parts and preserves `w15:commentEx` paragraph, resolved, and thread-parent metadata on imported comment note summaries and AST note attrs.

The slice preserves package provenance for the commentsExtended relationship target, including query/fragment suffixes, content type source, selected XML root validation, relationship type summaries, and the stripped package part path. It does not add a new direct format parity row; phpPass should move by one focused DOCX OpenXML test case while phpFail stays zero.

Verification:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 test file, 862 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 65542 assertions, 0 failures.
