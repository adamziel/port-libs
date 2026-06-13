# Pandoc XML/HTML5 Raw Text Boundary Diagnostics

Bead: `plib-01k0x`
Date: 2026-06-13 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `3c006d786a`

## Behavior

`XmlHtmlDom` now exposes bounded source diagnostics for HTML raw-text boundary
repairs:

- `plaintext-boundary` records that `<plaintext>` consumes the rest of the
  fragment and flags ignored `</plaintext>` source text;
- `raw-text-boundary` records missing raw/RCDATA/inert raw end tags that are
  synthesized at EOF for deterministic parser handoff;
- diagnostics include tag, kind, reason, closure mode, synthetic end tag,
  content byte count, and source line/column;
- `Html5DomFragment` carries these diagnostics into `raw_html` AST attrs while
  preserving existing escaped reviewer text and blocked active wrappers.

This does not implement full HTML5 tree construction, browser parsing parity, or
full XML/JATS/BITS direct readers.

## Accounting

- `phpPass`: `3378 -> 3379`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3338 -> 3339`
- `mappedXmlHtmlDomRawTextBoundaryCases`: `1`
- `xmlHtmlDomRawTextBoundaryAssertions`: `33`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- Focused `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `2 test files, 4987 assertions, 0 failures`.
- Full `php tools/run-tests.php lanes/pandoc/tests` passed with `46 test files,
  76484 assertions, 0 failures`.

No Pandoc, Cabal/Haskell runner, browser renderer, Node tooling, TeX/PDF engine,
Typst engine, office suite, online service, live provider test, or external
validator was invoked.
