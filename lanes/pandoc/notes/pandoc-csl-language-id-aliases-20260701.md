# Pandoc CSL language id aliases

Bead: `plib-yrbrk`
Date: 2026-07-01 UTC
Area: Pandoc CSL/BibTeX/BibLaTeX citation support

`CitationCslProcessor` now normalizes bounded direct CSL JSON language id
aliases into the existing `language` and `languageList` metadata. The slice
covers camel-case `langId`, hyphenated `lang-id`, existing `language-id`, and
camel-case `languageId`, then renders the same values through CSL citation,
bibliography, sort-key, and WordPress bibliography handoff paths.

This slice is recorded as a lane note instead of touching
`lane-status.json`, because the prior merge attempt for this bead conflicted
in aggregate status files. No Pandoc binary, citeproc executable, TeX/browser
engine, Node tooling, online service, live provider, or external validator was
invoked.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- focused `CitationCslProcessorTest` case:
  `normalizes bounded direct csl json lang id aliases into language metadata`
  - `focused 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 6214 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - current checkout remains baseline-red outside this slice:
    `534 test files, 142321 assertions, 8912 failures`
