# Pandoc BibTeX/CSL Entry Options Handoff

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260608T072034Z`
Base accepted HEAD: `0beefbb15b02a8a82f64dd1fad4516dc169139da`

## Behavior

- Adds bounded BibLaTeX entry `options` handoff for non-data-only entries.
- `BibtexCslParser` continues using `dataonly` to filter standalone data entries, while preserving visible entry switches such as `skipbib=false`, `useprefix=true`, and `maxnames=3` as `biblatex-options`.
- `CitationCslProcessor` normalizes those switches into `biblatexOptions`, renders them in default review bibliography metadata, and exposes `biblatex-options` plus `biblatex-option-summary` CSL text variables.
- Adds a WordPress handoff example proving the metadata survives Markdown citation rendering and bibliography block output.

Source truth: no local Pandoc upstream cache was present under `/home/claude/port-libs/.upstream-cache` for targeted reads in this isolated worktree. This slice ports the bounded BibLaTeX format contract already modeled by the lane's parser: `options` remains the entry-level switch list, `dataonly` remains the filtering switch, and non-filtering options are now preserved for CSL/WordPress review handoff instead of being dropped.

## Evidence

- Rework check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2310 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2327 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-entry-options-handoff.php --self-test` passed with `wordpress-bibtex-csl-entry-options-handoff self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/BibtexCslParser.php` -> `No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php` -> `No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> `No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-entry-options-handoff.php` -> `No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-entry-options-handoff.php`
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- Diff whitespace: `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1559 -> 1560`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1980 -> 1981`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 138`.
- Focused assertion delta: `+17` assertions in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses the native `BibtexCslParser` option-list splitter, `CitationCslProcessor` normalization/rendering, `MarkdownReader`, `WordPressBlockWriter`, focused Citation/CSL tests, and the lane-local WordPress handoff example.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Stack, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This patch does not touch BibLaTeX related-options, xref/crossref inheritance, custom user/list/name fields, event-place lists, article numbers, pagination, call numbers, CSL macro sorting, or date/name rendering. It only preserves entry-level BibLaTeX `options` metadata after `dataonly` filtering.

## Next Task

Choose a non-overlapping BibTeX/CSL gap such as additional BibLaTeX date/list/name metadata, bibliography-driver review fields, or style-variable exposure, still using native PHP support and focused lane tests only.
