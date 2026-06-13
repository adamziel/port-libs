# Pandoc DocBook structural review diagnostics

Date: 2026-06-13
Base: `faf6223e82`
Bead: `plib-2y8jn`

## Verdict

DocBook remains partial, not ship-ready. This slice closes one bounded structural diagnostic gap without claiming direct reader parity.

## Implemented slice

`XmlHtmlDom::summarizeDocBookStructure()` now emits review-only packets for DocBook 5 namespaced roots and DocBook 4 style legacy roots. The packet records:

- root/version/language/metadata attributes
- title, subtitle, abstract, identifiers, contributors, and contributor roles
- section/chapter structure, roles, IDs, direct and descendant paragraph counts
- figures, tables, admonitions, bibliography entries, cross-reference targets, external targets, media objects, image objects, and image references
- explicit `directReaderParity=false` plus unsupported diagnostics for incomplete direct-reader/body conversion parity

This deliberately does not duplicate existing DocBook table geometry parsing. It adds a DocBook-specific review packet for non-table structure.

## Counters

- PHP pass numerator: 3,348 -> 3,349
- PHP fail: 0
- Mapped upstream cases: 3,307 -> 3,308
- New mapped counter: `mappedXmlHtmlDomDocBookStructureReviewCases = 1`
- New assertion counter: `xmlHtmlDomDocBookStructureReviewAssertions = 56`
- DocBook local evidence: 16 -> 17 against the existing 16-row DocBook/table geometry denominator

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 file, 1,930 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 45 files, 75,426 assertions, 0 failures

No Pandoc binary, Cabal/Haskell runner, XML validator, browser renderer, Node tooling, online service, live provider test, or external validator was used.

## Remaining DocBook gaps

Full DocBook reader parity still needs body conversion into shared AST blocks/inlines, broader section nesting semantics, references and bibliography mapping, generated AST round trips, non-table figure/media handling, admonition block conversion, and broader upstream fixture hydration.
