# shared-zip-local-header-fixed-field-provenance-20260611T2202Z

Slice: `plib-d7luj`, shared ZIP/OPC package primitives.
Base: current `origin/main` `4bb725eee`.

## Change

`ZipPackage::localHeaderMetadataPreflight()` now preserves local-header
fixed-field byte provenance before package construction. The raw metadata packet
includes offsets for the local fixed header, signature, version-needed, general
purpose flags, compression method, DOS timestamps, CRC32, compressed and
uncompressed sizes, name and extra-field lengths, variable fields, raw name,
extra fields, and local header end.

The same summary is carried through raw strict import preflight, so DOCX, EPUB,
ODF, and other OPC package review queues can inspect local-header spoofing or
metadata mismatches without exposing package part bytes or invoking external
tools.

No Pandoc, office suites, zip/unzip, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed: 1 test file, 3492 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66518 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3129 -> 3130`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3215 -> 3216`.
- Added one focused `ZipPackageTest` case with 48 assertions for local-header
  fixed-field byte provenance.
