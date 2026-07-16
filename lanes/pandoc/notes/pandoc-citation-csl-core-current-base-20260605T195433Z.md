# Pandoc citation CSL current-base handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T195433Z`
Base: `bfc40ef615bba014a6ee8387c69ab693e3c94724`
Date: 2026-06-05 UTC

## Scope

Implemented one bounded CSL name-list punctuation behavior: `delimiter-precedes-last` for `cs:names`. The native PHP style parser now accepts `contextual`, `always`, `never`, and `after-inverted-name`, records explicit style use in citation metadata, rejects invalid values, and the citation processor applies the final-name delimiter without invoking Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online sanitizers, or online services.

## Patch

- `src/CslStyle.php`: parse and validate `delimiter-precedes-last` on `cs:names`, and carry explicit/default name-rendering metadata through merged macro/layout options.
- `src/CitationCslProcessor.php`: join rendered names through a bounded `delimiter-precedes-last` decision path while preserving the existing non-explicit bibliography fallback.
- `tests/CitationCslProcessorTest.php`: added a focused red-first coverage case for citation `never`, bibliography `always`, citation `always`, bibliography `after-inverted-name`, WordPress escaped output, and invalid-value rejection.
- `examples/wordpress-citation-csl-delimiter-last-handoff.php`: added a WordPress handoff smoke and self-test.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: recorded the +1 focused PASS and +1 mapped native CSL support case.

## Evidence

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1370 assertions, 0 failures`.
- Red-first after adding expectations: the focused run failed on missing `delimiterPrecedesLast` metadata with `1 test files, 1372 assertions, 1 failures`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1385 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-delimiter-last-handoff.php --self-test` passed.
- PHP lint passed for `src/CslStyle.php`, `src/CitationCslProcessor.php`, `tests/CitationCslProcessorTest.php`, and `examples/wordpress-citation-csl-delimiter-last-handoff.php`.
- JSON validation passed for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1058 -> 1059`.
- `benchmarkDenominator.mapped`: `1511 -> 1512`.
- `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused CitationCslProcessor assertions: `1370 -> 1385` (`+15`) with one new focused PASS case.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` components. The upstream-runner dependency blocker remains unchanged: full upstream Pandoc runner parity still needs a hydrated Pandoc checkout and Haskell Tasty executable builds.

## Non-overlap

This deliberately avoids already accepted CSL date-parts, locator/page labels, subsequent-author substitution, `et-al-use-last`, BibLaTeX pagination, and delimiter-before-et-al slices. It only covers final delimiter behavior for non-et-al name lists.
