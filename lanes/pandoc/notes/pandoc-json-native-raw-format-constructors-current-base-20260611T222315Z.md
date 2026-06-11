# Pandoc JSON/native raw Format constructors

Bead: `plib-0441f`
Base: `71ce25fbe`
Scope: JSON/native AST constructor completeness.

## Change

`PandocJsonReader` and `NativeReader` now accept tagged `Format` helper constructors for `RawBlock` and `RawInline` payloads.

`PandocJsonWriter` and `NativeWriter` preserve the source tagged `Format` wrapper when edited raw blocks and raw inlines are regenerated. If a node keeps `formatNative` but changes its normalized format, the writers rebuild the same `Format` wrapper around the new format string.

Bare-string raw formats continue to emit bare strings.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test file, 1117 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66708 assertions, 0 failures`

Metric:

- `phpPass`: `3133 -> 3134`
- `phpFail`: `0`
- `mappedJsonNativeRawFormatConstructorCases`: `1`
- `jsonNativeRawFormatConstructorAssertions`: `24`

No Pandoc executable, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
