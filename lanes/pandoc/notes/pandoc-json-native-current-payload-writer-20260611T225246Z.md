# Pandoc JSON/native current payload writer

Bead: `plib-ql2fq`
Date: 2026-06-11 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now reuses current-shape source `native` constructor payloads
for leaf block and inline constructors when the normalized shared AST value
still matches the source payload:

- leaf block payloads: `CodeBlock`, `RawBlock`, `HorizontalRule`, and `Null`;
- leaf inline payloads: `Str`, break/space constructors, `Code`, `Math`,
  `RawInline`, plus current three-slot `Link` and `Image` targets.

The reuse guard reparses the source payload through the JSON/native readers and
compares normalized AST values before emitting the source payload. Edited node
content therefore regenerates instead of reusing stale native payloads, while
compatible helper payloads such as `Attr` tuples can still be preserved by their
own writer guards. Container blocks, figures, tables, and legacy two-slot
targets remain on the existing canonical writer paths.

No Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/browser
engines, `zip`/`unzip`, external validators, online services, live provider
tests, or live-service provider tests were invoked.

## Accounting

- `phpPass` note ledger: `3135 -> 3136`
- `phpFail`: `0`
- `mappedJsonNativeCurrentPayloadWriterCases`: `+1`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1142 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66852 assertions, 0 failures`
