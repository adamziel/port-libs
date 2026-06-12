# Shared ZIP/OPC raw local extra-field records

Slice: `plib-yuitf` (`20260611T231508Z`)

Target: current main `ef6c412430`.

## Scope

Shared ZIP/OPC package preflight now exposes raw local-header extra-field record provenance before package construction. `ZipPackage::localHeaderVariableFieldsPreflight()` reports aggregate local extra-field record counts and IDs, per-entry record IDs, structure issues, record/data/end byte offsets, and the largest local extra-field-bearing entry.

The raw summary is carried through `ZipPackage::rawStrictImportPreflight()` and matches instantiated `strictImportPreflight()` plus object-level `localHeaderPreflight()` record provenance.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Focused post-rebase verification passed `1 test files, 4350 assertions, 0 failures`.
Full post-rebase Pandoc lane verification passed `44 test files, 72774 assertions, 0 failures`.
