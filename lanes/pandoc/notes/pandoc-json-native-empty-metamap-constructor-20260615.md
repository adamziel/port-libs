# Pandoc JSON/native empty MetaMap constructor provenance

Bead: `plib-rlufm`
Date: 2026-06-15 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` now records document-level `MetaMap` constructor and native
payload provenance even when an explicit Pandoc metadata envelope is empty.

The normalized shared AST still omits an empty `meta` helper, so
`PandocJsonWriter` and `NativeWriter` continue to emit canonical empty metadata
maps. `NativeReader` already preserved the empty top-level constructor envelope;
the regression keeps the JSON and native reader paths aligned.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `mappedJsonNativeMetaConstructorCases`: `+1`
- `mappedPandocJsonNativeMetaConstructorProvenanceCases`: `+1`
- Focused assertions after rebase: `4143`
- Full lane assertions after rebase: `85429`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4143 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 85429 assertions, 0 failures`
