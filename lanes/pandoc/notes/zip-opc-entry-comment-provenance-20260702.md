# ZIP/OPC Entry Comment Provenance

- `ZipPackage::packageManifestPreflight()` now rolls commented central-directory entries up by directory root and package-part extension without exposing comment bytes.
- OPC ZIP entry and raw central-directory manifests now carry matching entry-comment provenance summaries, including exact source-record byte accounting when local layout data is available.
- Focused coverage locks package-manifest grouping and direct/raw OPC manifest parity for commented entries.
