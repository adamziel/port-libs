# Pandoc EPUB Nav Normalized Target Collisions

## Scope

This slice adds direct `EpubPackageReader` metadata diagnostics for EPUB nav
hrefs whose distinct raw values normalize to the same package target.

`epub.navReport` now records:

- per-section normalized collision group and item counts;
- distinct raw href groups for percent-encoding, case, dot-segment, and fragment
  collisions;
- external nav target diagnostics;
- local fragment and fragment-only target diagnostics;
- package-root escape classification as unsafe nav targets.

The existing `epub.toc` entry shape remains unchanged. The slice stays inside
native package metadata and does not invoke Pandoc, EPUBCheck, zip/unzip,
ZipArchive, browser renderers, Node, online services, or external validators.

## Verification

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 file, 255 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 75547 assertions, 0 failures

## Accounting

- `phpPass`: `3351 -> 3352`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3311 -> 3312`
- `mappedEpubNavNormalizedTargetCollisionCases`: `1`
- `epubNavNormalizedTargetCollisionAssertions`: `42`
