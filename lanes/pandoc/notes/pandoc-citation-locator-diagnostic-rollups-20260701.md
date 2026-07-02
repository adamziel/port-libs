# Citation locator diagnostic rollups

Issue: `plib-klv2`

The native CSL citation handoff now exposes citation locator diagnostic rollups
alongside the existing diagnostic rows, reason strings, and display summaries.

Added metadata-only fields on normalized citations and citation groups:

- `cslLocatorDiagnosticCount`
- `cslLocatorDiagnosticSeverityCounts`
- `cslLocatorDiagnosticSeveritySummary`

Added CSL text variables for in-style review output:

- `citation-locator-diagnostic-count`
- `locator-diagnostic-count`
- `citation-locator-diagnostic-severity-summary`
- `locator-diagnostic-severity-summary`

This keeps locator review state visible for direct PHP CSL, Markdown, Pandoc
JSON, and WordPress handoff paths without invoking external citeproc, BibTeX,
Biber, Pandoc, validators, or live services.
