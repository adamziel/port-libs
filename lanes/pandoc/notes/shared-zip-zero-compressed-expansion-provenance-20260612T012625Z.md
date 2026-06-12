# Shared ZIP/OPC zero-compressed expansion provenance

Slice: `plib-p3eel` on current main.

## Change

- `ZipPackage::sizePreflight()` now reports entries whose declared compressed
  size is zero while their declared uncompressed size is nonzero:
  - `unknownExpansionRatioEntryCount`
  - `hasUnknownExpansionRatioEntries`
  - `unknownExpansionRatioEntries`
- `ZipPackage::centralDirectorySizePreflight()` exposes the same per-entry
  provenance before package instantiation and emits `expansion-ratio-unknown`
  when a max expansion-ratio policy is requested.
- Strict package import now uses the per-entry bucket, so a mixed archive with a
  finite aggregate ratio still preserves the zero-compressed entry as an
  explicit ratio-review blocker.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3789 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68657 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
