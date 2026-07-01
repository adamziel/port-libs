# DOCX ZIP source-record compression methods

Slice: `docx-zip-source-record-compression-methods`

## Scope

Added DOCX/OpenXML package provenance rollups for loaded ZIP source-record byte
spans grouped by ZIP compression method.

`DocxOpenXmlReader` now exposes:

- `packageProvenance.summary.partZipSourceRecordCompressionMethod*` aggregate
  counters and byte totals.
- `packageProvenance.summary.partZipSourceRecordCompressionMethods` detailed
  method buckets with local header, compressed data, data descriptor, central
  directory, directory-root, content-type, role, and largest-part provenance.

The slice is metadata-only: it carries offsets, lengths, hashes, counters, and
package-part identities already available from native ZIP parsing, without
exposing raw ZIP record bytes.

## Tests

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php`
- Red-first focused test failed on the missing
  `partZipSourceRecordCompressionMethods` export.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php`
  passed with `1 test files, 31 assertions, 0 failures`.
- Broader adjacent gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordContentTypesTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed after rebase with `6 test files, 12551 assertions, 0 failures`.

## Boundary

No Pandoc, office suite, TeX, browser, Node, zip/unzip, Jupyter, or external
validator was used. The fixture is built in memory with native PHP
`ZipPackage::fromParts()` and read through the DOCX/OpenXML package reader.
