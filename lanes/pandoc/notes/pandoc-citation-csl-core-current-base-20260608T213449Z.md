# Pandoc Citation/CSL Current-Base: Localized Symbol And Terms

Slice: `pandoc-citation-csl-core-current-base-20260608T213449Z`
Base accepted HEAD: `17b111d85a0bb4b5cb849a471da21f0b1ab9bf09`
Date: 2026-06-08 UTC

## Behavior

This slice maps one bounded native Citation/CSL support case: CSL `name and="symbol"` joins now resolve through the style locale term table instead of hardcoding `&`.

- `CslStyle` now includes the default `and|symbol` term fallback as `&`.
- `CitationCslProcessor` now asks the active style for `term("and", "symbol")` when rendering symbol joins.
- The focused test covers a localized `<term name="and" form="symbol">+</term>` in both citation and bibliography layouts, plus the default `&` fallback.
- The WordPress smoke shows the localized symbol join surviving Markdown citation parsing, bibliography append, and block output.

## Verification

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 2951 assertions, 0 failures`

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 2960 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-citation-csl-symbol-and-handoff.php --self-test`
- Result: `wordpress-citation-csl-symbol-and-handoff self-test passed`

PHP syntax checks:

- `php -l lanes/pandoc/src/CslStyle.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/examples/wordpress-citation-csl-symbol-and-handoff.php`
- Result: no syntax errors detected for all changed PHP files

JSON and diff hygiene:

- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- Result: `json ok`
- `git diff --check -- lanes/pandoc`
- Result: passed with no output

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1871 -> 1872`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2297 -> 2298`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. The slice reuses native `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and a lane-local WordPress example. Full upstream Pandoc/citeproc runner parity remains outside this isolated slice.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids recent Citation/CSL and BibTeX/CSL rows for date-part rendering, is-uncertain-date conditionals, institution short-parts, et-al/subsequent-et-al, et-al-use-last, delimiter-precedes-last, subsequent-author substitution, BibLaTeX pagination, event-place lists, article-number/eid, call-number, and entry-subtype metadata.

Suggested next non-overlapping Citation/CSL work: bounded locale term forms beyond `and|symbol`, label pluralization, name delimiter variants, or note-style bibliography behavior not covered by this slice.
