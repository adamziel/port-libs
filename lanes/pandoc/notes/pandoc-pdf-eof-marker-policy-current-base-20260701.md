# Pandoc PDF EOF Marker Policy

Slice: `plib-13yrc`, PDF/Typst boundary provenance.

This slice adds a bounded native `PdfEngineHandoff` policy for produced PDF
`%%EOF` markers. The fake runner now records total EOF marker count, a bounded
offset sample, the last marker offset, trailing byte counts, whether the
complete-trailer rule passed, repeated marker status, and review issues.

The policy is exposed on:

- `pdfEofMarkerPolicy`
- `artifactProvenanceReview['pdfEofMarkerPolicy']`
- `finalPdfEofMarkerPolicy` from `fakeRunSequence()`
- diagnostics such as `pdf-byte-eof-marker-policy:*`,
  `pdf-byte-eof-markers:*`, and specific EOF policy issues

Focused coverage lives in
`lanes/pandoc/tests/PdfEngineHandoffPdfEofMarkerPolicyTest.php`. It covers a
valid Typst PDF handoff with repeated EOF markers and a truncated handoff where
the final EOF marker is followed by non-whitespace bytes.

No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
zip/unzip, Node, Jupyter, external PDF validators, online services, or live
providers are executed. The slice stays inside native PHP fake-runner
provenance for PDF/Typst boundary review.

Manifest additions: `mappedPdfEofMarkerPolicyCases = 1` and
`pdfEofMarkerPolicyAssertions = 32`.
