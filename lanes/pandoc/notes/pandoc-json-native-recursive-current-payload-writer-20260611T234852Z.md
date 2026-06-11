# Pandoc JSON/native recursive current payload writer

Bead: `plib-9xn7o`
Date: 2026-06-11 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now reuses unchanged current recursive native constructor
payloads after a reparse-and-compare guard verifies the shared AST is still
semantically identical. The current-shape guard now covers recursive `Header`,
`Plain`, `Para`, `BlockQuote`, `BulletList`, `OrderedList`, `DefinitionList`,
`LineBlock`, `Div`, inline formatting containers, `Span`, `Note`, `Quoted`,
`Cite`, and current three-slot `Link`/`Image` targets.

Nested shapes are checked recursively, so legacy two-slot targets and other
legacy nested payloads still fall back to the canonical JSON writer path.
Edited nodes regenerate from shared AST fields and drop stale inert native
payload data.

No Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/browser
engines, `zip`/`unzip`, external validators, online services, live provider
tests, or live-service provider tests were invoked.

## Accounting

- `phpPass` note ledger: `3145 -> 3146`
- `phpFail`: `0`
- `mappedJsonNativeRecursiveCurrentPayloadWriterCases`: `+1`
- `jsonNativeRecursiveCurrentPayloadWriterAssertions`: `7`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1166 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67374 assertions, 0 failures`
