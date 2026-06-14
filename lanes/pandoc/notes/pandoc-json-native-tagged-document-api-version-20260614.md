# Pandoc JSON/native tagged document API version

Bead: `plib-q1k6y`
Date: 2026-06-14 UTC
Area: Pandoc JSON/native AST constructor completeness

## Scope

Tagged top-level `Pandoc` constructor packets can now carry a sibling
`pandoc-api-version` field through both native PHP readers. The readers still
normalize `{"t":"Pandoc","c":[meta,blocks]}` into the shared document AST, but
they preserve the API version as `pandocApiVersion` before the JSON and native
writers re-emit the canonical packet object.

This keeps constructor provenance and API-version provenance together for
filter-style packets or newer producer packets without shelling out to Pandoc,
JSON filters, Cabal/Haskell runners, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

## Accounting

- `phpPass`: `3459 -> 3460`
- `phpFail`: `0`
- `mappedJsonNativeTaggedDocumentApiVersionCases`: `1`
- `jsonNativeTaggedDocumentApiVersionAssertions`: `6`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3106 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 80923 assertions, 0 failures`
