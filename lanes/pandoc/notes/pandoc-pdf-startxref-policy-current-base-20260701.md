# Pandoc PDF Startxref Policy

Slice: `plib-ijwgh`, PDF/Typst boundary provenance.

This slice adds bounded native `PdfEngineHandoff` provenance for produced PDF
`startxref` offsets. Fake-run inspection now summarizes the number of trailer
revisions with `startxref`, missing markers, out-of-bounds offsets, duplicate
offsets, latest offset, target kind buckets, and per-revision target rows.

The policy is exposed on:

- `pdfStartXrefPolicy`
- `artifactProvenanceReview['pdfStartXrefPolicy']`
- `finalPdfStartXrefPolicy` from `fakeRunSequence()`
- diagnostics such as `pdf-byte-startxref-policy:*`,
  `pdf-byte-startxref-target:*`, and specific startxref policy issues

The policy reports provenance and review issues without turning offset concerns
into fake-run execution failures. This keeps existing generated PDF byte
fixtures usable while still surfacing malformed or suspicious xref boundaries
for reviewers.

Focused coverage lives in
`lanes/pandoc/tests/PdfEngineHandoffPdfStartXrefPolicyTest.php`. It covers an
in-bounds xref-table target, an out-of-bounds offset, and a missing `startxref`
marker through the Typst fake-run handoff path.

No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
zip/unzip, Node, Jupyter, external PDF validators, online services, or live
providers are executed. The slice stays inside native PHP fake-runner
provenance for PDF/Typst boundary review.

Manifest additions: `mappedPdfStartXrefPolicyCases = 1` and
`pdfStartXrefPolicyAssertions = 36`.
