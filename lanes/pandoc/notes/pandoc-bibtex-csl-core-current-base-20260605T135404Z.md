# Pandoc BibTeX/CSL Core Current Base - Library Call Number

Slice: `pandoc-bibtex-csl-core-current-base-20260605T135404Z`
Base accepted HEAD: `b69c22e85097291dda52d2a9b156c6f19db473c3`
Date: 2026-06-05 UTC

## Scope

Implemented one bounded BibTeX/CSL support-library behavior: BibLaTeX `library`
and `callnumber` metadata now flows into CSL `call-number`, normalized item
`callNumber`, CSL style variable rendering, default review bibliography
metadata, and the WordPress bibliography handoff.

Source truth:

- BibLaTeX manual, CTAN: `library` is intended for library name and call-number
  style metadata, optionally printed by bibliography styles.
  https://ctan.math.illinois.edu/macros/latex/contrib/biblatex/doc/biblatex.pdf
- CSL input JSON schema: `call-number` is a string item variable.
  https://raw.githubusercontent.com/citation-style-language/schema/master/schemas/input/csl-data.json

## Changes

- `src/BibtexCslParser.php`: maps `callnumber`, `call-number`, and BibLaTeX
  `library` into raw CSL `call-number`.
- `src/CitationCslProcessor.php`: normalizes raw `call-number` into
  `callNumber`, renders it in default review metadata, and exposes it to CSL
  `<text variable="call-number"/>`.
- `tests/CitationCslProcessorTest.php`: adds a focused red-first case covering
  BibLaTeX `library`, `callnumber`, direct CSL `call-number`, custom CSL style
  rendering, and WordPress block bibliography output.
- `examples/wordpress-bibtex-csl-handoff.php`: extends the local self-test
  smoke with a WordPress-visible archive call-number bibliography entry.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`: record one additional
  mapped native BibTeX/CSL support case.

## Red-First Evidence

Before the implementation, the new focused test failed because raw CSL
`call-number` metadata was absent:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded biblatex library call numbers into csl metadata
Values are not identical
Expected: 'NYPL Manuscripts Division, MS 42 Box 7 Folder 3'
Actual: NULL
1 test files, 1149 assertions, 1 failures
```

## Verification

```text
php -l lanes/pandoc/src/BibtexCslParser.php
No syntax errors detected in lanes/pandoc/src/BibtexCslParser.php

php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1162 assertions, 0 failures

php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); } echo "json ok\n";'
json ok
```

```text
git diff --check -- lanes/pandoc
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `933 -> 934`
- `benchmarkDenominator.mapped`: `1389 -> 1390`
- `mappedBibtexCslCoreCases`: `2 -> 3`
- `bibtexCslCoreAssertions`: `38 -> 53`
- Focused `CitationCslProcessorTest.php`: `+1` PASS case and `+15`
  assertions, ending at `1 test files, 1162 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`,
`WordPressBlockWriter`, and bounded CSL style renderer. No Pandoc, citeproc,
BibTeX, Biber, Cabal build/solver/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser
renderer, online sanitizer, or online service was executed.

The upstream-runner dependency gate remains unchanged: hydrate the pinned
Pandoc checkout and verify the Cabal test-suite closure before any non-mutating
runner plan is marked ready.

## Non-Overlap

This does not repeat accepted BibTeX/CSL slices for entry subtype, split URL
dates, journal abbreviations, page-first metadata, event/organizer metadata,
ID aliases, shorthand labels, xdata/crossref inheritance, related entries,
or secondary editor role metadata. It only owns the library/call-number to CSL
`call-number` handoff.
