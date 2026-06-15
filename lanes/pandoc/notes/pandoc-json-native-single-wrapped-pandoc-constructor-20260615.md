# Pandoc JSON/native single-wrapped Pandoc document constructor

Bead: `plib-cbmhu`
Date: 2026-06-15 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` and `NativeReader` now accept tagged top-level `Pandoc`
document constructors whose `c` payload is single-wrapped as
`[[meta, blocks]]`. Both readers preserve the original `documentNative`
payload and normalize the shared AST so `PandocJsonWriter` and `NativeWriter`
emit canonical packet objects with the same metadata and block constructors.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- rebased current main: `711f1e84ba`
- `phpPass`: `3686 -> 3687`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3715 -> 3716`
- `mappedJsonNativeConstructorCompletenessCases`: `39 -> 40` in lane status; `38 -> 39` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `806 -> 830` in lane status; `799 -> 823` in the upstream manifest
- `mappedJsonNativeSingleWrappedDocumentConstructorCases`: `1`
- `jsonNativeSingleWrappedDocumentConstructorAssertions`: `24`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4759 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87077 assertions, 0 failures`
