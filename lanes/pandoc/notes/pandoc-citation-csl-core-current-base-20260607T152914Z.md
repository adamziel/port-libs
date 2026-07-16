# Citation/CSL Empty Else Validation Slice

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T152914Z`  
Base accepted HEAD: `6a3ea0f4861660790e73a0b7403add52995f31fa`  
Date: 2026-06-07 UTC

## Behavior

This slice tightens native CSL style parsing for `cs:choose`: `cs:else` branches must contain at least one rendering element. Empty `<else/>` branches now fail closed during `CslStyle` parsing, while valid non-empty fallback branches still render through `CitationCslProcessor`.

The implementation stays inside the existing native PHP CSL support library. It does not shell out to Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2067 assertions, 0 failures`.
- Red-first: after adding the focused test, the same command failed as expected with `1 test files, 2070 assertions, 1 failures` because empty `cs:else` accepted without throwing `InvalidArgumentException`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2070 assertions, 0 failures`.

## Status Delta

- Added one focused PHP PASS case and three focused assertions.
- Updated `lane-status.json` `phpPass` from `1523` to `1524`.
- Updated `UPSTREAM_TEST_MANIFEST.json` mapped denominator from `1943` to `1944`.
- Updated `mappedCitationCslCoreCases` from `11` to `12`.

## Dependency Closure

No new support component is needed. The slice reuses native `CslStyle` XML parsing, `CitationCslProcessor` rendering, `AstNode` citation clusters, and the focused Citation/CSL test harness.

## Non-Overlap

This does not repeat the current accepted CSL slices for participant names, `is-creator`, `is-uncertain-date`, `is-circa-date`, near-note citation positions, subsequent-author substitution, et-al rendering, locator labels, page ranges, or BibTeX/BibLaTeX handoff metadata.

Root harness: not run - isolated micro-slice.
