# DOCX ZIP Source Record Part Fixed Fields

Slice: `plib-kvn9r`
Date: 2026-07-01

## Change

- `DocxOpenXmlReader` now carries ZIP source-record fixed-field provenance from
  `zipPackage['byPackagePath']` onto loaded package-part inventory records.
- Loaded DOCX parts now preserve local fixed-header offsets/values,
  central-directory fixed-header values, creator host/version metadata, DOS and
  internal file attribute provenance, and fixed-field issue lists without
  exposing package bytes.
- This keeps part-level review handoff in parity with entry-level ZIP
  provenance so callers do not need to rejoin package parts against raw ZIP
  entry rows for source-record review.

## Coverage

- Added a self-contained DOCX ZIP fixture that marks `word/document.xml` as a
  Windows/NTFS-created entry with DOS hidden/archive attributes and the internal
  text attribute.
- The regression compares the loaded package part against the matching
  `byPackagePath` ZIP entry for local fixed-header fields, central-directory
  fixed-header fields, creator host/version values, and platform attribute
  diagnostics.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php`
  - 1 file, 63 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartZipSourceRecordFixedFieldsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php`
  - 3 files, 128 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 12,315 assertions, 0 failures

Direct-format parity accounting remains unchanged. This slice is limited to
bounded native PHP DOCX ZIP/OpenXML package metadata and does not invoke Pandoc,
office suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node tooling, live
services, or external validators.
