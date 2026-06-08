# pandoc-citation-csl-core-current-base-20260608T123934Z

## Scope

Mapped one bounded Citation/CSL creator-variable gap on accepted base
`08a602069d62d27c6fc675bbd49dd0be1fd7a9d2`: `<text variable="redactor">`
now renders the same normalized redactor creator list as
`<names variable="redactor">` for citation, bibliography, and WordPress
bibliography handoff paths.

## Source Truth

- Local source showed `redactor` already parsed from CSL JSON/BibLaTeX
  editor role metadata and already present in creator condition/name lookup
  paths.
- The missing behavior was limited to `CitationCslProcessor::renderVariableValue()`,
  where adjacent creator variables such as `reviewed-author`, `founder`, and
  `continuator` had `<text variable=...>` rendering but `redactor` did not.
- No external citeproc, BibTeX, Biber, Pandoc, Cabal/Haskell runner, Word,
  LibreOffice, online service, or live-service provider test was executed.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2458 assertions, 0 failures`.
- Red-first focused test after adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 2462 assertions, 1 failures` because
  `<text variable="redactor">` omitted the redactor creator names while
  `<names variable="redactor">` rendered them.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2465 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-redactor-variable-handoff.php --self-test`
  passed with `wordpress-citation-csl-redactor-variable-handoff self-test passed`.

## Status Delta

- `lane-status.json` `phpPass`: `1642 -> 1643`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2062 -> 2063`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses
`CitationCslProcessor` creator-name rendering, the existing CSL style parser
summary, `CitationCslProcessorTest.php`, `MarkdownReader`, and
`WordPressBlockWriter`.

## Non-Overlap

This slice does not overlap the recent charset/Unicode width, upstream-runner
dependency audit, BibTeX pagination, BibTeX event-place list, or CSL
subsequent-author rule handoffs. It is restricted to the native Citation/CSL
redactor creator text-variable mapping.

## Root Harness

Not run - isolated micro-slice.
