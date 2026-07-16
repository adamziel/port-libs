# Pandoc Citation/CSL Date-Parts Precision Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T123939Z`
Base accepted HEAD: `29d163feb6e58f391e305e1c254ebf90840b6728`
Date: 2026-06-07 UTC

## Behavior

- Added bounded native support for CSL `cs:date` element-level
  `date-parts="year"`, `year-month`, and `year-month-day`.
- `CslStyle` now validates and preserves the date precision selector in style
  summaries as `datePartsSelection`.
- `CitationCslProcessor` applies the selector before rendering localized
  `form="text"` and `form="numeric"` dates, including date ranges and
  same-month range de-duplication.
- Added a WordPress handoff example that keeps month/year citation dates and
  year/month bibliography review dates visible without invoking citeproc.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this
  lane before editing.
- Red-first focused run after adding the test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 1946 assertions, 1 failures` because
  `datePartsSelection` was absent.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1958 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-date-parts-handoff.php --self-test`
  passed with `wordpress-citation-csl-date-parts-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1499 -> 1500`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1919 -> 1920`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `11 -> 12`.
- Focused citation coverage: one new PASS case and `+14` focused assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `CslStyle` XML
parsing, `CitationCslProcessor` date rendering, `MarkdownReader`,
`WordPressBlockWriter`, and the focused lane PHP harness.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external
bibliography manager, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not repeat accepted explicit `cs:date-part` rendering, CSL
`form="text|numeric"` date-form rendering, day-ordinal locale limits, uncertain
or circa date predicates, page-range formatting, locator range punctuation,
name rendering, et-al behavior, subsequent-author substitution, or BibTeX/
BibLaTeX metadata handoffs. This patch owns only the element-level CSL
`date-parts` precision selector for localized date rendering.
