# Pandoc ODT Style Diagnostics Slice

## Scope

- Added bounded native `OdfReader` import-report diagnostics for malformed ODT
  style catalog references.
- Reports missing parent styles, list styles, master pages, data styles, font
  faces, style-map targets, table-template style names, master-page layout
  links, and style inheritance cycles.
- Kept the slice metadata-only and under `lanes/pandoc`; rendering behavior is
  unchanged.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 3384 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56688 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, zip/unzip command, TeX/PDF engine, EPUBCheck, browser renderer, external
validator, online service, live provider test, or live-service provider test was
executed.

## Accounting

- `phpPass` moves from 2808 to 2809.
- `phpFail` remains 0.
- The mapped denominator moves from 3035 to 3036 with one focused ODT style
  diagnostics pass case and 11 focused assertions.
