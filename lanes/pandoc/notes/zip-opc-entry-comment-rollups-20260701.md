# Shared OPC ZIP Entry Comment Rollups

2026-07-01 `plib-wwh93`

- `OpcRelationshipGraph::preflightZipEntryManifest()` now carries ZIP package-manifest entry-comment rollups from `ZipPackage::packageManifestPreflight()` into the constructed-package OPC manifest summary.
- `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now computes the same metadata-only rollups directly from raw central-directory entry fields, preserving raw-byte preflight coverage before package construction.
- Focused coverage extends the existing OPC central-directory source-record fixture with one commented package part, asserting package-manifest parity, raw/object parity, source-record byte accounting, and `zip-entry-comment-source-metadata-only` exposure policy.
- Validation does not invoke external Pandoc, office suites, TeX/browser engines, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services.
