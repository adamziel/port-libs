# pandoc-citation-csl-core-current-base-20260608T233135Z

## Scope

- Lane: pandoc
- Micro-slice: `pandoc-citation-csl-core-current-base-20260608T233135Z`
- Accepted base: `9eb676a5cd9add619cf3b6f2435447962ecbfb04`
- Behavior cluster: bounded CSL `source` sort keys across citation clusters,
  citation-number assignment, bibliography order, and WordPress handoff output.

## Source Truth

CSL sort keys can target item variables, and the previous Citation/CSL slice
made `source` a normalized text variable. This slice closes the follow-up gap
by proving `source` works as a first-class sort variable instead of only as
rendered provenance text.

The fixture intentionally uses out-of-order citation input and source values
`zeta`, `alpha`, and `middle`. The expected output sorts the citation cluster,
assigns citation-number values from sorted bibliography order, and keeps the
WordPress definition list in the same source-provenance order.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3123 assertions, 0 failures`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3135 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-source-sort-handoff.php --self-test`
  - Result: `wordpress-citation-csl-source-sort-handoff self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-source-sort-handoff.php`
  - Result: no syntax errors.
- Diff whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1973 -> 1974`
- Focused Citation/CSL coverage: `1 test files, 3123 assertions -> 1 test
  files, 3135 assertions`
- No `UPSTREAM_TEST_MANIFEST.json` counter was changed; this is a bounded
  native support-library behavior fixture and WordPress handoff example.

## Dependency Closure

No new support component is needed. This reuses native
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, `MarkdownWriter`,
`WordPressBlockWriter`, and focused `CitationCslProcessorTest.php` coverage.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, external bibliography manager, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat the prior source-variable normalization/default
bibliography slice, source-file attachment diagnostics, BibLaTeX sort override
fields, presort fields, macro sort keys, citation-number numeric formatting,
section/version/part labels, subsequent-author substitution, or upstream-runner
dependency audits. It is limited to CSL `source` sort-key behavior and
WordPress bibliography order.

## Follow-Up

A next non-overlapping Citation/CSL slice could cover a distinct CSL
conditional/rendering variable gap, a remaining BibLaTeX-to-CSL provenance
handoff, or broader bibliography-layout sort coverage beyond source-sort order.

## Root Harness

Not run - isolated micro-slice.
