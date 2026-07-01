# DOCX ZIP Source-Record Creator Host Systems

## Slice

`DocxOpenXmlReader` now carries loaded DOCX ZIP source-record creator-host-system summaries through `packageProvenance.summary` and `packageIdentity`.

The summary groups loaded package parts by ZIP creator host ID/name and records part counts, source-record byte totals, version-made/version-needed comparison counts, directory roots, compression methods, content-type source/base buckets, role buckets, and largest source-record part metadata.

## Guardrails

- The source-record summaries are metadata-only and do not expose package payload bytes.
- The fixture is built with `ZipPackage::fromParts()` and does not invoke external ZIP, Office, Pandoc, validator, or service tools.
- `packageIdentity` mirrors the summary fields for downstream package review handoff.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCreatorHostSystemsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCreatorHostSystemsTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused result: 6 files, 12,753 assertions, 0 failures.
