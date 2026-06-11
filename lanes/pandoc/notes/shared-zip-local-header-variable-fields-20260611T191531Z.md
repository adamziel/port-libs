# Shared ZIP Local Header Variable Fields

Date: 2026-06-11
Base: current main caf9a25cb

## Scope

This slice extends native PHP shared ZIP/OPC package preflight metadata without invoking Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Behavior

- `ZipPackage::localHeaderPreflight()` now reports aggregate local header variable-field bytes.
- The summary separates local name bytes from local extra-field bytes and counts entries with local extra fields.
- Each local entry now exposes variable-field, name, and extra-field offsets and lengths.
- Strict import preflight carries the same local header provenance for DOCX/EPUB/ODF/OPC review handoff.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 test file, 3209 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 65481 assertions, 0 failures.

## Accounting

- `phpPass`: 3103 -> 3104
- `phpFail`: 0
- Added `mappedSharedZipLocalHeaderVariableFieldCases`: 1
- Added `sharedZipLocalHeaderVariableFieldAssertions`: 26
