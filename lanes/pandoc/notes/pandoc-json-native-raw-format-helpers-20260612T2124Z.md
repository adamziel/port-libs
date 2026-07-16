# Pandoc JSON/native raw Format helpers

Bead: `plib-dpflq`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` and `NativeReader` now accept tagged `Format` helper
constructors in `RawBlock` and `RawInline` format slots. Both string content and
single-item list content normalize into the shared `format` field while the full
helper payload is retained as `formatNative` with `formatConstructor = Format`.

`PandocJsonWriter` and `NativeWriter` now preserve a valid `formatNative` helper
when the shared raw format is unchanged, even if raw text edits force the raw
constructor to regenerate from AST state. If the raw format itself changes, the
writers emit the canonical string format and drop the stale helper.

No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Accounting

- `phpPass`: `3269 -> 3270`
- `phpFail`: `0`
- `mappedJsonNativeRawFormatHelperCases`: `+1`
- `jsonNativeRawFormatHelperAssertions`: `+54`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1832 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73235 assertions, 0 failures`
