# BibTeX/CSL LaTeX Punctuation Macro Handoff

- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T025739Z`
- Accepted base: `f3cb4f0219cafa35ccd839e4b1e650317d63e7bb`
- Behavior: bounded `.bib` text decoding for LaTeX punctuation macros used in titles, container titles, and notes: `\textquotedblleft`, `\textquotedblright`, `\textquoteleft`, `\textquoteright`, `\textemdash`, `\ldots`, `\dots`, `\textellipsis`, plus simple quote sequence normalization. Raw BibTeX fields remain in `rawBibtex`.
- Non-overlap: extends BibTeX text decoding after accepted TeX accent/special-letter and wrapper stripping, source-locator, reference-crossref, creator-role, shorthand, keyword, date, field annotation, source-file, and abbreviation JSON slices. Does not touch CSL style XML rendering, citation disambiguation, DOCX/ODF/EPUB/math/archive behavior.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3421 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3433 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-bibtex-csl-latex-punctuation-handoff.php --self-test` -> `wordpress-bibtex-csl-latex-punctuation-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Keep broader BibLaTeX datamodel aliases, CSL locale/style parity, title-case/sentence-case behavior, and upstream Haskell runner dependency closure as separate bounded slices.
