# DOCX content-type base buckets

Bead: `plib-9z4ze`

Current base: `35768390c9`

## Scope

`DocxOpenXmlReader` now summarizes package inventory entries by resolved
content-type base in `packageProvenance.summary.contentTypeBaseBuckets`.

Each bucket captures part count, byte total, relationship-part count, missing
content-type count, raw content-type variants, default extensions, override
part names, content-type source counts, role counts, and package part names.
This lets review queues distinguish default XML parts, parameterized overrides,
relationship sidecars, content media, and untyped/missing package parts without
walking the full inventory.

This is bounded native PHP DOCX/OpenXML package ingestion metadata. It does not
invoke Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 1240 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 67482 assertions, 0 failures`

## Status Delta

- `phpPass`: `3147 -> 3148`
- Added one focused DOCX package-ingestion PASS case.
- Added 39 focused DOCX assertions.
- Added `mappedDocxOpenXmlContentTypeBaseBucketCases = 1`.
- Added `docxOpenXmlContentTypeBaseBucketAssertions = 39`.
