# ODF Package CRC32 Aggregate Provenance

Date: 2026-07-01
Bead: plib-i22d0

## Slice

ODF/ODT package ingestion now carries metadata-only package CRC32 aggregate provenance through compact `OpenDocumentPackage` inventory/identity and rich `OdfReader` package provenance, package identity, and document metadata.

The aggregate groups non-directory ZIP entries by central-directory CRC32 and records entry names, duplicate counts, byte/compressed/source-record byte totals, compression-method buckets, byte-exposure policy buckets, manifest media-type/family buckets, manifest-declared/undeclared counts, exposable/blocked counts, and role buckets. This lets importers audit duplicate or reused package payloads without exposing package bytes and without shelling out to Pandoc, office suites, zip/unzip, browsers, Node, or validators.

## Validation

- `php -l lanes/pandoc/src/OdfPackageCrc32Inventory.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageCrc32AggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCrc32AggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCrc32AggregateProvenanceTest.php lanes/pandoc/tests/OdfZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/OdfZipTimestampSourcesTest.php lanes/pandoc/tests/OdfZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCrc32AggregateProvenanceTest.php lanes/pandoc/tests/OdfManifestPackageCoverageProvenanceTest.php lanes/pandoc/tests/OdfManifestMediaTypeSummaryCompactParityTest.php lanes/pandoc/tests/OdfPackageSidecarAggregateCountsTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php lanes/pandoc/tests/OdfPackageIdentityStemLookupMapsTest.php lanes/pandoc/tests/OdfZipNamePolicyProvenanceTest.php lanes/pandoc/tests/OdfZipPackagePathSegmentPositionRoleBucketsTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderZipTimestampProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php lanes/pandoc/tests/OdfZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/OdfZipTimestampSourcesTest.php lanes/pandoc/tests/OdfZipSourceRecordDirectoryRootsTest.php`
