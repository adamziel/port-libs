# ODF Manifest Preferred View Mode Package Provenance

Bead: `plib-8sznu`

## Scope

- Added native `OdfReader` package provenance rollups for `manifest:preferred-view-mode`.
- The import report/document manifest now exposes aggregate mode counts, ordered preferred-view file entries, manifest order propagation, and per-ZIP-part inventory fields.
- No Pandoc, office suite, unzip/zip, browser, external validator, online service, or live provider runner is used.

## Accounting

- `phpPass`: `3131 -> 3132`
- `mappedOdfManifestPreferredViewModeCases`: `1`
- `odfManifestPreferredViewModeAssertions`: `16`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 4070 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 66568 assertions, 0 failures`
