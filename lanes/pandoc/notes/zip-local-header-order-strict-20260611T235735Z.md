# Shared ZIP/OPC Local Header Order Strict Diagnostics

Bead: `plib-b5sxm`

Base: current `origin/main` `8086676050`

## Slice

Shared `ZipPackage` strict/raw import preflight now treats central-directory order mismatches against local-header order as explicit review diagnostics:

- `strictImportPreflight()` now includes `central-directory-local-header-order-mismatch` and marks the strict packet invalid when local-header order differs from central-directory order.
- `rawStrictImportPreflight()` carries the same diagnostic while preserving `canInstantiate=true`, so DOCX/EPUB/ODF review queues can still inspect package metadata before conversion handoff.

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` (1 file, 3608 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 67330 assertions, 0 failures)
