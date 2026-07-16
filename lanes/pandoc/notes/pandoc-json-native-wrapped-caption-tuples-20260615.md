# Pandoc JSON/native wrapped caption tuples

Slice: `pandoc-json-native-wrapped-caption-tuples-20260615`
Base: `87ff0c40cd`

## Scope

This bounded JSON/native AST constructor-completeness slice accepts `Caption`
constructors whose `c` payload is a single-wrapped two-slot caption tuple. Both
`PandocJsonReader` and `NativeReader` now decode wrapped table and figure caption
tuples while preserving the original `captionNative` sidecar. `PandocJsonWriter`
and `NativeWriter` reuse unchanged wrapped captions, and regenerate edited
captions inside the same outer wrapper without retaining stale caption sidecars.

The slice does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Accounting

- `phpPass`: `3650 -> 3651`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `3687 -> 3688`
- `mappedJsonNativeConstructorCompletenessCases`: `31 -> 32`
- `jsonNativeConstructorCompletenessAssertions`: `480 -> 544`
- `mappedJsonNativeSingleWrappedCaptionTupleCases`: `1`
- `jsonNativeSingleWrappedCaptionTupleAssertions`: `64`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 4355 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 86066 assertions, 0 failures
