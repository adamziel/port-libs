# Shared ZIP Selected Local Header Variable Fields

## Scope

- Slice: `shared-zip-selected-local-header-variable-fields`
- Bead: `plib-chu3j`
- Area: shared ZIP/OPC package primitives before DOCX, EPUB, and ODF readers expose package bytes.

## Change

- `ZipPackage::entryHandoffPreflight()` now preserves selected-entry local-header variable-field byte provenance.
- Selected source spans now include raw local-name and local extra-field offsets, byte counts, and SHA-256 digests.
- Aggregate selected-entry handoff counters now report local fixed-header bytes, local variable-field bytes, raw local-name bytes, local extra-field bytes, and local review-field bytes.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 4616 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 82477 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc/src/ZipPackage.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/lane-status.json lanes/pandoc/notes/shared-zip-selected-local-header-variable-fields-20260614.md`

No Pandoc, office suites, TeX/PDF engines, browser renderers, zip/unzip,
ZipArchive, external validators, online services, live provider tests, or
live-service provider tests were invoked.

## Parity Accounting

- `phpPass`: `3502 -> 3503`
- `phpFail`: `0`
- `mappedZipSelectedLocalHeaderVariableFieldHandoffCases`: `1`
- `zipSelectedLocalHeaderVariableFieldHandoffAssertions`: `34`
