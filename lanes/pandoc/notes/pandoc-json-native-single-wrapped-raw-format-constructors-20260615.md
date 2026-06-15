# Pandoc JSON/native single-wrapped raw format constructors

Slice: `plib-7wt1u`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for RawBlock and
RawInline `Format` helper payloads. `PandocJsonReader` and `NativeReader` now
accept single-wrapped unary `Format` content such as `c=[["html"]]`, while
`PandocJsonWriter` and `NativeWriter` preserve unchanged wrapped `Format`
sidecars and regenerate canonical scalar formats after edits.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5390 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 88436 assertions, 0 failures`
- PHP JSON validation for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- conflict-marker scan

Accounting:

- rebased current main: `e9bedf1a8b`
- `phpPass`: `3727 -> 3728`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3745 -> 3746`
- `mappedJsonNativeRawFormatHelperCases`: `1 -> 2`
- `jsonNativeRawFormatHelperAssertions`: `54 -> 82`
- `mappedJsonNativeConstructorCompletenessCases`: `55 -> 56` in lane status;
  `51 -> 52` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1408 -> 1436` in lane status;
  `1273 -> 1301` in the upstream manifest
