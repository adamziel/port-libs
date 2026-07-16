# ODF Compact Manifest Missing Media Type

Bead: `plib-f9q10`

`OpenDocumentPackage` now retains non-directory manifest file entries that omit
`manifest:media-type` instead of aborting package ingestion. The entry receives
`odf-manifest-file-entry-missing-media-type`, remains visible in manifest review
and inventory summaries, and uses `missing-media-type-bytes-blocked` so package
bytes are not exposed through compact review handoff.
