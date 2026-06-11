# Shared ZIP/OPC local-header variable fields

Slice: `plib-9eb18` (`20260611T164639Z`)

## Scope

Shared ZIP/OPC package preflight now exposes local-header variable-field provenance before package payload handoff. The new `ZipPackage::localHeaderVariableFieldsPreflight()` reports local name and local extra-field byte totals plus per-entry fixed-header, raw-name, local-extra-field, and data-start offsets from raw ZIP bytes.

`ZipPackage::strictImportPreflight()` and `ZipPackage::rawStrictImportPreflight()` now include the same `localHeaderVariableFields` summary, and `localHeaderPreflight()` carries matching aggregate byte counts and per-entry offset fields after package construction.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Focused post-rebase verification passed `1 test files, 3444 assertions, 0 failures`.
Full post-rebase Pandoc lane verification passed `44 test files, 66399 assertions, 0 failures`.
