# Pandoc BibTeX/CSL Eprint Archive Summary Handoff

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260608T212208Z`
Base accepted HEAD: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Behavior

- Adds bounded BibLaTeX eprint/archive summary metadata handoff for repository-backed bibliography entries.
- `BibtexCslParser` now derives `archive-summary` from archive source, eprint/archive location, and eprint class metadata while preserving existing `archive`, `archive-place`, and `archive_location` fields.
- `CitationCslProcessor` normalizes `archive-summary`, `archiveSummary`, `eprint-summary`, and `eprintSummary` input metadata into `archiveSummary`.
- Direct CSL item inputs synthesize the same summary from `archive` plus `archive-location` when no explicit summary is present.
- CSL text rendering exposes `archive-summary` and `eprint-summary` variables for bounded review styles.
- Adds a WordPress handoff example proving the summary survives Markdown citation rendering and bibliography block output.

Source truth: no local Pandoc upstream checkout was present under `/home/claude/port-libs/.upstream-cache` for targeted reads in this isolated worktree. This slice ports the bounded BibLaTeX/CSL format contract already modeled by the lane's native parser and style renderer: repository identifiers are preserved for CSL/WordPress review instead of being dropped or only available as separate raw fields.

## Evidence

- Rework check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2941 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed with `1 test files, 2943 assertions, 1 failures` because `archive-summary` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2954 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-eprint-summary-handoff.php --self-test` passed with `wordpress-bibtex-csl-eprint-summary-handoff self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/BibtexCslParser.php` -> `No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php` -> `No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> `No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-eprint-summary-handoff.php` -> `No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-eprint-summary-handoff.php`
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- Diff whitespace: `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1863 -> 1864`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2290 -> 2291`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 134`.
- Focused assertion delta: `+13` assertions in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses native `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, focused Citation/CSL tests, and the lane-local WordPress handoff example.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Stack, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This patch does not touch BibLaTeX related-options, xref/crossref inheritance, entry `options`, event-place lists, custom user/list/name fields, article numbers, pagination, call numbers, gender metadata, CSL macro sorting, or date/name rendering. It only derives and exposes repository eprint/archive summaries for CSL/WordPress review handoff.

## Next Task

Choose a non-overlapping BibTeX/CSL gap such as related entry sets, bibliography-driver review fields, additional BibLaTeX date/list/name metadata, or style-variable exposure, still using native PHP support and focused lane tests only.
