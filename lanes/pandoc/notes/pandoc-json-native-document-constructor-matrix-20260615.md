# Pandoc JSON/native document constructor matrix

Slice: `plib-zn0t5`
Date: 2026-06-15 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

The JSON/native constructor matrix now includes top-level tagged `Pandoc`
document packets. The case verifies that `PandocJsonReader` and `NativeReader`
record `documentConstructor` and `documentNative` provenance for the envelope
while `PandocJsonWriter` and `NativeWriter` continue to emit the canonical
`meta`/`blocks` packet shape.

No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Accounting

- `phpPass`: `3660 -> 3661`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `3695 -> 3696`
- `mappedJsonNativeConstructorMatrixCases`: `12 -> 13`
- `jsonNativeConstructorMatrixAssertions`: `168 -> 186`

## Verification

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 4404 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 86293 assertions, 0 failures
