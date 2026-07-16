# Pandoc JSON/Native Target Tuple Provenance

Slice: `plib-4g83e`, Pandoc JSON/native AST constructor completeness.
Base: current main `d84ad700e`.

## Change

`PandocJsonReader` and `NativeReader` now preserve the two-string Pandoc
`Link`/`Image` target tuple as `targetNative` while still normalizing `url`,
`title`, and image `alt` attrs. The coverage includes modern three-entry
target inline constructors and legacy two-entry target inline shapes.

Writers continue to emit canonical Pandoc JSON/native target tuples from
normalized URL/title attrs, so existing output shape stays stable.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 test file, 938 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64974 assertions, 0 failures.

## Accounting

- Added one focused `PandocJsonNativeAstTest` PASS case and strengthened the
  existing legacy target-shape case.
- Focused assertion delta: +34.
- `lane-status.json` `phpPass`: 3090 -> 3091.
- No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external
  validators, online services, live provider tests, or live-service provider
  tests were invoked.
