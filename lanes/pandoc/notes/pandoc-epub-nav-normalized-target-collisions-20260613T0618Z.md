# Pandoc EPUB Nav Normalized Target Collisions

## Scope

This slice adds direct `EpubPackageReader` metadata diagnostics for EPUB nav
hrefs whose distinct raw values normalize to the same package target.

`epub.navReport` now records:

- `navReport.normalizedCollisionSections` with per-section normalized collision
  group and item counts;
- distinct raw href groups for percent-encoding, case, dot-segment, and fragment
  collisions;
- external nav target diagnostics;
- local fragment and fragment-only target diagnostics;
- package-root escape classification and separate unsafe local target
  diagnostics.

The current rich `epub.toc` entry metadata remains intact. The slice stays
inside native package metadata and does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, Node, online services, or external
validators.

## Verification

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 file, 815 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 81785 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

## Accounting

- `phpPass`: `3485 -> 3486`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3414 -> 3415`
- `mappedEpubNavNormalizedTargetCollisionCases`: `1`
- `epubNavNormalizedTargetCollisionAssertions`: `47`
