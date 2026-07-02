# ZIP Package Manifest Basename Lookup Maps

Slice: `plib-caahw`

## Summary

`ZipPackage::packageManifestPreflight()` now exposes direct lookup maps for
shared ZIP/OPC package manifest basename and stem provenance:

- exact package part basename entry counts and entry-name maps;
- case-folded basename entry counts and entry-name maps;
- exact package part basename-stem file counts and entry-name maps;
- case-folded basename-stem file counts and entry-name maps.

The maps are derived from the existing deterministic basename summary rows and
are included in the `zip-package-manifest-v1` hash payload, so downstream OPC
readers can answer common package identity questions without rescanning summary
arrays or reading package payload bytes.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 6069 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 11402 assertions, 0 failures`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`

No Pandoc binary, office suite, TeX/browser engine, unzip/zip command,
ZipArchive, Node tooling, external validator, online service, live provider
test, or payload-expanding external tool was invoked.
