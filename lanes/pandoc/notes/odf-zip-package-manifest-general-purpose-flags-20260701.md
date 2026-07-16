# ODF ZIP Package Manifest General-Purpose Flag Provenance

Slice: `plib-xe8bb` / ODF/ODT OpenDocument package ingestion.

This slice carries `ZipPackage::packageManifestPreflight()` general-purpose flag
aggregate metadata through the ODF package ingestion identity surfaces:

- compact `OpenDocumentPackage::summarize().packageInventory`
- compact `OpenDocumentPackage::summarize().packageIdentity`
- rich `OdfReader` package provenance
- rich `OdfReader` package identity

The exposed fields are metadata-only `zipPackageManifestGeneralPurpose...`
values for flag summary count, UTF-8 name entries, data-descriptor entries,
deflate option entries, and deterministic flag summary buckets. Package bytes
remain non-exposable and no external ZIP, office, Pandoc, or validator tooling is
invoked.

Direct-format parity accounting remains active in lane status; this is an ODF
package-ingestion provenance completion and does not claim unrelated direct
format parity movement.
