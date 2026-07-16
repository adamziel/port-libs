# OPC ZIP Source Record Manifest

Slice: `plib-q7s4m`

## Summary

`OpcRelationshipGraph::preflightZipEntryManifest()` and
`preflightZipCentralDirectoryManifest()` now expose a compact
`zip-opc-source-record-manifest-v1` summary for ZIP/OPC package review.

The manifest carries per-entry local-record, compressed-data, data-descriptor,
and central-directory-record hashes and byte counts, plus package totals and an
exactness flag for raw central-directory scans where a local span may not be
bounded. Parsed package manifests also expose the backing
`zipPackageManifestSha256`.

This keeps DOCX/OpenXML, EPUB3, ODF, and raw OPC callers on native PHP ZIP/OPC
metadata before XML package graph construction without exposing entry payload
bytes or invoking external archive tooling.

## Validation

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 test file, 4,912 assertions, 0 failures
