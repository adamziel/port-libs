# DOCX ZIP Timestamp Source Buckets

Slice: `plib-b2hu1`

## Scope

DOCX/OpenXML package ingestion now summarizes loaded package parts by ZIP
modification-time source. The new handoff uses metadata already attached to each
loaded ZIP entry and package inventory part, so it does not expose document bytes
or shell out to external ZIP or office tooling.

## Handoff

- `packageProvenance.summary.partZipTimestampSource*` exposes timestamp-source
  bucket counts, loaded-part byte sums, source-record byte sums, modified-part
  counts, and modification-time issue-part counts.
- `packageProvenance.summary.partZipTimestampSources` carries metadata-only
  rows for `extended-timestamp`, `dos`, and `(missing)` timestamp sources with
  directory-root, local/central timestamp-source, content-type source/base, role,
  part-name, source-record, earliest/latest modified part, and
  largest-source-record-part rollups.
- Missing timestamps are explicit via the existing `(missing)` bucket convention.
  The per-part rollups keep `contents` out of the summary.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipTimestampSourcesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipTimestampSourcesTest.php`
  passed with `1 test files, 44 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 12355 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php`
  passed with `3 test files, 139 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipTimestampSourcesTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php`
  passed with `5 test files, 12538 assertions, 0 failures`.
