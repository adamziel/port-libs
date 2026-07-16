# Pandoc Citation/CSL Macro Sort Key Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260608T064107Z`
Base accepted HEAD: `73dd5a7d240710510b6ff5c4a47ec7f89b86803c`

## Behavior

- Adds bounded native CSL `sort` support for keys that use `macro=...`.
- `CitationCslProcessor` now renders the referenced macro in the active sort scope (`citation` or `bibliography`) and normalizes that rendered text before comparing sort keys.
- Visible citation and bibliography layouts remain independent from the sort macro; the test covers citation ascending macro order and bibliography descending macro order.
- Adds a WordPress handoff example proving the same macro-sorted citation cluster and bibliography order survive block output.

Source truth: CSL 1.0.2 permits sort keys to reference macros in citation and bibliography sort definitions (`https://docs.citationstyles.org/en/v1.0.2/specification.html#sort`). This slice ports the bounded format contract by rendering the local macro output instead of guessing a variable from the macro name.

## Evidence

- Rework check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2296 assertions, 0 failures`.
- Red-first: the same focused test failed before implementation with `1 test files, 2301 assertions, 1 failures`; macro sort keys fell back to author ordering instead of rendered macro output.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2310 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-macro-sort-handoff.php --self-test` passed with `wordpress-citation-csl-macro-sort-handoff self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/CitationCslProcessor.php` -> `No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> `No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-macro-sort-handoff.php` -> `No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-macro-sort-handoff.php`
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- Diff whitespace: `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1553 -> 1554`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1974 -> 1975`.
- `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused assertion delta: `+14` assertions in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses native `CslStyle` parsing, `CitationCslProcessor` macro rendering, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, focused Citation/CSL tests, and the lane-local WordPress handoff example.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This patch does not touch page-range rendering, institution short-parts, date predicates, locator labels/ranges, citation-number collapse, note-style first-reference numbering, participant roles, BibTeX metadata, or empty `cs:else` validation. It only maps rendered CSL macro output into citation and bibliography sort-key comparisons.

## Next Task

Choose a non-overlapping Citation/CSL gap such as locale/date predicates, disambiguation, additional variable rendering, or bibliography layout metadata, still using native PHP support and focused lane tests only.
