# Citation/CSL Names Substitute Display Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260608T075700Z`
Base accepted HEAD: `f7ac0a85e1fac9551aa46d1e1dabc4c1e6766a6c`
Date: 2026-06-08 UTC

## Behavior

Implemented bounded native CSL bibliography display-part collection for
display-bearing `cs:names` substitute branches. When a bibliography `names`
element has no matching primary name variable, `CitationCslProcessor` now
follows the first non-empty substitute branch for `cslDisplayParts` metadata,
matching the existing text rendering path.

This preserves substitute `display="left-margin|right-inline|block|indent"`
and formatting metadata such as `font-weight="bold"` for WordPress CSL review
entries, while leaving entries with real primary names on their existing display
path.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2327 assertions, 0 failures`.
- Red-first: after adding the focused test, the same command failed as expected
  with `1 test files, 2337 assertions, 1 failures` because the display-bearing
  `cs:names` substitute was absent from `cslDisplayParts`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2340 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-substitute-display-handoff.php --self-test`
  passed with `wordpress-citation-csl-substitute-display-handoff self-test passed`.

## Status Delta

- Added one focused PHP PASS case and 13 focused assertions.
- Updated `lane-status.json` `phpPass` from `1569` to `1570`.
- Updated `UPSTREAM_TEST_MANIFEST.json` mapped denominator from `1990` to `1991`.
- Updated `mappedCitationCslCoreCases` from `12` to `13`.

## Dependency Closure

No new support component is needed. The slice reuses native `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the
focused Citation/CSL test harness.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Citation/CSL date-part precision, participant
names, institution short-parts, et-al behavior, subsequent-author substitution,
choose conditionals, locator/page labels, number rendering, nested display
parts from macros/choose branches, or generic bibliography display formatting.
It only owns display metadata reached through `cs:names` substitute branches.

Root harness: not run - isolated micro-slice.
