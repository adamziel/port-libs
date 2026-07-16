# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T082552Z`

Base: `cf7ee25101ce82ce8d6c80475a68145da0b850cf`

## Behavior

- Added bounded CSL `<citation collapse="...">` parsing for `citation-number`, `year`, `year-suffix`, and `year-suffix-ranged`, with validation for unsupported values.
- Implemented native author-date citation cluster collapse for normal known citations when the CSL layout is default or author-date-shaped.
- Preserved explicit boundaries for locators, suffixes, missing items, non-normal citation modes, and unsupported custom citation layouts.
- Added WordPress-facing year-suffix collapse smoke coverage through `wordpress-citation-csl-year-suffix-handoff.php`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 855 assertions, 0 failures`.
- Red-first: after adding collapse expectations, the same focused test failed with `1 test files, 833 assertions, 2 failures` because `CslStyle` did not expose collapse metadata.
- Green: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 863 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-year-suffix-handoff.php --self-test` passed.

## Non-overlap

This slice stays within the `pandoc-citation-csl-core-*` ownership bucket. It does not change archive compression, DOCX/ODT/EPUB/PDF, YAML, doctemplate, table geometry, charset/Unicode, XML/HTML5 DOM, legacy DOC/CFB, dashboard, or root coordination files.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP CSL style parser, citation renderer, Markdown reader, and WordPress block writer. No external citeproc, Pandoc, Cabal build, Haskell runner, BibTeX, Biber, Word, LibreOffice, zip/unzip, template engine, TeX/PDF engine, browser renderer, online sanitizer, or online service was run.

Follow-up remains bounded to note-style/near-note positioning, citation-number collapse rendering, broader citeproc layout compression, and locale-specific collapse punctuation.
