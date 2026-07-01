# PDF engine byte boundary policy

## Scope

- Added a `pdfByteBoundaryPolicy` aggregate for fake PDF engine handoffs.
- The aggregate summarizes header, EOF marker, and startxref policy statuses and issues.
- Artifact provenance now carries the aggregate policy and flags a single review issue when any boundary policy needs review.

## Verification

- `PdfEngineHandoffPdfByteBoundaryPolicyTest` covers ok and review packets without executing Typst or TeX tools.
