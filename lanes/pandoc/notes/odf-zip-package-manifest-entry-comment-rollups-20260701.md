# ODF ZIP Package Manifest Entry Comment Rollups

2026-07-01 `plib-83qkt`

- `OpenDocumentPackage` and `OdfReader` now carry `ZipPackage::packageManifestPreflight()` entry-comment rollups into ODF/ODT compact inventory, compact identity, rich package provenance, rich package identity, and document package provenance.
- The mapped fields are `zipPackageManifestHasEntryComments`, `zipPackageManifestCommentedEntryNames`, `zipPackageManifestEntryCommentSummaryCount`, `zipPackageManifestEntryCommentSourceRecordBytes`, and `zipPackageManifestEntryCommentSummaries`.
- Focused coverage extends the existing commented ODT package fixtures and aggregate provenance test, preserving metadata-only central-directory comment review and source-record accounting without exposing package bytes.
- Validation does not invoke external Pandoc, office suites, TeX/browser engines, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services.
