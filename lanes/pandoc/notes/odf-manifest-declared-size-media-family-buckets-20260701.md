# ODF manifest declared-size media-family buckets

2026-07-01 plib-pzjej: ODF/ODT package ingestion now carries metadata-only manifest declared-size rollups by `manifestMediaFamily` through compact `OpenDocumentPackage`, rich `OdfReader` package provenance, package identity, and document manifest metadata.

The slice complements the existing declared-size role summary by grouping declared byte counts, mismatch counts, existing counts, missing counts, and affected package parts for media families such as `image`, `script`, and `xml`. Missing manifest-declared parts with `manifest:size` remain visible in the family rollups without reading package bytes or invoking external office/ZIP/Pandoc tools.
