# ZIP Package Manifest Compressed Payload Hash

Hook: `plib-5haqs`, Pandoc shared ZIP/OPC package core blocker slice.

## Summary

- Added `compressedDataSha256` to each `ZipPackage::packageManifestPreflight()`
  entry.
- The hash is computed from the exact compressed payload bytes referenced by the
  local header, so DOCX, EPUB, and ODF package reviewers can cite payload
  provenance without inflating or exposing entry contents.
- The raw strict import path and instantiated strict import path return the same
  package manifest.

## Accounting

- `phpPass`: `16348 -> 16349`
- mapped upstream manifest cases: `15957 -> 15958`
- `mappedZipPackageManifestCompressedDataHashCases = 1`
- `zipPackageManifestCompressedDataHashAssertions = 8`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4844 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 195 files, 169914 assertions, 0 failures

No Pandoc, office suites, `zip`/`unzip`, browser renderers, Node tooling, live
services, or external validators were invoked.
