# Pandoc Citation/CSL Core Current Base - Version Number Variables

Slice: `pandoc-citation-csl-core-current-base-20260608T200513Z`
Base accepted HEAD: `e8d89b1bca7d948de85077feddadfe6b141d5ed7`

## Source Truth

- Official CSL 1.0.2 specification: `cs:number` renders number variables, `cs:label` can render locator/page/number-variable labels, and Appendix IV lists `version` as a number variable.
- Source consulted: https://docs.citationstyles.org/en/v1.0.2/specification.html

## Implementation

- Added `version` to the bounded native CSL number-variable allowlist used by `cs:number` and `cs:label`.
- Added default `version` long/short terms so labels render as `version`/`versions` and `ver.`/`vers.` with existing contextual plural handling.
- Added `version` to numeric `cs:text` forms so numeric versions can use `roman`, `ordinal`, and other bounded number forms without external citeproc.
- Added WordPress smoke coverage for version labels and number formatting through Markdown citation clusters and bibliography output.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2756 assertions, 0 failures`
- Red-first with the new test before source changes:
  - `1 test files, 2756 assertions, 1 failures`
  - Failure: `CSL citation label variable is not supported: version`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2770 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-version-number-handoff.php --self-test`
  - `wordpress-citation-csl-version-number-handoff self-test passed`

## Dependency Closure

No new support component is needed. This reuses the existing bounded CSL style parser, `CitationCslProcessor` number formatting, Markdown reader, and WordPress block writer. No Pandoc, Cabal solver/build/test command, Haskell runner, external citeproc, BibTeX, Biber, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the earlier BibLaTeX software/dataset `version` metadata text handoff, part-number numeric forms, locator labels, audiovisual creator variables, or doctemplate child control-key slice. The behavior gap is specifically CSL 1.0.2 `version` as a number variable for `cs:number`, `cs:label`, and numeric text forms.
