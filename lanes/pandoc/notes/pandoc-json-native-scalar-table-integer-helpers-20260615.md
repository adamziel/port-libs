# Pandoc JSON/native scalar table integer helpers

Date: 2026-06-15

Slice: `pandoc-json-native-scalar-table-integer-helpers`

## Scope

This slice covers a bounded JSON/native AST constructor-completeness gap in table integer helper payloads. `PandocJsonReader` and `NativeReader` already accepted scalar and single-wrapped scalar table helper payloads for `RowHeadColumns`, `RowSpan`, and `ColSpan`, but `PandocJsonWriter` and `NativeWriter` regenerated those helpers as tagged constructors when rebuilding table wrappers.

The writers now reuse matching source-native scalar integer payloads (`1`, `2`) and single-wrapped scalar payloads (`[3]`) when the shared AST value still matches. Tagged helper payload behavior is unchanged.

## Accounting

- rebased current main: `a6ca32bb27`
- `phpPass`: 3720 -> 3721
- `phpFail`: remains 0
- `mappedJsonNativeConstructorCompletenessCases`: 54 -> 55
- `jsonNativeConstructorCompletenessAssertions`: 1380 -> 1408
- `mappedJsonNativeScalarIntegerHelperCases`: 0 -> 1
- `jsonNativeScalarIntegerHelperAssertions`: 0 -> 28

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 file, 5362 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 88230 assertions, 0 failures

No Pandoc executable, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
