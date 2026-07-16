# Pandoc JSON/native single-wrapped table section constructors

Slice: `pandoc-json-native-single-wrapped-table-section-constructors`
Base: current main `39fb84e1df`

Implemented one bounded JSON/native AST constructor-completeness case for
single-wrapped table helper constructor payloads. `PandocJsonReader` and
`NativeReader` now accept single-wrapped `TableHead`, `TableBody`,
`TableFoot`, `Row`, and `Cell` constructor `c` payloads. `PandocJsonWriter`
and `NativeWriter` preserve unchanged single-wrapped table section/body/row/cell
payloads and regenerate edited body/cell boundaries without stale wrapper
sidecars.

This is native PHP JSON/native AST work only. It does not invoke Pandoc, JSON
filters, Cabal/Haskell runners, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Result: `1 test files, 4608 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `46 test files, 86660 assertions, 0 failures`

Accounting:

- `phpPass`: `3673 -> 3674`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3707 -> 3708`
- `mappedJsonNativeConstructorCompletenessCases`: `36 -> 37`
- `jsonNativeConstructorCompletenessAssertions`: `689 -> 735`
- `mappedJsonNativeHelperConstructorVariantCases`: `10 -> 11`
- `jsonNativeHelperConstructorVariantAssertions`: `230 -> 276`
- `mappedJsonNativeSingleWrappedTableSectionConstructorCases`: `1`
- `jsonNativeSingleWrappedTableSectionConstructorAssertions`: `46`
