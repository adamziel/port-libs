# Shared OPC raw local-header metadata manifest provenance

Work item: `plib-oduq3`

## Summary

`OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now carries raw ZIP local-header fixed-field metadata into the OPC ZIP manifest preflight. This keeps malformed packages diagnosable before `ZipPackage::fromString()` can construct a package object.

The raw manifest now exposes local-header metadata validity, mismatch counts, per-entry mismatch issues, and central-versus-local values for version-needed, general-purpose flags, compression method, DOS modification time/date, CRC32, compressed size, uncompressed size, and data-descriptor placeholder state.

ZIP64 central-directory size sentinels remain accounted for as unknown byte counts instead of being double-counted as ordinary local-header size mismatches. The raw `ZipPackage::localHeaderMetadataPreflight()` payload remains available under `localHeaderMetadata` for low-level review.

## Validation

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Focused shared OPC validation passed with 4,718 assertions and 0 failures.

Direct-format parity remains active in lane status. This slice extends native ZIP/OPC package review without invoking Pandoc, office suites, unzip/zip, external validators, browser engines, TeX, or other external tools.
