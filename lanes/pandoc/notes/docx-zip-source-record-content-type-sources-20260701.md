# DOCX ZIP source-record content type sources

Slice: `docx-zip-source-record-content-type-sources`

## Scope

Added DOCX/OpenXML package provenance rollups for loaded ZIP source-record byte
spans grouped by content type declaration source.

`DocxOpenXmlReader` now exposes:

- `packageProvenance.summary.partZipSourceRecordContentTypeSource*` aggregate
  counters and byte totals.
- `packageProvenance.summary.partZipSourceRecordContentTypeSources` detailed
  source buckets for default, override, and missing content type declarations.

Each bucket carries local header, compressed data, data descriptor, central
directory, content-type, directory-root, compression-method, role, part-name,
and largest-part provenance. The slice is metadata-only: it exposes byte
offset-derived lengths and existing package-part identities, not raw ZIP record
bytes.

## Tests

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php`
- Red-first focused test failed on the missing
  `partZipSourceRecordContentTypeSources` export.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php`
  passed with `1 test files, 31 assertions, 0 failures`.
- Broader adjacent gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypeSourcesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed after rebase with `7 test files, 12582 assertions, 0 failures`.

## Boundary

No Pandoc, office suite, TeX, browser, Node, zip/unzip, Jupyter, or external
validator was used. The fixture is built in memory with native PHP
`ZipPackage::fromParts()` and read through the DOCX/OpenXML package reader.
