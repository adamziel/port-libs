# Shared ZIP/OPC zero-byte size provenance

Slice: `plib-i57v8` on current main `8aa111e490c4`.

## Change

- `ZipPackage::sizePreflight()` now reports zero-byte payload buckets:
  - `zeroByteEntryCount`
  - `zeroByteFileCount`
  - `emptyDirectoryEntryCount`
  - `hasZeroByteEntries`
  - `zeroByteEntries`
- The zero-byte entry list reuses the existing per-entry size summary shape, so strict and raw strict import review can inspect names, compression method, directory state, compressed size, uncompressed size, and expansion ratio before package bytes are exposed.
- `strictImportPreflight()` and `rawStrictImportPreflight()` carry the data through their existing `size` summary without adding new diagnostics or invoking external ZIP tooling.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3758 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68522 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
