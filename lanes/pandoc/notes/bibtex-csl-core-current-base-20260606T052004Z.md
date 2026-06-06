# BibTeX/CSL reviewed-work metadata slice

- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260606T052004Z`
- Accepted base: `23932dd761e9b54b9c5be6a67898bcd0727918e3`
- Behavior: bounded BibLaTeX reviewed-work metadata handoff for `reviewtitle`/`reviewsubtitle`, `reviewed-title`, `references`, `dimensions`/`dimension`, and `scale`.
- Non-overlap: extends the existing BibTeX/CSL review metadata path after the accepted pagination and article-number/eid slices; it does not repeat call-number, pagination/bookpagination, issue-title, article-number/eid, PubMed, container-author, entry-subtype, or related-entry handoff behavior.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1505 assertions, 0 failures`.
- Red-first: after adding the focused reviewed-work metadata test, the same command failed with `1 test files, 1507 assertions, 1 failures` because `reviewed-title` item metadata was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1532 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test` passed with `wordpress-bibtex-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online sanitizer, online service, or live provider test was executed.

## Follow-Up

Keep broader BibLaTeX review relationships, annotation-specific role formatting, locale/style edge cases, and full citeproc parity as separate bounded slices.
