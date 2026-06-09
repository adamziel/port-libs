# Pandoc BibTeX/CSL Periodical Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T050111Z`
Session: `port-dev-pandoc-bibtex-csl-20260609T050111Z`
Base accepted HEAD: `945c3c6f54718c2e2a84ea6013a7f69ab7cd1d9a`

## Behavior

- `BibtexCslParser` now maps BibLaTeX `@periodical` entries to CSL `article-journal`, matching CSL periodical issue routing while preserving `rawBibtex.type` as `periodical`.
- The focused test covers `journaltitle`, issue `number`, pages, notes, CSL `<if type="article-journal">` conditionals, bibliography rendering, Markdown citation handoff, and WordPress paragraph/bibliography blocks.
- The WordPress smoke example demonstrates a periodical issue review packet without invoking Pandoc, BibTeX, Biber, citeproc, office tools, zip/unzip, or external converters.

## Evidence

- Accepted-base focused baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3697 assertions, 0 failures`.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3724 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-periodical-type-handoff.php --self-test` -> `wordpress-bibtex-csl-periodical-type-handoff self-test passed`.
- PHP lint: `php -l lanes/pandoc/src/BibtexCslParser.php`, `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`, and `php -l lanes/pandoc/examples/wordpress-bibtex-csl-periodical-type-handoff.php` all reported no syntax errors.

## Delta

- `lane-status.json` `phpPass`: `2343 -> 2344`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count: `2738 -> 2739`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 148`.
- Focused assertion delta: `+27`.

## Non-Overlap

This slice does not repeat the accepted BibTeX/CSL `@manual`, `@booklet`, `@letter`, `@suppperiodical`, direct creator, source-locator, xdata, punctuation macro, CSL JSON, YAML, DOCX, ODT, EPUB, archive, table, math, PDF-engine, XML/HTML5 DOM, charset, syntax-highlighting, or legacy DOC/CFB slices. It only adds the standard BibLaTeX `@periodical` entry-type normalization and its citation/WordPress handoff.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and the existing WordPress example-smoke pattern. Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Next

Choose another non-overlapping BibTeX/BibLaTeX datamodel or CSL variable gap, such as a remaining entry-type alias, crossref/date inheritance edge, or style variable mapping not already covered by the current type-routing cases.
