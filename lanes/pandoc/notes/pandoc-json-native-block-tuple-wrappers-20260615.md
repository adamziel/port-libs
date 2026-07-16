# Pandoc JSON/native block tuple wrappers

Date: 2026-06-15

Slice: `pandoc-json-native-block-tuple-wrappers`

## Scope

- Accept single-wrapped block list payloads for `Plain` and `Para` through the JSON reader path.
- Accept single-wrapped tuple payloads for `Header`, `CodeBlock`, `RawBlock`, `Div`, `Figure`, and `Table` through JSON and native readers.
- Preserve unchanged wrapped block constructor payloads through JSON and native writers.
- Regenerate edited `Header` and `Table` block shells canonically, dropping stale wrapper sidecars.

## Accounting

- rebased current main: `12a77eed95`
- `phpPass`: 3713 -> 3714
- `phpFail`: 0
- `mappedJsonNativeConstructorCompletenessCases`: 50 -> 51
- `jsonNativeConstructorCompletenessAssertions`: 1219 -> 1283
- `mappedJsonNativeSingleWrappedBlockTupleCases`: 2 -> 3
- `jsonNativeSingleWrappedBlockTupleAssertions`: 144 -> 208

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5244 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87987 assertions, 0 failures`

No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked for this slice.
