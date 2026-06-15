# Pandoc JSON/native single-wrapped table column specs

Date: 2026-06-15
Bead: plib-0zjzb

## Scope

JSON and native readers now accept single-wrapped table column spec tuples:

- `[Alignment, ColWidth]`
- `[[Alignment, ColWidth]]`

The readers keep `columnSpecNatives` sidecars while still exposing normalized
alignment and width helpers. JSON and native writers reuse unchanged wrapped
column spec sidecars, including through rebuilt table wrappers, and regenerate
edited widths as current direct column spec tuples.

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 5594 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88765 assertions, 0 failures

## Accounting

- `phpPass`: `3735 -> 3736`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3752 -> 3753`
- `mappedJsonNativeConstructorCompletenessCases`: `59 -> 60` in lane status,
  `55 -> 56` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1610 -> 1640` in lane status,
  `1475 -> 1505` in the upstream manifest
- `mappedJsonNativeHelperConstructorVariantCases`: `12 -> 13`
- `jsonNativeHelperConstructorVariantAssertions`: `300 -> 330`
- `mappedJsonNativeSingleWrappedTableColumnSpecCases`: `1`
- `jsonNativeSingleWrappedTableColumnSpecAssertions`: `30`
