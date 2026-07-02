# ODF manifest media type subtype tree buckets

Bead: `plib-ypo5c`
Date: 2026-07-02

## Scope

ODF/ODT package ingestion now carries manifest media-type subtype tree rollups through compact `OpenDocumentPackage`, rich `OdfReader`, document manifest attributes, and metadata-only package identities.

The rollup groups manifest declarations into:

- `vendor` for `vnd.` subtypes
- `personal` for `prs.` subtypes
- `experimental` for `x-` subtypes
- `standard` for ordinary registered-looking subtypes
- `(invalid)` for malformed media types
- `(empty)` for manifest entries without a media type

Each bucket carries manifest item counts, parts, media-type base counts, raw media-type variants, parameterized counts, directory/non-directory counts, missing/encrypted counts, declared-size issue counts, and byte totals already exposed by the manifest media-type summary. This is metadata-only package review; it does not expose payload bytes or invoke external ZIP, Office, Pandoc, browser, TeX, or validator tooling.

## Evidence

- Added focused coverage in `lanes/pandoc/tests/OdfManifestMediaTypeSubtypeTreeBucketsTest.php`.
- Updated `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` with `mappedOdfManifestMediaTypeSubtypeTreeBucketCases=1` and `odfManifestMediaTypeSubtypeTreeBucketAssertions=50`.
- Updated `lanes/pandoc/lane-status.json` focused PHP pass count to 491.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfManifestMediaTypeSubtypeTreeBucketsTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestMediaTypeSubtypeTreeBucketsTest.php` with 1 file, 50 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestMediaTypeSubtypeTreeBucketsTest.php lanes/pandoc/tests/OdfManifestMediaTypeSummaryCompactParityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` with 4 files, 7,813 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/Odf*.php lanes/pandoc/tests/OpenDocumentPackage*.php lanes/pandoc/tests/OpenDocumentReaderTest.php` with 61 files, 12,295 assertions, 0 failures
- Conflict-marker scan of touched lane files
