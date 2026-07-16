# DOCX OpenXML ZIP manifest case-insensitive collision provenance

This slice carries `ZipPackage::packageManifestPreflight()` case-insensitive
entry-name collision fields through DOCX OpenXML package ingestion.

- `packageProvenance.zipPackage` now exposes manifest collision counts,
  booleans, groups, and entries under the existing `packageManifest*` field
  family.
- `packageProvenance.summary` mirrors those fields under the
  `zipPackageManifest*` summary family for handoff and direct-format parity
  review.
- Loaded DOCX package parts now retain the ZIP manifest case-fold key,
  equivalent ZIP entry names, collision flag, and collision issues as
  `zip*` inventory fields.

The focused reader fixture includes same-path, different-case media entries to
verify the native PHP DOCX ingest path without shelling out to external tools.
