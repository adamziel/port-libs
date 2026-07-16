## Pandoc BibTeX/CSL Citation Alias Provenance

Slice: `pandoc-bibtex-csl-core-current-base-20260608T201529Z`
Base accepted HEAD: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

### Behavior

- Preserves BibLaTeX `ids` alias lists as both `citationAliases` and a semicolon-delimited `citationAliasSummary` during native CSL item normalization.
- Keeps existing alias resolution behavior: alias citation keys resolve to the canonical bibliography item while retaining `citationAlias` provenance on the resolved alias item.
- Adds default bibliography review metadata so WordPress bibliography review blocks expose the source-era citation aliases instead of hiding them.
- Exposes bounded CSL variables `citation-alias-summary` and `citation-aliases-summary` for styles that need semicolon-delimited alias provenance while keeping existing comma-delimited `citation-aliases` behavior.
- Adds a lane-local WordPress smoke covering Markdown citation rendering, alias resolution, and one canonical bibliography entry with visible alias provenance.

Source-truth basis: BibLaTeX `ids` aliases are already parsed and used for canonical citation resolution in this lane. This slice ports the bounded handoff contract for review output and CSL variable exposure without invoking external bibliography tools.

### Evidence

- Rework note check: `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` returned no files.
- Red-first with the focused alias-provenance expectations: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2764 assertions, 2 failures` because `citationAliasSummary` was absent.
- Final focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2790 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-citation-alias-handoff.php --self-test` -> `wordpress-bibtex-csl-citation-alias-handoff self-test passed`.
- Coupled example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test` -> `wordpress-bibtex-csl-handoff self-test passed`.
- PHP lint for changed PHP files passed.
- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

### Status Delta

- `phpPass`: `1795 -> 1796`.
- `benchmarkDenominator.mapped`: `2215 -> 2216`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 147`.
- Focused assertion delta: `+26`.

### Dependency Closure

No new native PHP support component is needed. This reuses `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the focused Citation/CSL test harness.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

### Non-Overlap

This does not repeat accepted alias key resolution, shorthand label, short-creator metadata, sortshorthand, entry subtype, library call-number, pagination, article-number/eid, event-place list, refsection/refsegment, keyword, related-entry, index-title/indexsorttitle, or general sort-key/name/title override slices. It only makes already parsed BibLaTeX `ids` alias provenance visible in default bibliography review output and bounded CSL summary variables.

### Follow-Up

Possible BibTeX/CSL follow-ups: abbreviation-file handoff, list-of-shorthands writer block output, or another bounded BibLaTeX field/style-variable exposure that is not already covered.
