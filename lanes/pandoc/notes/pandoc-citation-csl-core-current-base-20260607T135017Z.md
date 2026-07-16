# Pandoc Citation/CSL Extended Locator Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T135017Z`
Base accepted HEAD: `0f6a827583ed4cd322d9cb5476a5c5b23c62d765`
Date: 2026-06-07 UTC

## Behavior

- Added bounded native inference for CSL locator labels in Markdown citation
  tails and direct citation metadata: appendix, column, equation, figure,
  issue, line, note, table, and verse.
- Added default English CSL terms for appendix, equation, figure, table, and
  note so `cs:label variable="locator"` can render compact review output such
  as `fig.`, `tbls.`, `app.`, `n.`, and `eqs.`.
- Kept the implementation in the existing `MarkdownReader`, `CslStyle`, and
  `CitationCslProcessor` paths. No external citeproc or bibliography manager
  was introduced.
- Added a WordPress handoff example for extended locator review output.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this
  lane before editing.
- Initial focused run after implementation and test addition:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 1981 assertions, 2 failures` because
  `MarkdownReader` still classified `fig. 2` as a page locator and the older
  numeric-conditional fixture still expected `appendix A` to remain an
  unclassified locator string.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2000 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-extended-locator-handoff.php --self-test`
  passed with `wordpress-citation-csl-extended-locator-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1507 -> 1508`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1927 -> 1928`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `11 -> 12`.
- Focused citation coverage: one new PASS case and `+16` focused assertions in
  `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`MarkdownReader` citation-tail parsing, `CslStyle` locale terms,
`CitationCslProcessor` locator rendering, `MarkdownWriter`, and
`WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external
bibliography manager, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not repeat accepted page/chapter/section/paragraph locator rendering,
locator range punctuation, page-range formatting, `is-numeric` conditionals,
`is-uncertain-date` predicates, date-parts precision, choose match semantics,
name rendering, et-al behavior, subsequent-author substitution, or BibTeX/
BibLaTeX metadata handoffs. This patch owns only the bounded extended CSL
locator labels needed for figure/table/appendix/note/line/equation-style
citation review.
