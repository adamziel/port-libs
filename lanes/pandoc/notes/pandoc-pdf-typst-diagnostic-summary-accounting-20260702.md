# PDF/Typst Diagnostic Summary Accounting

Slice: `plib-06ep7`, PDF/Typst boundary provenance.

This slice adds standalone mapped coverage for Typst diagnostic-output summary
provenance in `PdfEngineHandoff`. The fixture verifies selected diagnostic
format/color controls, invalid history entries, override counts, boundary matrix
details, fake-run artifact provenance, and sequence carry-forward without
executing Typst, Pandoc, PDF engines, TeX/browser engines, external validators,
or live services.

Accounting:

- `benchmarkDenominator.mapped`: `2883 -> 2884`
- `mappedTypstDiagnosticSummaryProvenanceCases`: `1`
- `typstDiagnosticSummaryProvenanceAssertions`: `40`

Validation:

- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticSummaryProvenanceTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticSummaryProvenanceTest.php lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/notes/pandoc-pdf-typst-diagnostic-summary-accounting-20260702.md`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticSummaryProvenanceTest.php`
  passed with 1 file, 40 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypst*.php`
  passed with 11 files, 452 assertions, and 0 failures.
