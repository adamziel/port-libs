# Pandoc JSON/native tagged target constructors

Slice: `pandoc-json-native-tagged-target-constructors`
Base: current main `881d83c952`.

Implemented one bounded JSON/native AST constructor-completeness case:
`PandocJsonReader` and `NativeReader` now accept tagged `Target` helper
payloads for `Link` and `Image` target tuples, including single-wrapped
`Target.c` tuple content. `PandocJsonWriter` and `NativeWriter` reuse tagged
target payloads when the normalized URL/title still match, and regenerate a
canonical target tuple when an edited URL or title invalidates the sidecar.

This slice does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: `1 test files, 5933 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `195 test files, 169950 assertions, 0 failures`

Accounting:

- `phpPass`: `16348 -> 16349`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `15957 -> 15958`
- `mappedJsonNativeConstructorCompletenessCases`: `65 -> 66` in lane status
- `jsonNativeConstructorCompletenessAssertions`: `1856 -> 1900` in lane status
- `mappedJsonNativeTaggedTargetConstructorCases`: `1`
- `jsonNativeTaggedTargetConstructorAssertions`: `44`
