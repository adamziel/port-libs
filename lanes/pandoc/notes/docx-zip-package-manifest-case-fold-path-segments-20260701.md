# DOCX ZIP package manifest case-fold path segment aggregates

DOCX/OpenXML package provenance now mirrors the shared `ZipPackage::packageManifestPreflight()` path-segment rollups for ZIP-backed packages. The mirror carries raw path-segment summaries, case-fold path-segment summaries, and path-position summaries through `packageProvenance.summary`, `zipPackage.packageManifest*`, and package identity.

The slice is metadata-only. It exposes counts, byte totals, segment variants, directory-root buckets, extension buckets, compression-method buckets, and entry names without exposing package payload bytes or invoking external ZIP, Office, Pandoc, or validator tooling.
