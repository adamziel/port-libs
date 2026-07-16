# Pandoc BibTeX/CSL Label Field Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T112335Z`
Base accepted HEAD: `b3982ce3cf92154632f7bf678e3981a27b4e3514`

## Scope

This slice preserves imported BibLaTeX label disambiguation metadata without attempting full citeproc label generation. `BibtexCslParser` now maps `labelalpha`, `labeltitle`, `extradate`, and `extratitle`, plus dashed aliases, into CSL-like review fields `label-alpha`, `label-title`, `extra-date`, and `extra-title`.

`CitationCslProcessor` normalizes those fields as `labelAlpha`, `labelTitle`, `extraDate`, and `extraTitle`, renders them in default review bibliography output, and exposes them through bounded CSL text variables using both dashed and BibLaTeX spellings. The WordPress example keeps the imported metadata visible in bibliography review blocks.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed as expected with `1 test files, 2439 assertions, 1 failures` because `labelalpha` was dropped before implementation.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2467 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-label-fields-handoff.php --self-test` passed.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing bounded `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths.

Out of scope: generated `labelalpha`/`extradate` disambiguation, citation abbreviation files, full citeproc/Pandoc label parity, and any external bibliography runner.
