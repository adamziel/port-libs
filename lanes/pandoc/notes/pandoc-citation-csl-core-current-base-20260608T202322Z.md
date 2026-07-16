# Pandoc Citation/CSL Core Current Base - Section Number Variables

Slice: `pandoc-citation-csl-core-current-base-20260608T202322Z`
Base accepted HEAD: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Source Truth

- Official CSL 1.0.2 specification: `cs:number` renders number variables, `cs:label` renders locator/page/number-variable labels, and Appendix IV lists `section` as a number variable.
- Source consulted: https://docs.citationstyles.org/en/v1.0.2/specification.html
- The isolated worker did not have a hydrated Pandoc upstream checkout under `.upstream-cache/pandoc`, so no upstream Haskell runner or citeproc comparison was executed.

## Implementation

- Added normalized CSL item `section` metadata to `CitationCslProcessor`.
- Added `section` to the bounded native CSL number-variable allowlist used by `cs:number`, `cs:label`, and numeric `cs:text` forms.
- Reused the existing localized `section` terms (`section`, `sec.`, and section symbols) plus existing contextual plural detection, ordinal, long-ordinal, and roman number formatting.
- Added a WordPress citation handoff example covering numeric, range, and nonnumeric section values in citation clusters and bibliography output.

## Evidence

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2808 assertions, 0 failures`
  - Focused delta: `+1` PASS case / `+15` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-section-number-handoff.php --self-test`
  - `wordpress-citation-csl-section-number-handoff self-test passed`
- Lane counters:
  - `lane-status.json` `phpPass`: `1802 -> 1803`
  - `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2225 -> 2226`
  - `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing bounded CSL style parser, `CitationCslProcessor` item normalization and number formatting, Markdown reader, WordPress block writer, and focused Citation/CSL test harness. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted section locator labels, uncommon locator vocabulary, part-number numeric forms, version number forms, audiovisual creator variables, available/submitted date variables, or BibTeX/BibLaTeX metadata handoff. The behavior is limited to CSL item-level `section` as a number variable for `cs:number`, `cs:label`, numeric `cs:text`, and `is-numeric` conditionals.
