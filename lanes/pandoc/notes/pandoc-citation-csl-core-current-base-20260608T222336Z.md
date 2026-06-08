# pandoc-citation-csl-core-current-base-20260608T222336Z

## Scope

- Lane: pandoc
- Micro-slice: `pandoc-citation-csl-core-current-base-20260608T222336Z`
- Accepted base: `638c2a05c9464741270d591f95240e54d5519ba1`
- Behavior cluster: bounded CSL `cs:names` label terms for already-normalized audiovisual creator variables.

## Source-Truth Behavior

The native CSL handoff already normalized audiovisual creator roles such as `producer`, `performer`, `narrator`, `host`, `guest`, `executive-producer`, and `script-writer` into CSL item variables. This slice closes the next bounded label gap: when a CSL style uses `<label>` inside `<names>` for those variables, the processor should use deterministic locale-term defaults instead of falling back to raw term names such as `producer Producer`.

The patch adds bounded default term forms (`long`, `short`, `verb`, and `verb-short`) for those existing variables and keeps the rendering path inside `CslStyle` plus `CitationCslProcessor`; it does not add an external citeproc runner.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3006 assertions, 0 failures`
- Red-first focused command after adding the new test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3015 assertions, 1 failures`
  - Failure reason: the new audiovisual label case rendered raw labels like `producer Producer` and `guest Guest`.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3020 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-audiovisual-label-handoff.php --self-test`
  - Result: `wordpress-citation-csl-audiovisual-label-handoff self-test passed`

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1921 -> 1922`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2344 -> 2345`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. This reuses `CslStyle` default terms, `CitationCslProcessor` name-label rendering, the existing BibTeX-to-CSL creator mapping, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the earlier audiovisual creator-variable mapping slice, editor/translator `cs:names` label slice, `editortranslator` term slice, name `form="count"` slice, subsequent-author substitution slices, et-al slices, date-condition slices, or upstream-runner dependency audit slices. It only adds bounded default label terms for creator variables already present in the native CSL handoff.

## Follow-Up

A next non-overlapping Citation/CSL slice could add bounded label behavior for another already-normalized creator role set, or move to a distinct CSL conditional, sort, or rendering gap.
