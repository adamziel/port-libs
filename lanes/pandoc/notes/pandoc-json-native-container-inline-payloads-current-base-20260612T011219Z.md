# Pandoc JSON/native container inline payload writer

Bead: `plib-3fu31`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness
Base: `2a569e4541`

## Behavior

`PandocJsonWriter` now reuses current-shape source `native` constructor
payloads for normalized container inline constructors when the shared AST value
still matches the source payload:

- `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, and
  `SmallCaps`;
- `Quoted`, including quote-type sidecar provenance;
- `Note`;
- `Span`, including compatible `Attr` tuple provenance.

The reuse guard still reparses the source payload and compares normalized AST
values before emission. Edited inline content therefore regenerates a fresh
constructor instead of reusing stale sidecar metadata.

No Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/browser
engines, `zip`/`unzip`, external validators, online services, live provider
tests, or live-service provider tests were invoked.

## Accounting

- `phpPass` note ledger: `3160 -> 3161`
- `phpFail`: `0`
- `mappedJsonNativeContainerInlinePayloadCases`: `+1`
- `jsonNativeContainerInlinePayloadAssertions`: `+22`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1261 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68327 assertions, 0 failures`
