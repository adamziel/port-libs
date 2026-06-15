# Pandoc JSON/native single-wrapped definition item helpers

Slice: `plib-ns6cl`
Area: Pandoc JSON/native AST constructor completeness.

`PandocJsonReader` and `NativeReader` now accept single-wrapped
`DefinitionList` item tuple payloads. `PandocJsonWriter` and `NativeWriter`
reuse unchanged single-wrapped definition item payloads when rebuilding
definition-list wrappers, and regenerate a direct tuple when the term or
definition body is edited so stale outer wrappers do not survive.

The focused test also verifies neighboring single-wrapped blockquote,
bullet-list item, ordered-list item, definition body, and line helper payloads
through both JSON/native readers and writers. No Pandoc binary, JSON filters,
Cabal/Haskell runners, browser renderers, external validators, online services,
live provider tests, or live-service provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5652 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 88911 assertions, 0 failures`

Accounting:

- `phpPass`: `3739 -> 3740`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3755 -> 3756`
- `mappedJsonNativeConstructorCompletenessCases`: `60 -> 61` in lane status
  and `55 -> 56` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1640 -> 1698` in lane status
  and `1475 -> 1533` in the upstream manifest
- `mappedJsonNativeHelperConstructorVariantCases`: `13 -> 14` in lane status
  and `11 -> 12` in the upstream manifest
- `jsonNativeHelperConstructorVariantAssertions`: `330 -> 388` in lane status
  and `254 -> 312` in the upstream manifest
- `mappedJsonNativeSingleWrappedDefinitionItemHelperCases`: `1`
- `jsonNativeSingleWrappedDefinitionItemHelperAssertions`: `58`
