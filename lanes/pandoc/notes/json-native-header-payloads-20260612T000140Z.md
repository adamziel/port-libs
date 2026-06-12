# JSON/native Header payloads

Slice: `plib-eueny` on current main `6d8eba6ee7`.

This slice covers one bounded Pandoc JSON/native AST constructor completeness gap: source-tagged current `Header` block constructor payloads now survive JSON and native writer output while the heading is unchanged, but regenerate once semantic heading fields are edited. The preserved payload can carry inert reviewer provenance such as source ordinals and review queues while duplicate attribute tuples remain compatible with normalized shared AST attrs.

Mapped accounting:

- `mappedJsonNativeHeaderPayloadCases`: 1
- `jsonNativeHeaderPayloadAssertions`: 24
- `phpPass`: 3149 -> 3150
- Upstream mapped denominator: 3223 -> 3224

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1183 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67604 assertions, 0 failures

No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
