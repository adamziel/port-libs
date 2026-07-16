## Pandoc BibTeX/CSL List Of Shorthands Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T203624Z`
Base accepted HEAD: `e76c4cc82ad1172514b0791041ad64c954f9e499`

### Behavior

- Adds bounded list-of-shorthands AST support for BibLaTeX entries with `shorthand`.
- Sorts shorthand-list entries by parsed `sortshorthand` / `sort-shorthand`, then by shorthand, title, and id for deterministic review output.
- Uses `shorthandintro` plus title text for definition bodies, while entries without a shorthand stay excluded from the shorthand list.
- Adds `appendShorthandList()`, `shorthandListBlocks()`, and `shorthandDefinitionList()` so Markdown and WordPress writers can emit the review list without changing normal citation or bibliography rendering.
- Adds a lane-local WordPress smoke covering the emitted heading and definition-list snippets.

Source-truth basis: this builds on the lane's existing BibLaTeX `shorthand`, `shorthandintro`, and `sortshorthand` normalization. The prior sort-shorthand slice already preserved the metadata; this slice ports the bounded output handoff for writer review blocks.

### Evidence

- Rework note check: `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` returned no files.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2834 assertions, 0 failures`.
- Red-first focused test after adding the shorthand-list expectation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2834 assertions, 1 failures` because `CitationCslProcessor::shorthandListBlocks()` was absent.
- Final focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2858 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-shorthand-list-handoff.php --self-test` -> `wordpress-bibtex-csl-shorthand-list-handoff self-test passed`.
- PHP lint for changed PHP files passed.
- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

### Status Delta

- `phpPass`: `1820 -> 1821`.
- `benchmarkDenominator.mapped`: `2244 -> 2245`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 145`.
- Focused assertion delta: `+24`.

### Dependency Closure

No new native PHP support component is needed. This reuses `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, and the focused Citation/CSL test harness.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

### Non-Overlap

This does not repeat accepted shorthand label parsing, short-creator metadata, `sortshorthand` metadata preservation, alias provenance, journal abbreviation, entry subtype, library call-number, pagination, article-number/eid, event-place list, refsection/refsegment, keyword, related-entry, index-title/indexsorttitle, or general sort-key/name/title override slices. It only emits a bounded writer-facing list-of-shorthands review block from already parsed BibLaTeX shorthand metadata.

### Follow-Up

Possible BibTeX/CSL follow-ups: abbreviation-file handoff or another bounded BibLaTeX field/style-variable exposure that is not already covered by shorthand-list output.
