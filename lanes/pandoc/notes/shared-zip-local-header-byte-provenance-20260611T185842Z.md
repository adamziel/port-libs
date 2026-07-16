# Shared ZIP local header byte provenance

Base: `0ba6b0e01ef0` (`origin/main`, 2026-06-11).
Bead: `plib-oh63t`.

## Slice

Shared ZIP package local-header preflight now exposes byte provenance for local fixed headers and variable fields, matching the central-directory variable-field review surface.

`localHeaderPreflight()` now reports aggregate local header byte totals plus per-entry offsets and lengths for the fixed header, local variable fields, local name, and local extra field. The fields flow through object strict import preflight and raw strict import preflight via `strictImport.localHeaders`.

This is a bounded native PHP provenance slice only; it does not shell out to Pandoc, office suites, zip/unzip, browser engines, external validators, online services, or live providers.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` (1 file, 3212 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 65667 assertions)
