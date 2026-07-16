# Pandoc Citation/CSL Gendered Ordinal Handoff

Slice: `pandoc-citation-csl-core-current-base-20260608T205418Z`
Base accepted HEAD: `65a6df3ab5094e251e3a86a2aa20ace8a8f50ea8`

## Source Truth

- Official CSL 1.0.2 specification: https://docs.citationstyles.org/en/v1.0.2/specification.html
- The bounded behavior covered here is CSL locale term `gender` plus ordinal `gender-form` selection: number variables rendered as `ordinal` or `long-ordinal` use the gender of their label term, and ordinal day date-parts use the gender of the month term.
- No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Implementation

- `CslStyle` now stores optional locale term gender metadata and supports gender-specific ordinal term keys.
- `CslStyle::term()` and `termOrNull()` accept an optional gender form while preserving existing call sites.
- `CslStyle::termGender()` exposes the gender for number-variable label terms and month terms.
- CSL locale parsing validates `gender` and `gender-form` values as `feminine` or `masculine`, rejects `gender` on ordinal terms, and rejects `gender-form` on non-ordinal terms.
- `CitationCslProcessor` now passes gender context into ordinal and long-ordinal number formatting, text-variable number formatting, and ordinal day rendering.
- Added a WordPress example smoke for localized edition/chapter/month ordinal output.

## Evidence

- No rework notes existed for `port-pandoc-*.needs-lane-rework.md`.
- Baseline before the new test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2883 assertions, 0 failures`
- Red-first after adding the focused test and before implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2887 assertions, 1 failures`
  - Failure showed neutral masculine fallback for feminine edition/month ordinals.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2893 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-gender-ordinal-handoff.php --self-test`
  - `wordpress-citation-csl-gender-ordinal-handoff self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/CslStyle.php` passed
  - `php -l lanes/pandoc/src/CitationCslProcessor.php` passed
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` passed
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-gender-ordinal-handoff.php` passed
- JSON validation:
  - `lanes/pandoc/lane-status.json` passed `json_decode(..., JSON_THROW_ON_ERROR)`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed `json_decode(..., JSON_THROW_ON_ERROR)`
- `git diff --check -- lanes/pandoc` passed with no output.

## Counters

- Focused Citation/CSL assertions: `2883 -> 2893` (`+10`).
- New focused PHP PASS case: `+1`.
- `lane-status.json` `phpPass`: `1838 -> 1839`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2262 -> 2263`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP CSL style parser, citation processor, Markdown reader, and WordPress block writer. The full upstream Pandoc/citeproc runner remains outside this isolated worker.

## Non-Overlap

This does not repeat accepted CSL coverage for date-part forms, day ordinal limiting, number variables such as `version` or `section`, ordinal `match` attributes, institution short-parts, subsequent author substitution, et-al behavior, locator labels, or BibTeX/BibLaTeX metadata handoff.

## Follow-Up

A good next Citation/CSL slice would be abbreviation-list lookup, locale-specific name delimiter variants, note-style output parity, or explicit citeproc parity diagnostics. Do not repeat gendered ordinal term selection.
