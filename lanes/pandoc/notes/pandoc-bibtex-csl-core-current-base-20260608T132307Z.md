# BibTeX/CSL Language Options Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T132307Z`
Base accepted HEAD: `f2c68bcb90cae7f8d5c420ad4c2ba78bf326142c`

## Behavior

This slice maps bounded BibLaTeX `langidopts` metadata into native CSL handoff records:

- `langidopts = {variant=mexican, hyphenation=traditional}` becomes `biblatex-language-options`.
- Normalized CSL items expose `biblatexLanguageOptions` and `biblatexLanguageOptionSummary`.
- Default bibliography output keeps language options visible for import review.
- CSL text variables `biblatex-language-options`, `langidopts`, `biblatex-language-option-summary`, and `language-option-summary` render the bounded metadata.
- The WordPress smoke confirms the metadata survives Markdown citation resolution and bibliography block rendering.

The source-truth contract is the lane's existing BibLaTeX metadata handoff model: entry-level bibliography-driver metadata that affects locale/language handling must be preserved for review instead of being dropped. This slice does not run or emulate Biber locale processing.

## Focused Evidence

- Rework check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2482 assertions, 0 failures`.
- First focused run after adding the fixture failed with `1 test files, 2483 assertions, 1 failures` because the fixture used semicolon-separated `langidopts` while the bounded BibLaTeX option parser accepts comma-separated option lists.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2494 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+12` focused assertions in `CitationCslProcessorTest.php`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-language-options-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle` text-variable rendering, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1654 -> 1655`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2074 -> 2075`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 133`.

## Non-Overlap

This does not repeat accepted BibTeX/CSL entry options, related options, field annotations, name annotations, gender, thesis type, date addenda, event-place lists, URL labels, custom user/list/name fields, article-number/eid, pagination, library call-number, or Citation/CSL name/date rendering slices. The bounded behavior is specific to BibLaTeX `langidopts` language-option metadata and CSL/WordPress review handoff.

## Next Task

Choose a non-overlapping bounded BibTeX/CSL follow-up such as refsection/refsegment provenance, richer entryset behavior, additional bibliography-driver review fields, or a CSL style rendering gap. Keep it native PHP only and do not run Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.
