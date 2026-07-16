# Pandoc BibTeX/CSL Supplemental Periodical Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T044712Z`
Session: `port-dev-pandoc-bibtex-csl-20260609T044712Z`
Base accepted HEAD: `4bd0353e68feb117d03d0d43e4710ee88b193cbf`

## Behavior

- `BibtexCslParser` now maps BibLaTeX `@suppperiodical` entries to CSL `article-journal`, matching the existing periodical-style issue-field handoff while preserving `rawBibtex.type` as `suppperiodical`.
- The focused test covers `journaltitle`, issue `number`, pages, notes, CSL `<if type="article-journal">` conditionals, bibliography rendering, Markdown citation handoff, and WordPress paragraph/bibliography blocks.
- The WordPress smoke example demonstrates a supplemental periodical review packet without invoking Pandoc, BibTeX, Biber, citeproc, or external converters.

## Evidence

- Baseline focused run before the change: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3681 assertions, 0 failures`.
- Red-first probe before the change: bounded `@suppperiodical` normalization returned raw type `suppperiodical` instead of CSL `article-journal`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3697 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-suppperiodical-type-handoff.php --self-test` -> `wordpress-bibtex-csl-suppperiodical-type-handoff self-test passed`.

## Delta

- `lane-status.json` `phpPass`: `2327 -> 2328`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count: `2723 -> 2724`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 137`.
- Focused assertion delta: `+16`.

## Non-Overlap

This slice does not repeat the accepted BibTeX/CSL `@manual`, `@booklet`, or `@letter` type-routing handoffs, nor the previously accepted direct creator, media type, legal/patent, thesis, unpublished speech, source/section/supplement, crossref title-part, CSL JSON, YAML, DOCX, ODT, EPUB, archive, table, math, PDF-engine, XML/HTML5 DOM, charset, syntax-highlighting, or legacy DOC/CFB slices.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and the existing WordPress example-smoke pattern. Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Next

Choose another non-overlapping BibTeX/BibLaTeX datamodel or CSL variable gap, such as a remaining entry-type alias, crossref/date inheritance edge, or style variable mapping not already covered by the current type-routing cases.
