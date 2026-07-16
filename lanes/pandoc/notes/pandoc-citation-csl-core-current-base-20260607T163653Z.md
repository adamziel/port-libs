# Pandoc Citation/CSL Original-Date Text Variable Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T163653Z`
Base accepted HEAD: `d2e7be788a40ae9de50a145789df72120ce1ffab`
Date: 2026-06-07 UTC

## Behavior

This slice maps one bounded native Citation/CSL support case: `cs:text variable="original-date"` now renders normalized original publication dates through the existing CSL date rendering path. The handoff covers both `date-parts` and literal `original-date` metadata, citation cluster output, bibliography output, and WordPress block handoff output.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before work started.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2070 assertions, 0 failures`.
- Red-first focused test: the new case failed before implementation with `1 test files, 2074 assertions, 1 failures` because `cs:text variable="original-date"` rendered empty instead of normalized `originalDate` output.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2079 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-original-date-text-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1531 -> 1532`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1950 -> 1951`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused coverage delta: `+1` PHP PASS case and `+9` focused assertions.

## Dependency Closure

No new support component is needed. The implementation reuses native `CslStyle` XML parsing, `CitationCslProcessor` date normalization/rendering, `MarkdownReader`, `WordPressBlockWriter`, the focused Citation/CSL tests, and the lane-local WordPress handoff example.

## Non-Overlap

This does not repeat prior Citation/CSL slices for date-part form precision, uncertain/circa conditionals, original publication metadata mapping, et-al behavior, subsequent-author rules, institution short-parts, locator/page-range rendering, choose matching, empty `cs:else` validation, or BibTeX/BibLaTeX handoffs. The only behavior owned here is rendering `original-date` when a CSL style references it as a text variable.
