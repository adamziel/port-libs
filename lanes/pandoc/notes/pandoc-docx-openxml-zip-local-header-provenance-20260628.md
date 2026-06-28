# DOCX/OpenXML ZIP local-header provenance - 2026-06-28

Slice: `plib-3zezk`, DOCX/OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now carries metadata-only ZIP local-header provenance from native `ZipPackage::localHeaderPreflight()` into DOCX package review. The provenance is exposed through `zipPackage.localHeaders`, ZIP entry rows, loaded package inventory rows, and `packageProvenance.summary`, including local header offsets, fixed and variable field lengths, extra-field IDs, extra-field structure rows, record spans, contiguous-entry counters, and local header size/CRC fields.

The slice keeps DOCX package bytes blocked: it exposes offsets, counts, IDs, and structure metadata only. Raw local extra-field payload bytes are not exposed.

Focused coverage adds one `DocxOpenXmlReaderTest.php` PASS case with 79 assertions. Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php` (3 files, 12,725 assertions, 0 failures)

Lane accounting:

- `lane-status.json` `phpPass`: `472 -> 473`
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `2,313 -> 2,314`
- Added `mappedDocxZipLocalHeaderProvenanceCases: 1`

No Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
