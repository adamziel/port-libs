# Pandoc JSON/native native-writer helper payloads

Slice: `plib-ahsgz` (`20260611T174659Z`)

## Scope

This slice closes a bounded JSON/native AST constructor-completeness gap in
`NativeWriter`: edited shared AST nodes now regenerate native output while
preserving matching source-tagged helper payload shapes.

Covered helper payloads:

- ordered-list style and delimiter constructors;
- quoted inline quote-type constructors;
- math inline type constructors;
- citation mode constructors;
- table column alignment/width helpers;
- table body row-head count helpers;
- table cell alignment, row-span, and column-span helpers.

The writer still regenerates stale helper payloads when the normalized shared
AST value changed, so edited list style, table spans, and similar current AST
fields remain authoritative.

## Verification

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 1119 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 files, 66710 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `3133 -> 3134`.
- Added `mappedJsonNativeWriterHelperPayloadCases: 1`.
- Added `jsonNativeWriterHelperPayloadAssertions: 26`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
