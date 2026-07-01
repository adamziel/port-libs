# OPC ZIP Manifest Path Segment Summary

`OpcRelationshipGraph::preflightZipEntryManifest()` and
`preflightZipCentralDirectoryManifest()` now carry package-part path segment
metadata before XML/package handoff:

- per-entry `partNamePathSegmentCount`;
- `packagePartPathSegmentCounts`;
- `entryNamesByPackagePartPathSegmentCount`;
- `packagePartPathSegmentSummaries` with byte totals, role counts, handoff-kind
  counts, entry names, and OPC part names.

The summary is metadata-only. It is derived from canonical OPC part names and ZIP
central-directory metadata, so package payload bytes remain unread by the raw
central-directory preflight and no Pandoc, office, zip/unzip, browser, TeX, Node,
or external validator is invoked.

This complements the accepted package-part extension inventory and selected ZIP
handoff directory-root summaries without repeating either slice. The new field
answers how deeply package parts are nested, which lets importer review split
root package records, root-owned part records, relationship/media subtrees, and
deeper nested payloads before any XML body parsing.

Validation on 2026-06-30:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with 1 test file, 4,685 assertions, and 0 failures.

Direct-format parity remains active in `lane-status.json`; this slice only closes
a shared ZIP/OPC package metadata blocker.
