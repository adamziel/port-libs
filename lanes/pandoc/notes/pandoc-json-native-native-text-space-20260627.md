# Pandoc JSON/native native text Space constructor

Slice: `plib-6805u`
Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for explicit shared AST
`space` inline nodes in native text output. `NativeWriter` now emits first-class
`Space` constructors for `space` nodes instead of rejecting them, and
`NativeReader` parses native text `Space` constructors back into shared `space`
nodes so downstream Pandoc JSON emission keeps `Space` rather than degrading to
`Str " "`.

No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, office
suite, external validator, online service, live provider test, or live-service
provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- selected regression `serializes shared space inline nodes through native text constructors`
  passed with 7 assertions and 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  remains baseline-red with 1 file, 5896 assertions, and 12 unrelated failures;
  the new regression passes.

Accounting:

- `lane-status.json` `phpPass`: `457 -> 458`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedJsonNativeNativeTextSpaceInlineCases`: `1`
