# Pandoc JSON/native Div Plain Boundary

Bead: `plib-k5th2`
Date: 2026-06-13 UTC

## Scope

`WordPressBlockWriter` now preserves adjacent native `Plain` block boundaries
inside HTML block collections such as `Div` and nested `BlockQuote` bodies.
When a collection has multiple blocks, unadorned `Plain` children render as
explicit paragraphs before following `Plain`, `RawBlock`, or `Para` content, so
reviewer-visible WordPress handoff does not collapse `First plainSecond plain`
or merge plain text into raw HTML containers.

The focused fixture starts from a Pandoc native `Div` containing two `Plain`
blocks, an HTML `RawBlock`, and a `Para`, then verifies native writer
round-trip stability and WordPress boundary output. Single-block plain
collections remain compact, preserving existing tight list-item behavior.

## Verification

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 1987 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 45 files, 74214 assertions, 0 failures

No Pandoc binary, Cabal/Haskell runner, TeX/PDF engine, browser renderer, Node
tooling, office suite, external validator, online service, live provider test,
or live-service provider test was invoked.

## Accounting

- `phpPass` moves from 3309 to 3310 after final rebase.
- `phpFail` remains 0.
- `mappedJsonNativeDivPlainBoundaryCases`: 1.
- `jsonNativeDivPlainBoundaryAssertions`: 9.

## Remaining Block-Structure Gaps

Broader JSON/native AST parity remains partial. Remaining non-list structure
work includes wider upstream native/json fixture coverage, unsupported
constructor surfaces, and table/citation/metadata round-trip edges beyond this
bounded Div/Plain/RawBlock WordPress boundary slice.
