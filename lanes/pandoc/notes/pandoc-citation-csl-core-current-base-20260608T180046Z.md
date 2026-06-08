# pandoc-citation-csl-core-current-base-20260608T180046Z

## Scope

Implemented bounded CSL `is-creator` condition support for the extended creator-name variables already normalized and rendered by the native Citation/CSL lane:

- `founder`
- `continuator`
- `reviser`
- `collaborator`

Styles can now branch on these variables with `match="all"`, `match="any"`, or `match="none"` while rendering the same creator names through `cs:names` in citation and bibliography layouts.

## Source Truth

The local lane contract already maps BibLaTeX extended editor roles into CSL creator-name variables and `CitationCslProcessor` already normalizes/renders `founder`, `continuator`, `reviser`, and `collaborator`. This slice closes the parser-side CSL `is-creator` condition gap so conditional routing matches the existing native name-variable support. No external citeproc behavior was executed.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note existed for this lane slice before editing.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2605 assertions, 1 failures`
- Initial failure: `CSL macro extended-creator-route choose branch is-creator variable is not supported: founder`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2623 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-citation-csl-extended-creator-condition-handoff.php --self-test`
  - `wordpress-citation-csl-extended-creator-condition-handoff self-test passed`

## Status Delta

- `lane-status.json` `phpPass`: `1712 -> 1713`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2133 -> 2134`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Focused Citation/CSL assertion result: red-first `2605 assertions / 1 failure`; final `2623 assertions / 0 failures`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, focused Citation/CSL tests, and a WordPress handoff example. Full upstream Pandoc/citeproc runner parity remains separate and was not attempted.

## Non-Overlap

This slice is limited to `is-creator` condition parsing and evaluation for extended creator variables already available to the renderer. It does not repeat the existing author/editor/translator `is-creator` condition test, BibLaTeX extended editor-role metadata mapping, redactor text-variable rendering, participant-name rendering, names substitute suppression, date/locator/number conditionals, or part-number rendering.

## Exclusions

Did not run Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external bibliography managers, browser renderers, online services, live provider tests, or live-service provider tests.

Root harness not run - isolated micro-slice.
