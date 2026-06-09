# BibTeX/CSL reference crossref slice

- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T023250Z`
- Base: `baf3ce2966b31d81f7576b68e2155b8538ba2649`
- Behavior: bounded BibLaTeX `@reference` crossref handoff now promotes a data-only parent reference title into `@inreference`, `@bookinbook`, and supplement child `container-title` metadata, normalizes `bookinbook`/supplement child entries as chapter-like CSL items, and prevents inherited parent `options={dataonly}` from leaking into visible child review metadata.
- Non-overlap: extends the existing BibTeX crossref/xdata/entry-set/relation path after accepted reviewed-work, reprint-title, custom-field, keyword, language-option, refsection, sort, index-title, pagination, article-number, and legal/patent slices. It does not repeat entry-set summaries, related/xref metadata, source-file diagnostics, event metadata, custom creator roles, or Citation/CSL style rendering behavior.

## Verification

- Red-first focused check before parser change:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: failed as expected in `inherits bounded biblatex reference crossref titles into child containers`; expected `Migration Reference Desk`, actual empty `container-title`.
- Focused lane test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3390 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-reference-crossref-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-reference-crossref-handoff self-test passed`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Next

Keep broader BibLaTeX entry-type parity, citeproc edge behavior, full CSL locale/style parity, and upstream Haskell runner dependency closure as separate bounded slices.
