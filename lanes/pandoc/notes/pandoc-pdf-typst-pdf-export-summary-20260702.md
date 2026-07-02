# Pandoc PDF/Typst PDF Export Summary

Slice: `plib-neber`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst boundary summary
provenance for PDF export controls. The existing `pdfExport` payload and
`pdf-export-controls` matrix case already preserved the control details; the
summary now also exposes stable aggregate names, per-control presence counts,
flag totals, history/override totals, and per-control issue counts for
reviewer handoff.

Behavior:

- Adds `pdfExportControlNames`, `pdfExportControlCounts`,
  `pdfExportFlagCount`, `pdfExportHistoryEntryCount`,
  `pdfExportOverrideCount`, `pdfExportIssueCount`, and
  `pdfExportIssueCounts` to `typstBoundarySummary`.
- Adds summary diagnostics for PDF export control and issue totals.
- Preserves the existing `typstBoundaryProvenance` shape and carries the
  expanded summary through plan output, fake-run artifact review, and fake-run
  sequence summaries without invoking external engines.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstPdfExportSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstPdfExportSummaryTest.php`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, TeX/PDF engine,
browser renderer, external validator, online service, live provider test, or
live-service provider test is required for the focused slice.
