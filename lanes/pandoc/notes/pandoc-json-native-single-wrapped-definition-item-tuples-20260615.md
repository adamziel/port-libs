# Pandoc JSON/native single-wrapped definition item tuples

Slice: `pandoc-json-native-single-wrapped-definition-item-tuples`
Date: 2026-06-15
Bead: `plib-tzfkj`

## Scope

`PandocJsonNativeAstTest` now covers single-wrapped `DefinitionList` item tuple payloads such as `[[term, definitions]]`.

The regression verifies that the current JSON and native readers accept the wrapped item tuple shape, and that the JSON and native writers preserve unchanged wrapped definition item tuples when rebuilding definition list wrappers while regenerating edited items as canonical direct tuples.

Rebased onto current main `3dda0837c3`.

No Pandoc executable, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5784 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `181 test files, 165421 assertions, 0 failures`

## Accounting

- `phpPass`: `15328 -> 15329`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `mapped`: `14999 -> 15000`
- `mappedJsonNativeConstructorCompletenessCases`: `63 -> 64` in lane status; `59 -> 60` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1778 -> 1808` in lane status; `1643 -> 1673` in the upstream manifest
- `mappedJsonNativeSingleWrappedDefinitionItemTupleCases`: `1`
- `jsonNativeSingleWrappedDefinitionItemTupleAssertions`: `30`
