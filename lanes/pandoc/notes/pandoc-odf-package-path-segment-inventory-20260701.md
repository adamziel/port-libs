# ODF Package Path Segment Inventory

Implemented a bounded OpenDocument package provenance slice for ZIP path segment summaries.

- `OpenDocumentPackage::summarize()` now reports package path segment counts, case-folded segment counts, repeated segment summaries, and case-folded segment duplicate summaries in both `packageInventory` and `packageIdentity`.
- `OdfReader::readPackage()` exposes the same rollups through rich `importReport.manifest.packageProvenance` and the derived package identity.
- Added a focused ODT fixture covering mixed-case directory segments, repeated `content.xml` leaf segments, and an undeclared sidecar path without exposing package bytes.

Validation target: `OdfPackagePathSegmentInventoryTest.php` with related ODF package provenance tests.
