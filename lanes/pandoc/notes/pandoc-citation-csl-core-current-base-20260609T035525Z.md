# Pandoc Citation/CSL Numeric Sort Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T035525Z`
Base accepted HEAD: `4cca1c57da8720c140326c22572dbfb45205f318`

## Scope

- Implemented one bounded Citation/CSL support cluster for CSL numeric sort keys.
- `CitationCslProcessor` now builds integer tuple sort keys for bounded number-like CSL variables including `page`, `page-first`, `number`, `edition`, `volume`, `issue`, `chapter-number`, `number-of-pages`, `number-of-volumes`, `collection-number`, `section`, `part-number`, `part`, `printing-number`, `supplement`, `supplement-number`, and `version`.
- Numeric values sort by zero-padded integer tokens, so `2`, `2-3`, and `10` order numerically instead of lexically; nonnumeric labels such as `Special edition` remain explicit text fallback sort values.
- Rendering output is unchanged: the same `cs:number` and `cs:label` paths still render volume labels, page ranges, and nonnumeric labels for reviewer-facing WordPress output.

Source truth: CSL 1.0.2 defines style sort keys through `cs:sort` / `cs:key` and number variables for bounded numeric metadata such as `volume`: <https://docs.citationstyles.org/en/v1.0.2/specification.html>.

## Evidence

- Red-first focused verification before implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Red-first result: `1 test files, 3588 assertions, 1 failures`; the new numeric sort case rendered `2-3`, `Special edition`, `10`, `2` instead of numeric order.
- Final focused verification: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Final result: `1 test files, 3593 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-numeric-sort-handoff.php --self-test`
- Example result: `wordpress-citation-csl-numeric-sort-handoff self-test passed`
- New focused test case adds 1 PHP PASS line and 6 focused assertions.

## Status Delta

- `lane-status.json` `phpPass`: `2260 -> 2261`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2665 -> 2666`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. The patch reuses native PHP `CslStyle` sort parsing, `CitationCslProcessor` sorting/rendering, the existing CSL numeric classifier, `MarkdownReader`, `WordPressBlockWriter`, focused lane tests, and a lane-local WordPress self-test example. Full upstream Pandoc/citeproc runner parity remains outside this isolated slice.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids the already accepted CSL printing/supplement number rendering, part/version/section rendering, count-label pluralization, source-variable sort keys, date-variable sort keys, citation-number assignment, macro sort keys, and BibTeX/BibLaTeX metadata handoffs. The behavior here is specifically numeric comparison for CSL style sort keys over bounded number variables.

## Follow-Up

Keep remaining Citation/CSL work focused on non-overlapping CSL 1.0.2 gaps such as issue-number edge cases, note-style bibliography behavior, or locale term forms.
