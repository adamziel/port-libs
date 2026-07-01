# ODF Package Byte Handoff Missing/Compression Provenance

`plib-3bs7a` adds metadata-only review selection fields to `packageByteHandoff` for compact
`OpenDocumentPackage` and rich `OdfReader` package review. The existing readable
`handoffEntries` stay limited to byte-exposable document/media parts, while the new review
selection records stable ODF-oriented request order across `mimetype`, `META-INF/manifest.xml`,
core XML parts, media resources, manifest-declared missing package parts, and
unsupported-compression package entries.

Focused coverage lives in `OdfReaderTest.php` under the missing/unsupported package handoff
case, asserting that missing parts and unsupported-compression blocks appear in review metadata
without exposing content bytes, and that script/RDF metadata sidecars remain outside both the
readable handoff and review selection.
