# Pandoc EPUB Nav Item Label Diagnostics: Superseded MR

## Scope

- `plib-23ri` proposed primary EPUB navigation item-label diagnostics for `EpubPackage` and `EpubReader`.
- Current `main` already contains the broader accepted implementation from `plib-3yj8` and `plib-96sx`.
- The retained implementation reports `missing-primary-nav-item-label`, tracks `missingPrimaryItemLabelCount`, and also keeps non-primary `missing-nav-entry-label` coverage via `missingEntryLabelCount`.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Result: `1 test files, 680 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Result: `1 test files, 3770 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `42 test files, 58214 assertions, 0 failures`

## Decision

No source or accounting changes were reapplied from the worker branch because doing so would downgrade the accepted field names and diagnostics already on `main`.
