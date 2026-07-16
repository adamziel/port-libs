# ZIP Package Manifest Path Segment Positions - 2026-07-01

## Scope

- Added shared path segment position provenance to
  `ZipPackage::packageManifestPreflight()`.
- Each manifest entry now carries `pathSegmentPositionReviews` alongside
  existing `pathSegments`, `pathSegmentCount`, and `directoryDepth` metadata.
- Package manifests now summarize `only`, `first`, `middle`, and `last`
  segment positions with occurrence counts, entry counts, segment buckets,
  segment-index buckets, entry names, and source-record byte rollups.
- The new fields are included in `zip-package-manifest-v1` hash payloads so
  package manifest identity reflects this review surface.
- `OpcRelationshipGraph::preflightZipEntryManifest()` passes the shared entry
  reviews and aggregate segment-position summaries through to OPC handoff
  consumers.

## Boundary

This stays inside native PHP shared ZIP/OPC package primitives. It does not
read additional entry payload bytes and does not invoke Pandoc, office suites,
TeX/browser engines, `zip`/`unzip`, `ZipArchive`, Node tooling, online
services, live providers, or external validators.
