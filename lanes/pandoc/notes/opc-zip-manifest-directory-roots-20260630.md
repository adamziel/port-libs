# OPC ZIP manifest directory-root summaries

Slice: `plib-79ojw`, shared ZIP/OPC package primitives.

## Summary

`OpcRelationshipGraph::preflightZipEntryManifest()` and
`OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now report
package-part directory-root provenance before XML package handoff.

The new metadata includes:

- `packagePartDirectoryRootCount`;
- `packagePartDirectoryRootCounts`;
- `entryNamesByPackagePartDirectoryRoot`;
- `packagePartDirectoryRootSummaries`.

Directory roots use the same package-area shape as selected ZIP handoff review:
root-level parts are grouped under `/`, while package areas such as `_rels/`,
`docProps/`, `customXml/`, `word/`, `_xmlsignatures/`, `META-INF/`, or `OEBPS/`
stay separate. The raw central-directory preflight reports the same grouping
without constructing a `ZipPackage` or reading part payload bytes.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed: 1 test file, 4,833 assertions, 0 failures.

No Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, Node tooling, live
services, or external validators were invoked.

## Non-Overlap

This does not repeat accepted extension, compression, content-type,
relationship-part load, local-header order, or central-directory source-record
provenance. It only adds shared OPC package-area rollups for importer review
gates and keeps package bytes metadata-only.
