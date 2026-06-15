# Pandoc JSON/native Single-Wrapped Helper Payloads

Slice: `pandoc-json-native-single-wrapped-helper-payloads-20260615`

This bounded JSON/native AST constructor-completeness slice preserves
single-wrapped helper list payloads when rebuilding wrappers through both
`PandocJsonWriter` and `NativeWriter`.

Covered helper payloads:

- BulletList and OrderedList list-item block payloads.
- DefinitionList term inline payloads.
- DefinitionList definition-body block payloads.
- LineBlock line inline payloads.

The readers already tolerated these single-wrapped payload shapes. The writers
now reuse the original single-wrapped sidecar when the generated inline or block
payload still matches, while regenerated wrapper constructors still drop stale
wrapper-level sidecars.

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: `1 test files, 4277 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 85809 assertions, 0 failures`

Accounting:

- rebased current main: `3a34c69f1`
- `phpPass`: `3642 -> 3643`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3677 -> 3678`
- `mappedJsonNativeHelperConstructorVariantCases`: `8 -> 9`
- `jsonNativeHelperConstructorVariantAssertions`: `116 -> 166`

No Pandoc executable, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
