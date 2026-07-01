# PDF/Typst sidecar summary provenance

Slice: `plib-av5v9`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now carries compact Typst sidecar-output counters in
`typstBoundarySummary`:

- selected dependency output path/kind/safety and dependency format;
- selected timings output path/kind/safety;
- dependency output, dependency format, and timings output history counts;
- sidecar override counts split by dependency output, dependency format, and
  timings output;
- invalid sidecar buckets and sidecar-specific issue counts.

The summary complements the existing `sidecar-outputs` boundary matrix case so
package review can see sidecar boundary pressure without reading external
resources or executing Typst/PDF engines. Direct-format parity remains active in
the manifest current-slice accounting.

Validation targets:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstSidecarSummaryProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstSidecarSummaryProvenanceTest.php`
