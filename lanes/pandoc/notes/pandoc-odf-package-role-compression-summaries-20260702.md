# ODF/ODT Package Role Compression Summaries - 2026-07-02

Bead: `plib-pfezh`

## Slice

ODF/ODT package role summaries now carry compression-method review buckets through both compact `OpenDocumentPackage` summaries and rich `OdfReader` package provenance.

The added metadata is still package-review only:

- `compressionMethodCounts`
- `compressionMethodByteLengths`
- `compressionMethodCompressedByteLengths`

These buckets are present on the existing top-level segment, directory-depth, and role summary records, including `packageRoleSummaries`. They distinguish stored, deflated, unsupported, and unknown compression names without exposing package payload bytes.

## Coverage

Focused regressions cover mixed stored, deflated, and unsupported entries across manifest-declared and media-resource package roles in:

- `lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `lanes/pandoc/tests/OdfReaderTest.php`

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` - 1 file, 2446 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` - 1 file, 5398 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
