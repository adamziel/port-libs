# pandoc-citation-csl-core-current-base-20260608T235150Z

## Scope

- Lane: pandoc
- Micro-slice: `pandoc-citation-csl-core-current-base-20260608T235150Z`
- Accepted base: `d882dae9d858147bc44d510727ef5cac23951c50`
- Behavior cluster: bounded CSL date-variable sort keys for `accessed`,
  `event-date`, and `original-date` across citation clusters, bibliography
  order, citation-number assignment, and WordPress handoff output.

## Source Truth

CSL sort keys can target item variables. The native PHP CSL processor already
renders the `accessed`, `event-date`, and `original-date` variables and already
has a date-aware sort helper for other date variables, but those three date
variables were not routed through first-class sort-key handling.

This slice keeps the implementation bounded by reusing the existing normalized
date sort value path. The fixture intentionally cites entries out of order and
sorts first by `accessed`, then by descending `event-date`, then by
`original-date`.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3148 assertions, 0 failures`
- Red-first focused command after adding the new test, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3153 assertions, 1 failures`
  - Failure: descending `event-date` sort was ignored and fell back to rendered
    citation text order.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3159 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-date-sort-handoff.php --self-test`
  - Result: `wordpress-citation-csl-date-sort-handoff self-test passed`

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1988 -> 1989`
- Focused Citation/CSL coverage: `1 test files, 3148 assertions -> 1 test
  files, 3159 assertions`
- No `UPSTREAM_TEST_MANIFEST.json` counter was changed; this is a bounded
  native support-library behavior fixture and WordPress handoff example.

## Dependency Closure

No new support component is needed. This reuses native PHP `CslStyle`,
`CitationCslProcessor`, date normalization, `MarkdownReader`, `MarkdownWriter`,
`WordPressBlockWriter`, and focused `CitationCslProcessorTest.php` coverage.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, external bibliography manager, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat the prior CSL `source` variable normalization/default
bibliography slice, source sort-key slice, `available-date`/`submitted` date
rendering slice, BibLaTeX sort override fields, presort fields, macro sort
keys, citation-number numeric formatting, section/version/part labels,
subsequent-author substitution, source-file attachment diagnostics, or
upstream-runner dependency audits.

## Follow-Up

A next non-overlapping Citation/CSL slice could cover another distinct CSL
conditional/rendering variable gap, bibliography sort-collapse interaction, or
BibLaTeX-to-CSL provenance handoff not already covered by source and date sort
behavior.

## Root Harness

Not run - isolated micro-slice.
