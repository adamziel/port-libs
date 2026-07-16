# Pandoc Citation/CSL Core Current-Base Name/Year Disambiguation

Slice: `pandoc-citation-csl-core-current-base-20260608T092105Z`
Base: `a62fffb57f112b9cbf3cf9327689038b315bdd68`
Date: 2026-06-08 UTC

## Scope

Implemented one bounded Citation/CSL behavior cluster: `disambiguate-add-names`
now feeds `disambiguate-add-year-suffix` grouping before `year-suffix`
collapse output is rendered.

The covered reviewer case uses three same-year et-al citations where:

- two sources remain ambiguous after adding the second author and receive
  `2026a` / `2026b`;
- a third source becomes distinct after adding the second author and remains
  unsuffixed;
- collapsed WordPress citation output keeps the expanded author label:
  `Smith, Doe, et al. 2026a,b; Smith, Ng, et al. 2026`.

## Implementation

- `CitationCslProcessor` now carries name-disambiguation counts into
  year-suffix grouping for document and standalone citation-cluster rendering.
- Name expansion records the smallest candidate that improves an ambiguous
  label set, even when a later year suffix is still required for a remaining
  duplicate.
- The author-date collapse path renders author labels from the citation context
  so expanded names survive `collapse="year-suffix"`.
- Added a focused Citation/CSL regression test and a WordPress self-test
  example for the handoff.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2366 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2386 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-name-year-disambiguation-handoff.php --self-test`
  passed.
- PHP lint:
  `php -l lanes/pandoc/src/CitationCslProcessor.php` passed.
- PHP lint:
  `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` passed.
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-citation-csl-name-year-disambiguation-handoff.php` passed.
- JSON validation:
  `php -r 'json_decode(... lane-status.json ...); json_decode(... UPSTREAM_TEST_MANIFEST.json ...);'`
  passed.
- Whitespace:
  `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1591 -> 1592`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2011 -> 2012`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused assertion delta: `+20` in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native CSL
style parser, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap / Follow-Up

This slice does not overlap the accepted empty `cs:else` validation,
institution short-parts, et-al-use-last, subsequent et-al, subsequent-author
substitution, given-name disambiguation, or standalone year-suffix
disambiguation slices. It covers only the ordering interaction between
add-names, add-year-suffix, and citation collapse.

Useful follow-up: exercise `disambiguate-add-givenname` ordering when
add-names and add-year-suffix are also enabled, without invoking external
citeproc or Pandoc.
