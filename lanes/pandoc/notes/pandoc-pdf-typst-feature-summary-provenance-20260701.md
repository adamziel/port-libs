# Pandoc PDF/Typst Feature Summary Provenance

Slice: `plib-juc7o`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now carries Typst feature-gate selections into
`typstBoundarySummary` instead of exposing only the selected feature count.
The summary records selected CLI features, safe/invalid history counts,
feature override counts, shadowed `TYPST_FEATURES` entries, environment feature
names, and feature-specific issue rollups. The values are preserved through
plan output, fake-run artifact review, and fake-run sequence summaries without
executing Typst or any PDF engine.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstFeatureSummaryProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstFeatureSummaryProvenanceTest.php`
  - `1 test files, 56 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3804 assertions, 0 failures`

Accounting:

- `mappedTypstFeatureSummaryProvenanceCases`: `1`
- `typstFeatureSummaryProvenanceAssertions`: `56`
- `benchmarkDenominator.mapped`: `2883 -> 2884`

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, office suite,
`zip`/`unzip`, external validator, online service, live provider test, or
live-service provider test was invoked.
