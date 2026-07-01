# Pandoc PDF/Typst Safe Feature History Slice

Slice: `plib-t18sc`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves safe repeated Typst `--features` values in
`featureGateHistory`, not only histories with invalid entries. This keeps
review provenance for individually safe feature sets that are overridden at the
handoff boundary, and carries the history into boundary summary counts, the
feature-gates matrix case, fake-run artifact review, and fake-run sequence
summaries without executing Typst or any PDF engine.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Isolated `PdfEngineHandoffTest.php` closure
  `preserves typst safe feature gate override history without executing`
  passed with `23` assertions and `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  remains baseline-red with one unrelated first-test LaTeX source expectation
  failure; the new feature-history case passed in that run.

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, office suite,
`zip`/`unzip`, external validator, online service, live provider test, or
live-service provider test was invoked.
