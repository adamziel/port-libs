# Pandoc JSON/native Note constructor matrix

Slice: `plib-ikjkn`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-matrix coverage case for the `Note` inline
constructor. The JSON/native matrix now verifies that a labeled note containing
`Plain` and `HorizontalRule` block payloads exact-round-trips through
`PandocJsonReader`, `NativeReader`, `PandocJsonWriter`, and `NativeWriter`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5180 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87913 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- conflict-marker scan

Accounting:

- rebased current main: `47b8d05417`
- `phpPass`: `3711 -> 3712`
- `phpFail`: `0`
- `upstream.mapped`: `3734 -> 3735`
- `mappedJsonNativeConstructorMatrixCases`: `18 -> 19` in lane status and manifest top level; `17 -> 18` in the upstream manifest
- `jsonNativeConstructorMatrixAssertions`: `317 -> 331` in lane status and manifest top level; `292 -> 306` in the upstream manifest
