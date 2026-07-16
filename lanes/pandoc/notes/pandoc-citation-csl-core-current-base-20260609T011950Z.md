# Pandoc Citation/CSL Bibliography Options Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T011950Z`
Base accepted HEAD: `403bbfa850b87a30b18d0488738d4e785be58580`

## Scope

This slice maps one bounded native Citation/CSL support case: CSL bibliography
options that already reach the native AST now survive into the WordPress review
handoff as safe `<dl>` metadata.

- `CslStyle` validates `second-field-align`, accepting only the CSL values
  `flush` and `margin`.
- `WordPressBlockWriter` renders CSL bibliography definition lists with
  `class="pandoc-csl-bibliography"` and the bounded data attributes
  `data-csl-hanging-indent`, `data-csl-entry-spacing`,
  `data-csl-line-spacing`, and `data-csl-second-field-align`.
- The WordPress handoff stays text-only for bibliography entries without
  repeating existing CSL display-part rendering.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3212 assertions, 0 failures`
- Red-first focused command after adding the test, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3223 assertions, 1 failures`
  - Failure: WordPress output rendered a plain `<dl>` without CSL bibliography
    option metadata.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3226 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-bibliography-options-handoff.php --self-test`
  - Result: `wordpress-citation-csl-bibliography-options-handoff self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-bibliography-options-handoff.php`
  - Result: no syntax errors.
- Diff whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2029 -> 2030`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2444 -> 2445`
- `mappedCitationCslCoreCases`: `12 -> 13`
- Focused Citation/CSL coverage: `3212 -> 3226` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `CslStyle`,
`CitationCslProcessor`, `WordPressBlockWriter`, focused Citation/CSL tests, and
the new WordPress bibliography-options example.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, Stack, external bibliography manager, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted CSL bibliography layout parsing, top-level or
nested display-part rendering, rendering formatting metadata, term-form
fallbacks, source/date sort keys, subsequent-author substitution, name
rendering, locator/page labels, citation-number formatting, BibTeX/BibLaTeX
metadata mapping, or upstream-runner dependency audits. It only adds the
WordPress list-level handoff and validation for already parsed bibliography
options.

## Follow-Up

A next non-overlapping Citation/CSL slice could cover note-style bibliography
behavior, remaining style-option semantics, or a distinct BibLaTeX-to-CSL
provenance handoff not already covered by bibliography options, display parts,
source/date sorting, term forms, or name rendering.

## Root Harness

Not run - isolated micro-slice.
