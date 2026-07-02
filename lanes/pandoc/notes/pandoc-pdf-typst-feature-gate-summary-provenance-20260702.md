# PDF/Typst feature gate summary provenance

Slice: `plib-a5fj2`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now carries compact Typst feature-gate rollups in
`typstBoundarySummary`, including selected feature names, selected raw value,
history and override counts, shadowed `TYPST_FEATURES` details, environment
feature names, and issue counts.

This is native PHP boundary provenance only. It does not execute Pandoc, Typst,
PDF engines, TeX/browser engines, Office suites, Node, `zip`/`unzip`, Jupyter,
or external validators, and it does not change direct-format parity accounting
beyond the focused manifest test count.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstFeatureGateSummaryProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstFeatureGateSummaryProvenanceTest.php`
