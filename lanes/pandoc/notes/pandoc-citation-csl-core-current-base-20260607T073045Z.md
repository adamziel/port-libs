# Pandoc Citation/CSL Page-Range Format Handoff

Slice: `pandoc-citation-csl-core-current-base-20260607T073045Z`
Base accepted HEAD: `2ad8162f5a3428f4ace3f2bf0a83927be18816b0`
Date: 2026-06-07 UTC

## Behavior

- Added bounded CSL style root `page-range-format` parsing for `chicago`, `expanded`, `minimal`, and `minimal-two`.
- Added native locale/default `page-range-delimiter` support and applied it to CSL `page` variables.
- Collapsed numeric page ranges in page variables while preserving nonnumeric page tokens and keeping locator range rendering non-collapsed.
- Added a WordPress handoff example that verifies the rendered citation paragraph and bibliography block.

## Source Truth And Non-Overlap

Source truth: CSL 1.0.2 page range formatting and localized page range delimiter behavior.
Reference: https://docs.citationstyles.org/en/v1.0.2/specification.html

This slice does not repeat accepted date-parts, literal dates, locator/page labels, locator range delimiters, number forms, et-al rendering, institution rendering, conditionals, subsequent-author substitution, or BibTeX/BibLaTeX metadata handoffs. It owns only style-level page-range-format behavior for CSL page variables in the native PHP citation processor.

No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1902 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1912 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-page-range-format-handoff.php --self-test` passed.
- PHP lint passed for `CslStyle.php`, `CitationCslProcessor.php`, `CitationCslProcessorTest.php`, and the new example.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1465` -> `1466`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1883` -> `1884`.
- `mappedCitationCslCoreCases`: `11` -> `12`.
- Focused citation coverage: `1902` -> `1912` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle` XML parsing, `CitationCslProcessor` page/locator rendering, `MarkdownReader`, `WordPressBlockWriter`, and focused PHP tests.

Remaining exclusions for future work: full citeproc parity, note-style citations, abbreviation-list lookup, bibliography disambiguation, and upstream Pandoc/Haskell runner parity.
