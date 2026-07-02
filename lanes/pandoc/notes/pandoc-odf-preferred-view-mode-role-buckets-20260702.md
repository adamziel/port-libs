# Pandoc ODF Preferred View Mode Role Buckets

Slice `plib-oqinw` adds role-level ODF manifest preferred-view-mode provenance.

- Compact `OpenDocumentPackage` and rich `OdfReader` preferred-view-mode summaries now expose role counts, mode-by-role counts, and entry-name lookup maps.
- Individual preferred-view-mode records carry existing package role labels such as `odf-content`, `media-resource`, `package-thumbnail`, `script-package`, and embedded object roles.
- Encoded object package paths remain metadata-only and decode only through existing package-reference fields.
- Direct-format parity accounting: `mappedOdfManifestPreferredViewModeRoleBucketCases=1`, `odfManifestPreferredViewModeRoleBucketAssertions=38`, `phpPass 490 -> 491`, `phpFail=0`.
