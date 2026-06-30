# ZIP package manifest data descriptor source records

Date: 2026-06-30
Slice: `plib-831x4`

## Change

- `ZipPackage::packageManifestPreflight()` now reports local-record source
  provenance for every entry:
  - `localRecordOffset`
  - `localRecordBytes`
  - `localRecordEnd`
  - `localRecordSha256`
- Manifest entries using general-purpose bit 3 now also report data-descriptor
  source-record provenance:
  - `usesDataDescriptor`
  - `dataDescriptorOffset`
  - `dataDescriptorBytes`
  - `dataDescriptorEnd`
  - `dataDescriptorSha256`
- Package-level manifest totals now include `localRecordBytes`,
  `dataDescriptorEntryCount`, and `dataDescriptorBytes`.

## Coverage

- Added an in-memory ZIP fixture with one stored part and one deflated part using
  a signed data descriptor.
- Verified the descriptor span and hash against the exact archive bytes.
- Verified constructed and raw strict import preflights return the same package
  manifest.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4,904 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,668 assertions, 0 failures

Direct-format parity remains active in lane status. This slice only extends
bounded native PHP shared ZIP/OPC package metadata and does not invoke Pandoc,
office suites, TeX/browser engines, `zip`/`unzip`, Node tooling, Jupyter, live
services, or external validators.
