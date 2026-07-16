## Pandoc BibTeX/CSL Sort Shorthand Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T195004Z`
Base accepted HEAD: `e33874b6a59046b0ea8a8d0d93a0e5bb2e4b1b0b`

### Behavior

- Added bounded BibLaTeX `sortshorthand` / `sort-shorthand` handoff support in the native BibTeX-to-CSL path.
- `BibtexCslParser::bibtexItems()` now emits the explicit `sort-shorthand` field and an effective `shorthand-list-sort-key`.
- The effective list-of-shorthands key uses explicit `sortshorthand` when present and falls back to `shorthand` when absent.
- `CitationCslProcessor` normalizes these values as `sortShorthand` and `shorthandListSortKey`, exposes CSL variables `sort-shorthand`, `sortshorthand`, `list-shorthand`, and `shorthand-list-sort-key`, and sorts `<key variable="sort-shorthand"/>` by the effective list key.
- Default bibliography review metadata now shows explicit `Sort shorthand` values while fallback-only shorthand entries remain clean.
- Added a WordPress handoff smoke for Markdown citations plus appended bibliography blocks.

Source-truth basis: the prior BibTeX/CSL lane notes identify BibLaTeX shorthand metadata as in-scope. BibLaTeX documents `sortshorthand` as list-of-shorthands sorting metadata; this slice ports that bounded format contract without invoking external bibliography tools.

### Evidence

- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2700 assertions, 0 failures`.
- Red-first with only the new test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2702 assertions, 1 failures` because `sort-shorthand` was not parsed.
- Final focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 2725 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-sort-shorthand-handoff.php --self-test` -> `wordpress-bibtex-csl-sort-shorthand-handoff self-test passed`.

### Status Delta

- `phpPass`: `1750 -> 1751`.
- `benchmarkDenominator.mapped`: `2166 -> 2167`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 146`.
- Focused assertion delta: `+25`.

### Dependency Closure

No new native PHP support component is needed. This reuses `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

### Non-Overlap

This does not repeat accepted shorthand label/short-creator metadata, entry subtype, library call-number, pagination, article-number/eid, event-place list, refsection/refsegment, language-option, keyword, related-entry, index-title/indexsorttitle, or general sort-key/name/title override slices. It only covers `sortshorthand` and the effective list-of-shorthands sort key.

### Follow-Up

Possible BibTeX/CSL follow-ups: abbreviation-file handoff, list-of-shorthands writer block output, citation alias provenance, or another bounded BibLaTeX field that is not already covered.
