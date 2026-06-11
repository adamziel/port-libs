# Pandoc JSON/native JSON-writer helper payloads

Slice: `plib-hjijq` (`20260611T224512Z`)

## Scope

This slice closes a bounded JSON/native AST constructor-completeness gap in
`PandocJsonWriter` coverage: edited shared AST nodes now have explicit tests for
regenerated JSON output while preserving matching source-tagged helper payload
shapes.

Covered helper payloads:

- ordered-list style and delimiter constructors;
- quoted inline quote-type constructors;
- math inline type constructors;
- citation mode constructors;
- table column alignment/width helpers;
- table body row-head count helpers;
- table cell alignment, row-span, and column-span helpers.

The test edits list start, paragraph content, and table attributes, then checks
that regenerated JSON reflects the edited shared AST fields while preserving the
matching helper payload shapes from both JSON-reader and native-reader sources.

## Verification

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 1159 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 files, 66833 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `3135 -> 3136`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3218 -> 3219`.
- `mappedJsonNativeWriterHelperPayloadCases`: `1 -> 2`.
- `jsonNativeWriterHelperPayloadAssertions`: `26 -> 52`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
