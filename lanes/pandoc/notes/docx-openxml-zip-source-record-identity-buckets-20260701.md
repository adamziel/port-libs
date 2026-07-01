# DOCX/OpenXML ZIP Source-Record Identity Buckets

Slice: `plib-ujvne`

## Change

- `DocxOpenXmlReader` now carries loaded ZIP source-record directory-root, content-type, content-type-source, and package-part-extension bucket metadata into `packageIdentity`.
- The identity payload preserves the existing compression-method and role source-record handoff, adds aggregate source-record byte/count fields, and keeps `largestSourceRecordPart` records metadata-only without exposing package contents.
- This does not add a new upstream mapped DOCX case count; it tightens direct-format package parity by making existing DOCX package-ingestion source-record review buckets part of the canonical metadata-only identity.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityLookupMapsTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityStemLookupMapsTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecord*Test.php lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php -r 'json_decode(...)'` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc PANDOC_STATUS.md`
- Conflict-marker scan of changed files under `lanes/pandoc` and `PANDOC_STATUS.md`
