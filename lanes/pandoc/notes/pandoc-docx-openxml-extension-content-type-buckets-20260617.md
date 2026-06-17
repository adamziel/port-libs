# DOCX/OpenXML Extension Content-Type Bucket Rollups

- Issue: `plib-hbub7`
- Slice: `pandoc-docx-openxml-extension-content-type-bucket-rollups`
- Base: `origin/main cf04fe3f7b`
- Scope: DOCX/OpenXML package ingestion provenance only.

This slice extends the landed `DocxOpenXmlReader` package part extension summaries with package-level rollup counters for declared defaults, parameterized extension buckets, parameterized extension parts, and extension buckets with missing content types.

It also carries `defaultExtension`, `overridePartName`, and `isRelationshipPart` into each extension bucket `largestPart` summary so reviewer handoff can trace the largest member back to OpenXML content-type resolution without exposing package bytes.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 7054 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 260 files, 178876 assertions, 0 failures
