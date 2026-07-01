# ODF ZIP Order Mismatch Role Summary

This slice adds metadata-only OpenDocument ZIP order mismatch summaries by package role.

- Compact `OpenDocumentPackage` inventory and identity now expose central-directory/local-header mismatch role counts, byte buckets, and entry/index summaries.
- Rich `OdfReader` package provenance and identity expose the same fields with the same shape.
- `OdfZipOrderMismatchRoleSummaryTest` covers a reordered ODT package across compact inventory, compact identity, rich provenance, and rich identity without shelling out to ZIP/Pandoc/office validators.
