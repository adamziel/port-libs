# Pandoc BibTeX/CSL Gender Handoff

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260608T083830Z`
Base accepted HEAD: `e7d50020ec7dcde5a97e73ec313bbb4fa11ac57c`

## Behavior

- Adds bounded BibLaTeX entry-level `gender` metadata handoff for non-data-only entries.
- `BibtexCslParser` preserves the field as CSL `gender` metadata while keeping raw BibTeX fields available for audit.
- `CitationCslProcessor` normalizes `gender`, `biblatex-gender`, and `biblatexGender` into `gender`, `biblatexGender`, and `biblatexGenderSummary`.
- Default bibliography review output now includes `BibLaTeX gender: ...` for preserved entry grammar metadata.
- CSL style rendering exposes `gender`, `biblatex-gender`, and `biblatex-gender-summary` text variables.
- Adds a WordPress handoff example proving the metadata survives Markdown citation rendering and bibliography block output.

Source truth: no local Pandoc upstream checkout was present under `/home/claude/port-libs/.upstream-cache` for targeted reads in this isolated worktree. This slice ports the bounded BibLaTeX format contract already modeled by the lane's parser and CSL handoff: entry-level bibliography-driver metadata is preserved for CSL/WordPress review instead of being dropped.

## Evidence

- Rework check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2340 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2356 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-gender-handoff.php --self-test` passed with `wordpress-bibtex-csl-gender-handoff self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/BibtexCslParser.php` -> `No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php` -> `No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> `No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-gender-handoff.php` -> `No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-gender-handoff.php`
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- Diff whitespace: `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1581 -> 1582`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2002 -> 2003`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 137`.
- Focused assertion delta: `+16` assertions in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses native `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, focused Citation/CSL tests, and the lane-local WordPress handoff example.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Stack, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This patch does not touch BibLaTeX related-options, xref/crossref inheritance, entry `options`, event-place lists, custom user/list/name fields, article numbers, pagination, call numbers, CSL macro sorting, or date/name rendering. It only preserves entry-level BibLaTeX `gender` metadata for CSL/WordPress review handoff.

## Next Task

Choose a non-overlapping BibTeX/CSL gap such as additional BibLaTeX date/list/name metadata, bibliography-driver review fields, or style-variable exposure, still using native PHP support and focused lane tests only.
