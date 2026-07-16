# Pandoc JSON/native fallback constructor matrix

Slice: `pandoc-json-native-fallback-constructor-matrix`
Rebased over current main `ebb4d63bbd`.

Implemented one bounded JSON/native AST constructor matrix coverage case:
the constructor matrix now exercises opaque `VendorBlock` and `VendorInline`
fallback constructors through `PandocJsonReader`, `NativeReader`,
`PandocJsonWriter`, and `NativeWriter`.

This is a constructor-completeness accounting slice. It uses the existing
native PHP reader/writer paths and does not invoke Pandoc, JSON filters,
Cabal/Haskell runners, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Result: `1 test files, 4704 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `46 test files, 86887 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

Accounting:

- `phpPass`: `3679 -> 3680`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3711 -> 3712`
- `mappedJsonNativeConstructorMatrixCases`: `15 -> 16`
- `jsonNativeConstructorMatrixAssertions`: `264 -> 278`
