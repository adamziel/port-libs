# pandoc-citation-csl-core-current-base-20260608T160714Z

Accepted base: `7bb9457c376694c6a19cdc4541a59964cc2f5d73`

## Slice

Implemented one bounded Citation/CSL support-library behavior: CSL `cs:if` and
`cs:else-if` branches can now declare `is-date`. The native style parser keeps
the predicate in the style summary, and the citation processor evaluates it
against normalized CSL date variables (`issued`, `accessed`, `original-date`,
and `event-date`) by checking date-parts, display text, or literal date text.

This remains distinct from the accepted `is-uncertain-date` and
`is-circa-date` slices. Those marker predicates continue to inspect uncertain
and circa metadata, while `is-date` checks date presence for `match="all"`,
`match="any"`, and `match="none"` branch routing.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note existed for this lane slice before editing.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2575 assertions, 1 failures`
  - Failure showed `is-date` branches were rejected by `CslStyle` as unsupported choose predicates.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2591 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-citation-csl-is-date-handoff.php --self-test`
  - `wordpress-citation-csl-is-date-handoff self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-is-date-handoff.php`
  - All reported no syntax errors.
- JSON validation: `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Passed.

## Status Delta

- `lane-status.json` `phpPass`: `1695 -> 1696`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2115 -> 2116`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Focused assertion delta: final `CitationCslProcessorTest.php` reports `2591` assertions with one new focused PASS case.

## Dependency Closure

No new native PHP support component is needed. This slice reuses `CslStyle`,
`CitationCslProcessor`, normalized date metadata, `MarkdownReader`,
`WordPressBlockWriter`, focused Citation/CSL tests, and a lane-local WordPress
handoff example.

Full upstream Pandoc/citeproc runner parity, external BibTeX/Biber execution,
Cabal/Haskell runners, online services, live provider tests, and
live-service provider tests remain out of scope.

## Non-Overlap

This slice only adds CSL `is-date` conditional branch parsing and rendering. It
does not repeat accepted CSL variable/type/position conditionals,
`is-numeric`, `is-creator`, `is-uncertain-date`, `is-circa-date`, locator/page
labels, near-note positions, et-al rendering, subsequent-author substitution,
bibliography display parts, or BibTeX/BibLaTeX metadata mapping.

## Exclusions

Did not run Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell test binaries,
Word, LibreOffice, zip/unzip, external bibliography managers, browser
renderers, online services, live provider tests, or live-service provider
tests.

Root harness not run - isolated micro-slice.
