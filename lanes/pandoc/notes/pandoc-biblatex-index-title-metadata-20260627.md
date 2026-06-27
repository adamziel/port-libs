# Pandoc Citation/CSL Legacy BibLaTeX Index Title Metadata - 2026-06-27

Slice: carry legacy BibLaTeX `indextitle` and `indexsorttitle` metadata through the native PHP CSL handoff without invoking upstream Pandoc, citeproc, Haskell/Cabal, browser tooling, or attachment/identifier fetches.

Implementation:

- `lanes/pandoc/src/BibtexCslProcessor.php` maps `indextitle`/`index-title` to CSL `index-title` and `indexsorttitle`/`index-sort-title` to CSL `index-sort-title`.
- If `index-sort-title` is absent and `index-title` is present, the processor derives the sort metadata from `index-title`, matching the newer parser handoff.
- Direct bibliography text labels expose `Index title` and `Index sort title`.

Coverage:

- `lanes/pandoc/tests/BibtexCslProcessorTest.php` extends the legacy shorthand/sort metadata case with index title assertions and styled CSL variables.
- A focused case covers direct BibLaTeX aliases, fallback derivation, crossref inheritance, raw field provenance, direct bibliography text, citation handoff, styled CSL rendering, and WordPress bibliography output.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
- Result: `1 test files, 721 assertions, 0 failures`

Accounting:

- `phpPass`: 461 -> 462
- `benchmarkDenominator.mapped`: 2305 -> 2306
- `mappedLegacyBiblatexIndexTitleMetadataCases`: 1
