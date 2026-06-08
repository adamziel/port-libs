# Pandoc Citation/CSL Current-Base: Initialize False Names

Slice: `pandoc-citation-csl-core-current-base-20260608T214933Z`
Base accepted HEAD: `6f8463809fe932bed047f1bc503ab1bca68687f8`
Date: 2026-06-08 UTC

## Behavior

This slice maps one bounded native Citation/CSL support case: `cs:name initialize="false"` now preserves full given-name tokens while `initialize-with` still punctuates initials already present in source metadata.

- `CslStyle` parses `initialize` as a boolean name-rendering option and exposes it in style summaries for citation, bibliography, and element-level name rendering.
- `CitationCslProcessor` carries the option through normalized name rendering and adds an `initialize=false` given-name rendering path that keeps full tokens such as `James` while turning an existing `T` initial into `T.` when `initialize-with=". "` is present.
- The focused test covers citation output, bibliography output, WordPress blocks, summary metadata, and invalid boolean rejection.
- The WordPress smoke shows the behavior surviving Markdown citation parsing, bibliography append, and block output.

## Verification

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 2973 assertions, 0 failures`

Red-first check after adding the focused test:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 2975 assertions, 1 failures`
- Failure: `Expected: false; Actual: NULL` for missing `initialize` metadata.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 2985 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-citation-csl-initialize-false-handoff.php --self-test`
- Result: `wordpress-citation-csl-initialize-false-handoff self-test passed`

PHP syntax checks:

- `php -l lanes/pandoc/src/CslStyle.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/examples/wordpress-citation-csl-initialize-false-handoff.php`
- Result: no syntax errors detected for all changed PHP files

JSON and diff hygiene:

- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- Result: `json ok`
- `git diff --check -- lanes/pandoc`
- Result: passed with no output

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1890 -> 1891`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2312 -> 2313`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. The slice reuses native `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, lane-local focused PHP tests, and a lane-local WordPress self-test example. Full upstream Pandoc/citeproc runner parity remains outside this isolated slice.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids recent Citation/CSL and BibTeX/CSL rows for localized symbol terms, institution short-parts, et-al/subsequent-et-al, et-al-use-last, delimiter-precedes-last, subsequent-author substitution, date/name conditionals, BibLaTeX pagination, event-place lists, article-number/eid, call-number, and entry-subtype metadata.

Suggested next non-overlapping Citation/CSL work: bounded `name form="count"` label interactions, remaining locale term form variants, or note-style bibliography behavior not covered by this slice.
