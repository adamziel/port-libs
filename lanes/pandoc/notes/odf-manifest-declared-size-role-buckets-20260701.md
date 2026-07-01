# ODF manifest declared-size role buckets

This slice adds metadata-only rollups for `manifest:size` values grouped by ODF package role. Compact manifest review, compact package identity, rich package provenance, rich package identity, and the document manifest identity now expose matching:

- `manifestDeclaredSizeRoleCounts`
- `manifestDeclaredSizeRoleByteLengths`
- `manifestDeclaredSizeRoleMismatchCounts`
- `manifestDeclaredSizeRoleExistingCounts`
- `manifestDeclaredSizeRoleMissingCounts`
- `manifestDeclaredSizeRoleSummaries`

The rollups are derived from already parsed manifest entries and do not read external tools or expose blocked package bytes. `OdfManifestDeclaredSizeRoleBucketsTest` covers content, present media, missing media, and script sidecar declarations across compact and rich ingestion paths.
