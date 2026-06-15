# Pandoc ODF Manifest Encryption Summary

Area: Pandoc ODF/ODT OpenDocument package ingestion

## Summary

Native ODF/ODT package review now aggregates manifest encryption provenance across
both package readers:

- `OpenDocumentPackage::summarize()` exposes `manifestEncryption` and mirrors it
  under `manifestReview.manifestEncryption`.
- `OdfReader::readPackage()` exposes the same summary in document manifest
  metadata, `importReport.manifest.encryption`,
  `importReport.encryption.summary`, and package provenance.

The summary reports encrypted package parts, encryption record counts,
checksum-type buckets, algorithm buckets, key-derivation buckets,
start-key-generation buckets, unknown child element-name buckets, and issue-code
buckets for repeated or malformed encryption records. Encrypted package bytes
remain blocked from media exposure.

## Metrics

- `phpPass`: `3648 -> 3650`
- `phpFail`: `0`
- mapped upstream cases: `3685 -> 3687`
- `mappedOdfManifestEncryptionSummaryCases`: `2`
- `odfManifestEncryptionSummaryAssertions`: `106`
- ODF/ODT ship-ready local mapped cases: `78 -> 80`
- ODF/ODT focused assertions: `6550 -> 6693`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`: 2 files, 6393 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 5 files, 6693 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 86002 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
