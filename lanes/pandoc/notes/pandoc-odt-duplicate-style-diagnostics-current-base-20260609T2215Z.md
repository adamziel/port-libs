# Pandoc ODT Duplicate Style Diagnostics Slice

## Scope

- Added bounded native `OdfReader` import-report diagnostics for duplicate named
  ODT style catalog entries that would otherwise be silently shadowed.
- Reports duplicate names across style definitions, font faces, list styles,
  data styles, table templates, page layouts, and master pages.
- Keeps rendering behavior unchanged; the slice only enriches native reviewer
  diagnostics under `lanes/pandoc`.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 3412 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58190 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, zip/unzip command, TeX/PDF engine, browser renderer, external validator,
online service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2893 to 2894 after rebase onto current main.
- `phpFail` remains 0.
- The mapped denominator moves from 3090 to 3091 with one focused ODT duplicate
  style diagnostics pass case and 14 focused assertions.
