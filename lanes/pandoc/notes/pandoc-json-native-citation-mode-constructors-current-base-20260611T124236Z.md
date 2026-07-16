# Pandoc JSON/native citation mode constructors current base 20260611T124236Z

Bead: `plib-qe99z`
Base: `b65b8f7f683a6f0e76a8cfc006a4a64077709aa1`

## Scope

`PandocJsonReader` and `NativeReader` now retain citation mode helper constructor provenance on citation AST nodes.

Covered constructors:
- `NormalCitation`
- `AuthorInText`
- `SuppressAuthor`

Each citation record keeps the normalized shared `mode` value and now also exposes `citationModeConstructor` plus the original `citationModeNative` enum payload. This closes a bounded JSON/native AST constructor-completeness gap without changing writer output or invoking external Pandoc tooling.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 614 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - `1 test files, 299 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63037 assertions, 0 failures`

## Accounting

- `phpPass`: `3059 -> 3060`
- mapped denominator: `3190 -> 3191`
- Added `mappedPandocJsonNativeCitationModeConstructorCases = 1`
- Added `pandocJsonNativeCitationModeConstructorAssertions = 22`
