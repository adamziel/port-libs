# BibTeX/CSL Xdata Provenance Handoff

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T033604Z`
Base accepted HEAD: `ee63dde665f0edb8e5a49e4c834317a2631d73ee`

## Behavior

- `BibtexCslParser` now preserves explicit BibLaTeX `xdata` key order on child entries.
- Known referenced `@xdata` packets are exposed as data-only CSL summaries without bibliography manager execution.
- Missing or non-`@xdata` references are preserved as missing xdata diagnostics.
- `CitationCslProcessor` normalizes direct and parsed xdata provenance into review metadata, CSL text variables, and default bibliography diagnostics when a missing xdata packet needs review.
- The WordPress handoff smoke verifies citation text and bibliography output keep xdata provenance visible.

## Evidence

- Red-first focused command: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 3537 assertions, 1 failures`
  - Failure: parsed `.bib` items lacked `xdataKeys` metadata.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3559 assertions, 0 failures`
  - Delta: `+1` PHP PASS case and `+25` focused assertions for this slice.
- Example smoke command: `php lanes/pandoc/examples/wordpress-bibtex-csl-xdata-provenance-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-xdata-provenance-handoff self-test passed`
- Root harness: not run for isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths. Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This handoff does not repeat accepted BibTeX/CSL inheritance, source-file policy, entryset, related-entry, xref, label-prefix/sortinit, source locator, date rendering, or name rendering slices. It only adds bounded xdata provenance preservation and missing xdata review diagnostics.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.
