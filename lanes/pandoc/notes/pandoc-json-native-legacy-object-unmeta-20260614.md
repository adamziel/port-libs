# Pandoc JSON/native legacy object unMeta metadata

Bead: `plib-ek0iz`
Date: 2026-06-14 UTC
Area: Pandoc JSON/native AST constructor completeness

## Scope

`NativeReader` now accepts legacy object-form native JSON metadata envelopes
where `meta` is `{ "unMeta": ... }`, matching the JSON reader's bounded legacy
unwrapping behavior. The reader unwraps plain legacy envelopes when the API
version is absent or `1.x <= 1.17`, unwraps tagged `MetaMap` envelopes,
preserves canonical metadata constructor provenance paths, and keeps modern
tagged literal `unMeta` metadata values intact.

Writers continue to emit canonical JSON/native metadata maps. No Pandoc binary,
JSON filter, Cabal/Haskell runner, browser renderer, external validator, online
service, live provider test, or live-service provider test was invoked.

## Accounting

- `phpPass`: `3585 -> 3586`
- `phpFail`: `0`
- `mappedJsonNativeLegacyObjectUnMetaCases`: `1`
- `jsonNativeLegacyObjectUnMetaAssertions`: `18`

## Verification

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3549 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 84154 assertions, 0 failures`
