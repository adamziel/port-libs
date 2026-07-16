# Pandoc Shared ZIP Central Fixed Header Provenance

Slice: shared ZIP/OPC package primitive on current base `abae0725c`.

## Summary

- Added `ZipPackage::centralDirectoryFixedHeaderPreflight()` as a native PHP
  raw ZIP review packet for central-directory fixed-header byte provenance.
- The summary reports per-entry field offsets and values for version-made-by,
  version-needed-to-extract, general-purpose flags, compression method, DOS
  timestamps, CRC32, compressed/uncompressed sizes, variable-field lengths,
  disk start, internal/external attributes, and the local-header-offset field.
- Wired the packet into `rawStrictImportPreflight()` and
  `strictImportPreflight()` as `centralDirectoryFixedHeaders` so DOCX, EPUB,
  ODF, and OPC package review queues can inspect fixed header provenance even
  when another raw ZIP gate blocks package construction.
- Added one focused `ZipPackageTest` case covering raw/object strict summary
  propagation and fixed-header byte accounting.

## Direct-Format Accounting

- `phpPass`: `3123 -> 3124`
- Added cases: `mappedZipCentralDirectoryFixedHeaderCases = 1`
- Added assertions: `zipCentralDirectoryFixedHeaderAssertions = 66`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3403 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66188 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, zip/unzip, Node tooling,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
