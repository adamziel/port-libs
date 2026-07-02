# DOCX ZIP Source-Record Package Areas

Bead: plib-1wnun

Date: 2026-07-02

## Slice

- Added DOCX package-area buckets for ZIP source-record provenance in `DocxOpenXmlReader`.
- Exposed `partZipSourceRecordPackageArea*` count, byte, issue, data-descriptor, and detailed row fields through package provenance summaries.
- Mirrored the same metadata into `packageIdentity` so identity snapshots preserve package-area source-record review state without exposing package bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackageAreasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackageAreasTest.php` - 1 file, 36 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartRawExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackageAreasTest.php` - 8 files, 368 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 12,508 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
