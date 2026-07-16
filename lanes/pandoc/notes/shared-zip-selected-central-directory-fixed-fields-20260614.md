# Shared ZIP selected central-directory fixed fields

## Scope

- Slice: `pandoc-zip-selected-central-directory-fixed-field-handoff`
- Base: current main `3f33d1dd5e`
- Area: shared ZIP/OPC selected-entry package handoff before DOCX, EPUB, and ODF readers expose package bytes.

## Change

- `ZipPackage::entryHandoffPreflight()` now carries selected-entry central-directory fixed-header field provenance.
- The selected-entry provenance reports fixed-header offsets and values for version-made-by, version-needed-to-extract, general-purpose flags, compression method, DOS timestamps, CRC32, compressed and uncompressed sizes, raw name/extra/comment lengths, disk start, internal and external attributes, and the local-header-offset field.
- Handoff entries now receive the same fixed-header provenance as the selected aggregate entries.
- Aggregate selected-entry handoff metadata now reports central-directory fixed-field entry counts and issue-entry buckets for fixed-header mismatches.

## Direct-Format Accounting

- `phpPass`: `3507 -> 3508`
- Added cases: `mappedZipSelectedCentralDirectoryFixedFieldHandoffCases = 1`
- Added assertions: `zipSelectedCentralDirectoryFixedFieldHandoffAssertions = 50`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed `1 test files, 4664 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` passed `46 test files, 82652 assertions, 0 failures`.

No Pandoc, office suites, TeX/PDF engines, browser renderers, zip/unzip, ZipArchive, external validators, online services, live provider tests, or live-service provider tests were invoked.
