# DOCX ZIP Source Record Package Part Raw Extensions

Slice: `plib-x8y50`

DOCX/OpenXML package ingestion now summarizes loaded ZIP source records by exact
raw package part extension, preserving extension casing while keeping payload
bytes blocked.

## Handoff

- `packageProvenance.summary.partZipSourceRecordPackagePartRawExtension*`
  exposes raw-extension bucket counts, source-record byte sums, extensionless
  part totals, uppercase/normalized part totals, data-descriptor counts, and
  source byte-span issue counts.
- `packageProvenance.summary.partZipSourceRecordPackagePartRawExtensions`
  carries metadata-only rows with raw and normalized extension keys, directory
  roots, content-type sources, content-type bases, compression methods, roles,
  part names, and largest-source-record-part rollups.
- `packageProvenance.packageIdentity` mirrors the same fields for stable
  package identity handoff without exposing ZIP payload bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartRawExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartRawExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartRawExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartBaseNameStemsTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartRawExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordExpansionRatioBucketsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartRawExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartBaseNameStemsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
