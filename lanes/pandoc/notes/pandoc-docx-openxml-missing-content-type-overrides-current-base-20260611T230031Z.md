# Pandoc DOCX/OpenXML Missing Content-Type Override Provenance

Slice: `plib-q2xd4`
Base: `e25fac1262`

## Scope

DOCX package ingestion now summarizes `[Content_Types].xml` override declarations whose target parts are absent from the package:

- `contentTypesPart` exposes `missingOverrideCount`, `missingOverrideParts`, and `missingOverrides`.
- Package `summary` mirrors those missing override declarations for review queues.
- Missing override rows preserve content type base, parameter list/map, and declared part name.
- No byte exposure or relationship resolution behavior changes.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 1048 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 66853 assertions, 0 failures
