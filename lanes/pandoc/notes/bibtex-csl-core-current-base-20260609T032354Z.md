# BibTeX/CSL Label Prefix Sortinit Handoff

- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T032354Z`
- Base accepted HEAD: `ddc41a8931d632461ea1dfb31e90d2be40b8de1c`
- Scope: bounded BibLaTeX generated label and sort-initial metadata handoff.

## Behavior

Implemented one native BibTeX/CSL support-library cluster for generated
BibLaTeX bibliography grouping metadata:

- `labelprefix` / `label-prefix` -> CSL-like `label-prefix` and normalized
  `labelPrefix`.
- `extraalpha` / `extra-alpha` -> `extra-alpha` and normalized `extraAlpha`.
- `sortinit` / `sort-initial` -> `sort-initial` and normalized `sortInitial`.
- `sortinithash` / `sort-initial-hash` -> `sort-initial-hash` and normalized
  `sortInitialHash`.

The fields are now available to bounded CSL text-variable rendering, CSL sort
keys, default review bibliography metadata, direct CSL-like item input, and the
WordPress bibliography handoff example.

## Evidence

Red-first focused command after adding the new test:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: failed as expected because parsed `.bib` items did not include
`label-prefix` metadata. The focused run reported `1 test files, 3508
assertions, 1 failures`.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3534 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-bibtex-csl-label-prefix-sortinit-handoff.php --self-test
```

Result: `wordpress-bibtex-csl-label-prefix-sortinit-handoff self-test passed`.

Expected focused delta: +1 PHP PASS line and +27 focused assertions.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
zip/unzip, external bibliography manager, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This avoids accepted BibTeX/CSL clusters for source locators, reference
crossrefs, LaTeX punctuation macros, presort and sort override fields,
label-alpha/title and extra-date/title fields, shorthand sorting, creator
roles, event metadata, language/date lists, related/entryset metadata, source
file diagnostics, and citation/CSL rendering algorithms. The new behavior is
limited to generated label-prefix, extra-alpha, sort-initial, and
sort-initial-hash review metadata.

## Follow-Up

Keep broader BibLaTeX datamodel aliases, full citeproc parity, and upstream
Pandoc/Haskell runner closure as separate bounded slices.
