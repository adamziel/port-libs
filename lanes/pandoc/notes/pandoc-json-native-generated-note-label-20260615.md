# Pandoc JSON/native generated Note labels

Bead: `plib-j5fkz`
Date: 2026-06-15 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

Shared AST `note` nodes now have focused JSON/native writer coverage for
generated Pandoc `Note` constructors with valid `noteLabel` sidecars. Invalid
generated note labels are omitted so stale or unsafe sidecars are not emitted,
and both Pandoc JSON and native readers round-trip the valid label back onto
the shared AST node.

No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Accounting

- `phpPass`: `3651 -> 3652`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `3688 -> 3689`
- `mappedJsonNativeConstructorCompletenessCases`: `32 -> 33`
- `jsonNativeConstructorCompletenessAssertions`: `544 -> 572`
- `mappedJsonNativeGeneratedNoteLabelCases`: `1`
- `jsonNativeGeneratedNoteLabelAssertions`: `28`

## Verification

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 4383 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 86094 assertions, 0 failures
