# Pandoc JSON/native structural block payloads

Bead: `plib-0soxx`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now reuses guarded current-shape source `native` block
constructor payloads for structural blocks whose shared AST value is unchanged:

- `BlockQuote`
- `BulletList`
- `OrderedList`
- `DefinitionList`
- `LineBlock`
- `Div`

The existing reader-based reuse guard still reparses the source constructor
payload and compares normalized shared AST values before emitting it. As a
result, compatible inert envelope fields such as reviewer queue or source
ordinal metadata survive unchanged JSON/native handoff, while semantic child
edits regenerate the constructor and drop stale envelope provenance.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online services,
live provider tests, or live-service provider tests were invoked.

## Accounting

- `phpPass`: `3153 -> 3154`
- `phpFail`: `0`
- `mappedJsonNativeStructuralBlockPayloadCases`: `+1`
- `jsonNativeStructuralBlockPayloadAssertions`: `+6`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1221 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67867 assertions, 0 failures`
