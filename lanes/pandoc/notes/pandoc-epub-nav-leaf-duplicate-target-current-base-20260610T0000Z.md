# Pandoc EPUB Nav Leaf Link and Duplicate Target Diagnostics

## Scope

- Added native EPUB navigation diagnostics for leaf `<span>` nav entries that
  carry a label but no anchor target.
- Added same-section duplicate navigation target grouping in nav document
  diagnostics.
- Preserved the current `documentDiagnostics` model for item-level label and
  href issues.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 3834 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 59003 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner,
EPUBCheck, browser renderer, zip/unzip command, external validator, online
service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2938 to 2939.
- `phpFail` remains 0.
- The mapped focused suite count moves from 841 to 842 with one focused EPUB
  nav leaf link and duplicate target diagnostics pass case.
