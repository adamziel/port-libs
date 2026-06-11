# DOCX OpenXML Document Background Provenance

Bead: `plib-91bsz`
Base: `9e53a22c9b`
Date: 2026-06-11 UTC

This slice adds DOCX package-ingestion provenance for `w:background` in `word/document.xml`. The reader now reports WordprocessingML background color/theme attributes, VML background/fill metadata, and any `r:id`/`r:embed`/`r:link` relationship as a document-relationship target with the existing query/fragment, content-type parameter, existence, and issue metadata.

The data is exposed on `docx.documentBackground`, mirrored into `packageProvenance.documentBackground`, and summarized through `packageProvenance.summary.documentBackground*` flags so review queues can identify themed/image document backgrounds without treating them as normal inline content images.

Focused fixture coverage adds an in-memory DOCX package with a VML background fill targeting `word/media/background.jpg?bg=cover#tile`, asserting color/theme fields, VML fill fields, relationship target suffixes, content-type parameters, package inventory roles, relationship-type summaries, and zero background issues. No Pandoc, office suites, zip/unzip, browsers, external validators, online services, or live providers are invoked.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`: 1 test file, 1132 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: `PASS_COUNT=3312`, 44 test files, 67177 assertions, 0 failures
