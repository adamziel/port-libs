# Shared ZIP Selected Platform Attribute Handoff

Slice: `plib-nfncl`, shared ZIP/OPC package primitives.

This slice extends `ZipPackage::entryHandoffPreflight()` so selected package
entries preserve platform attribute provenance before DOCX, EPUB, or ODF
readers expose bytes. The handoff rows now include creator host/version,
central external and internal attributes, DOS attribute names, Unix mode/type
and permission summaries, executable/hidden/internal-text issue rollups, and
missing-entry null provenance.

The metadata is review-only and does not change byte exposure or decompression
behavior. It reuses existing native PHP `ZipPackage` and `ZipPackageEntry`
attribute helpers and does not shell out to Pandoc, office suites, zip/unzip,
ZipArchive, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

Verification after final rebase onto `e65eb3cbf`:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 4414 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73580 assertions, 0 failures`

Metric accounting:

- `phpPass`: `3278 -> 3279`
- `phpFail`: `0`
- `mappedZipSelectedPlatformAttributeProvenanceCases`: `1`
- `zipSelectedPlatformAttributeProvenanceAssertions`: `64`
