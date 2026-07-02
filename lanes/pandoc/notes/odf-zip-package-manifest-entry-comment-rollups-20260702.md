# ODF ZIP Package Manifest Entry Comment Rollups

2026-07-02 `plib-83qkt`

- `ZipPackage::packageManifestPreflight()` now reports metadata-only entry-comment rollups for central-directory ZIP comments, including commented entry names, summary count, source-record byte accounting, and per-entry hashes/offsets.
- `OpenDocumentPackage` and `OdfReader` carry those rollups into ODF/ODT compact inventory, compact identity, rich package provenance, rich package identity, and document package provenance.
- Focused coverage extends the shared ZIP comment fixture and the existing commented ODT package fixtures, preserving central-directory comment review without exposing package bytes.
- Validation does not invoke external Pandoc, office suites, TeX/browser engines, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services.
