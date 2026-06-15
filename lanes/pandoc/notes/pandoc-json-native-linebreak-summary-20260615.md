# Pandoc JSON/native LineBreak summary constructors

Bead: `plib-c3z9r`
Date: 2026-06-15 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` now treats `LineBreak` as a newline when deriving shared
text summaries, matching `NativeReader` behavior while keeping `SoftBreak` as a
space. The focused constructor-completeness case verifies that JSON and native
readers retain `SoftBreak` and `LineBreak` constructor/native payloads on the
shared AST, and that both `PandocJsonWriter` and `NativeWriter` preserve the
original constructor packet.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- rebased current main: `265dbf8f37`
- `phpPass`: `3622 -> 3623`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3634 -> 3635`
- `mappedJsonNativeConstructorCompletenessCases`: `10 -> 11`
- `jsonNativeConstructorCompletenessAssertions`: `160 -> 182`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3847 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 85012 assertions, 0 failures`
