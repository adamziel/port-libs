# Pandoc Citation/CSL Core Current Base 20260608T045701Z

Micro-slice: `pandoc-citation-csl-core-current-base-20260608T045701Z`
Base accepted HEAD: `a7130e39566f87e0f070ab864cbb860b9ffe3872`

## Behavior

Implemented bounded CSL citation `cs:name name-as-sort-order` rendering. `CslStyle` now keeps whether `name-as-sort-order` was explicit in the CSL style, and `CitationCslProcessor` uses the bibliography-style family/given formatter for citation names only when the citation style explicitly requests sorted names. The default author-date label fallback stays family-only.

Source truth: CSL 1.0.2 `cs:name` `name-as-sort-order` / name-part ordering behavior.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused check: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2281 assertions, 0 failures`.
- Red-first check after adding the focused assertion failed as expected with `1 test files, 2285 assertions, 1 failures`: expected `(Smith, A., and Roe, P. 2026; Ng, N. 2025)`, actual `(Smith, and Roe 2026; Ng 2025)`.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2289 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-name-sort-order-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1541 -> 1542`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1962 -> 1963`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`.
- Focused Citation/CSL coverage: `+1` PHP PASS case and `+8` focused assertions from the current accepted baseline.

## Dependency Closure

No new support component is needed. The slice reuses native `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and existing focused PHP test harness coverage. Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test commands, Haskell runners, external bibliography managers, online services, live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This slice avoids accepted Citation/CSL bibliography name sort order, family-given script order, demote-particle, delimiter-precedes-last, et-al/subsequent-et-al, et-al-use-last, subsequent-author substitution, date/locator/conditional handling, empty `cs:else` validation, BibTeX/BibLaTeX parsing, and upstream-runner dependency audit surfaces.
