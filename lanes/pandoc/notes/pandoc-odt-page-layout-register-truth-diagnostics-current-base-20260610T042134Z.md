# Pandoc ODT Page Layout Register-Truth Diagnostics

## Scope

- Preserved `style:register-truth-ref-style-name` from
  `style:page-layout-properties` in page-layout metadata.
- Added native style diagnostics for missing register-truth target styles under
  `odf-page-layout-missing-register-truth-style`.
- Verified both broad style diagnostic aggregation and a focused page-layout
  fixture with one valid and one missing register-truth style reference.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 3434 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58672 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner,
LibreOffice, Word, zip/unzip, browser renderer, external validator, online
service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2924 to 2925.
- `phpFail` remains 0.
- `suiteProgress` moves from 827 to 828 focused mapped checks.
- The mapped denominator moves from 3106 to 3107 with one focused ODT
  register-truth diagnostics pass case.
- `mappedOdfStyleDiagnosticsCases` moves from 1 to 2.
- `odfStyleDiagnosticsAssertions` moves from 11 to 20.
