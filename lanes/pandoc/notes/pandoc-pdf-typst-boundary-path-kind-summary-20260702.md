# Pandoc PDF/Typst Boundary Path Kind Summary

Slice `plib-twabo` adds compact Typst boundary path-kind rollups to `PdfEngineHandoff`.

- Summary fields now expose nonzero path kind, safe path-kind, unsafe path-kind, and path source counts.
- Environment-sourced boundary path variables are preserved as a sorted list.
- Diagnostics include nonzero path-kind and path-source buckets without executing Typst or PDF engines.
- Direct-format parity accounting: `mappedTypstBoundaryPathKindSummaryCases=1`, `typstBoundaryPathKindSummaryAssertions=31`, `phpPass 490 -> 491`, `phpFail=0`.
