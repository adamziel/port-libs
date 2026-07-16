# Pandoc EPUB3 Package Inventory Byte Buckets

Bead: plib-mje5a
Base: current main 31a8e12bc6

## Slice

EPUB package inventory summaries now include compressed and uncompressed byte buckets for:

- package inventory roles
- manifest resource kinds
- exposable versus blocked package entries
- unsupported-compression entries

The buckets are computed after byte-exposure policy is finalized, so unsupported or encrypted resources remain blocked in review handoff totals. The same packageInventory payload continues to propagate into the WordPress import summary.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` - 1 file, 2024 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 70694 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
