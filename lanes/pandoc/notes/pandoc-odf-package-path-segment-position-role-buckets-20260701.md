# ODF Package Path Segment Position Role Buckets

Slice: `plib-kl6up`

## Summary

`OpenDocumentPackage` and `OdfReader` now aggregate decoded ODF package path
segment positions by package role and byte-exposure policy:

- `packagePathSegmentPositionRoleCounts` groups first/middle/last/only decoded
  package path positions by ODF package role;
- `packagePathSegmentPositionByteExposurePolicyCounts` groups the same
  positions by byte-exposure policy;
- `entryNamesByPackagePathSegmentPositionRole` and
  `entryNamesByPackagePathSegmentPositionByteExposurePolicy` carry sorted entry
  names through compact package inventory, compact identity, rich reader
  provenance, rich identity, document provenance, and document identity.

This complements the ZIP package manifest segment-position buckets by using the
reader's decoded package path shape, so URI/name normalization and ODF package
role decisions can be reviewed without exposing blocked sidecar bytes.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePathSegmentPositionRoleBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePathSegmentPositionRoleBucketsTest.php`
  - `1 test files, 34 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePathSegmentPositionRoleBucketsTest.php lanes/pandoc/tests/OdfZipPackagePathSegmentPositionRoleBucketsTest.php lanes/pandoc/tests/OdfPackagePathDepthRoleBucketsTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
  - `4 test files, 194 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 2276 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestPathShapeRichParityTest.php`
  - `2 test files, 100 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, `zip`/`unzip` command, `ZipArchive`, online service, live
provider test, or payload-expanding external tool was invoked.
