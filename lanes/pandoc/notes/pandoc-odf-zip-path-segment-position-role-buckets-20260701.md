# ODF ZIP path segment position role buckets

Slice: `plib-ik9ap`

## Summary

`OpenDocumentPackage` and `OdfReader` now derive ODF package-level aggregate
maps from existing ZIP package manifest path segment position reviews:

- `zipPackageManifestPathSegmentPositionRoleCounts` groups first/middle/last/only
  ZIP entry path positions by ODF package roles;
- `zipPackageManifestPathSegmentPositionByteExposurePolicyCounts` groups the same
  positions by bounded byte exposure policy;
- `entryNamesByZipPackageManifestPathSegmentPositionRole` and
  `entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy` preserve
  sorted entry-name provenance for compact package inventory, compact identity,
  rich reader package provenance, and rich package identity.

This keeps ODF/ODT package review handoffs aligned with shared ZIP provenance
while showing how package path depth maps to ODF roles and byte-exposure
decisions before any document payload bytes are exposed.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackagePathSegmentPositionRoleBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackagePathSegmentPositionRoleBucketsTest.php`
  - `1 test files, 28 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackagePathSegmentPositionRoleBucketsTest.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - `6 test files, 8263 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, `zip`/`unzip` command, `ZipArchive`, online service, live
provider test, or payload-expanding external tool was invoked.
