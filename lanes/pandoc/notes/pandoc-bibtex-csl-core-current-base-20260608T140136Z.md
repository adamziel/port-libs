# BibTeX/CSL Reference Context Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T140136Z`
Base accepted HEAD: `df943add6ecdc665f4d2de6ef6093bc35935d6e0`

## Behavior

This slice maps bounded BibLaTeX reference-context metadata into native CSL handoff records:

- `refsection = {2}` becomes `biblatex-refsection`.
- `refsegment = {migration-import}` becomes `biblatex-refsegment`.
- Normalized CSL items expose `biblatexRefsection`, `biblatexRefsegment`, and `biblatexReferenceContextSummary`.
- Default bibliography output keeps the reference context visible for import review.
- CSL text variables `refsection`, `refsegment`, `biblatex-refsection`, `biblatex-refsegment`, `biblatex-reference-context-summary`, and `reference-context` render the bounded metadata.
- The WordPress smoke confirms the metadata survives Markdown citation resolution and bibliography block rendering.

The source-truth contract is the lane's existing BibLaTeX metadata handoff model: entry-level bibliography-driver metadata that affects bibliography partitioning must be preserved for review instead of being dropped. This slice does not run or emulate Biber section/segment resolution.

## Focused Evidence

- Rework check: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed with `1 test files, 2496 assertions, 1 failures` because `biblatex-refsection` was not mapped.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2516 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+20` focused assertions in `CitationCslProcessorTest.php`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-refsection-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle` text-variable rendering, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1660 -> 1661`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2080 -> 2081`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 141`.

## Non-Overlap

This does not repeat accepted BibTeX/CSL entry options, related options, field annotations, name annotations, language options, gender, thesis type, date addenda, event-place lists, URL labels, custom user/list/name fields, article-number/eid, pagination, library call-number, or Citation/CSL name/date rendering slices. The bounded behavior is specific to BibLaTeX `refsection` and `refsegment` reference-context provenance and CSL/WordPress review handoff.

## Next Task

Choose a non-overlapping bounded BibTeX/CSL follow-up such as keyword/options/language provenance, related-entry set metadata, or a CSL style rendering gap. Keep it native PHP only and do not run Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.
