# Pandoc Citation/CSL Printing Number Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T033751Z`
Base accepted HEAD: `74dfce3206dc1728f34071078950751a79a89c47`

## Scope

- Implemented one bounded Citation/CSL support cluster for CSL 1.0.2 number variables `printing-number` and `supplement-number`.
- Added native `CslStyle` parsing/default terms so `cs:number` and `cs:label` accept both variables.
- Added `CitationCslProcessor` normalization/rendering for direct `printing-number`, `printingNumber`, `supplement-number`, and `supplementNumber` item fields, with the existing `supplement` field retained as a fallback alias for `supplement-number`.
- Reused the existing bounded number formatter for ordinal, roman, long-ordinal, contextual labels, numeric `cs:text` forms, and `is-numeric` conditionals.

Source truth: CSL 1.0.2 specifies `cs:number` over number variables and lists `printing-number` and `supplement-number` under Appendix IV number variables: <https://docs.citationstyles.org/en/v1.0.2/specification.html#number-variables>.

## Evidence

- Red probe before patch: `php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\CitationCslProcessor; ... <number variable="printing-number"/> ...'`
- Result: `InvalidArgumentException: CSL citation number variable is not supported: printing-number`
- Focused verification: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 3560 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-printing-number-handoff.php --self-test`
- Result: `wordpress-citation-csl-printing-number-handoff self-test passed`
- New focused test case adds 26 direct assertions and 1 PASS line.

## Status Delta

- `lane-status.json` `phpPass`: `2238 -> 2239`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2647 -> 2648`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. The patch reuses native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, focused lane tests, and a lane-local WordPress self-test example. Full upstream Pandoc/citeproc runner parity remains outside this isolated slice.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids the already accepted CSL `supplement` shortcut handoff, part/version/section number variables, count-label pluralization, source-variable metadata, authority creator names, name-label quote handling, and BibTeX/BibLaTeX article-number/eid metadata. The behavior here is specifically the CSL 1.0.2 variables `printing-number` and `supplement-number` as number variables.

## Follow-Up

Keep remaining Citation/CSL work focused on non-overlapping CSL 1.0.2 variable/rendering gaps such as issue-number edge cases, locale term forms, or note-style bibliography behavior.
