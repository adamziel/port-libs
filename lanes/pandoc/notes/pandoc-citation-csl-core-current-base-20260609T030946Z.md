# Pandoc Citation/CSL Count Label Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T030946Z`
Base accepted HEAD: `6ab30597dbaeef18dd989f9dad5bd875e13a7661`

## Scope

- Implemented bounded CSL contextual label handling for count-like number variables.
- `number-of-pages` and `number-of-volumes` now render singular labels only for one-count values (`1`, `one`, or `i`) and plural labels for non-one counts or ranges.
- Preserved the existing range/list fallback for ordinary label variables such as `locator`, `section`, `volume`, and `chapter-number`.

## Evidence

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3472 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3485 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-count-label-handoff.php --self-test` passed with `wordpress-citation-csl-count-label-handoff self-test passed`.
- PHP lint passed for:
  - `lanes/pandoc/src/CitationCslProcessor.php`
  - `lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `lanes/pandoc/examples/wordpress-citation-csl-count-label-handoff.php`
- Final hygiene: `git diff --check -- lanes/pandoc` passed.

## Status Delta

- Added one focused Citation/CSL PASS case.
- Focused Citation/CSL assertions increased by 13.
- `lane-status.json` `phpPass` moves from `2208` to `2209`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2618` to `2619`; `mappedCitationCslCoreCases` moves from `12` to `13`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP CSL style parser, term lookup, citation rendering, Markdown reader, and WordPress block writer. No Pandoc, Cabal/Haskell runner, citeproc, bibliography manager, Word, LibreOffice, zip/unzip, renderer, online service, live provider test, or live-service provider test was invoked.

## Non-Overlap

This slice avoids the already accepted CSL label pluralization work for semicolon locator lists and the existing supplement, part-number, section-number, default name-variable terms, bibliography options, citation-position, and locale term batches. The new behavior is specifically count-variable contextual labels for `number-of-pages` and `number-of-volumes`.

## Follow-Up

Keep broader CSL locale term fallback behavior, citation-position disambiguation, and style XML coverage as separate non-overlapping slices.
