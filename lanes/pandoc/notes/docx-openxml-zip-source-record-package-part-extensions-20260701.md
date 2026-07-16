# DOCX ZIP Source Record Package Part Extensions

Slice: `plib-rlnry`

## Scope

DOCX/OpenXML package ingestion now summarizes loaded ZIP source-record byte
provenance by normalized package part extension. The handoff complements the
existing source-record directory-root, compression-method, role, and content-type
buckets while keeping package bytes blocked.

## Handoff

- `packageProvenance.summary.partZipSourceRecordPackagePartExtension*` exposes
  extension bucket counts, source-record byte sums, extensionless package-part
  counts, data-descriptor counts, and source byte-span issue counts.
- `packageProvenance.summary.partZipSourceRecordPackagePartExtensions` carries
  per-extension metadata-only rows with directory-root, content-type source,
  content-type base, compression-method, role, part-name, source byte-span, and
  largest-source-record-part rollups.
- Extensionless loaded package parts use the existing `(none)` bucket convention
  and never expose source or payload bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php` with 1 file, 38 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php` with 5 files, 180 assertions, 0 failures.
