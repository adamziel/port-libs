# OPC ZIP Package Comment Provenance - 2026-06-30

## Slice

`plib-gb9f9` carries ZIP EOCD package-comment source provenance through the shared OPC ZIP manifest preflights.

## What changed

- `ZipPackage::centralDirectoryVariableFieldsPreflight()` now hashes the EOCD package comment byte range with SHA-256 while preserving the existing offset, length, end, and presence fields.
- `OpcRelationshipGraph::preflightZipEntryManifest()` exposes package-comment offset, length/bytes, end, SHA-256, and presence metadata for constructed `ZipPackage` instances before XML/package handoff.
- `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` exposes the same metadata for raw central-directory OPC manifest preflights before package construction.
- `OpenPackagingConventionsTest.php` now covers constructed and raw manifest parity using a ZIP package-level comment without exposing comment bytes.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` - 1 file, 4,916 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` - 2 files, 11,025 assertions, 0 failures

Direct-format parity remains tracked in `lane-status.json`; this slice stays within shared ZIP/OPC metadata and does not invoke external ZIP, office, browser, TeX, Pandoc, or Node tooling.
