# PDF/Typst diagnostic output selected summary provenance

Slice: `plib-s3nw9`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now carries selected Typst diagnostic-output controls in
`typstBoundarySummary`:

- selected diagnostic format, machine-readable status, and safety;
- selected diagnostic color, ANSI-color mode, and safety;
- format/color override counts;
- diagnostic-output issue count and issue codes.

The summary mirrors the existing `diagnostic-output` boundary matrix details so
review handoff can inspect the selected diagnostic output boundary without
executing Typst/PDF engines or reading engine outputs.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticOutputSummaryProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstDiagnosticOutputSummaryProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoff*.php`
