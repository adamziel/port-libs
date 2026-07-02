# DOCX content type subtype tree buckets

Bead: `plib-5jd43`
Date: 2026-07-02

## Scope

DOCX/OpenXML package ingestion now summarizes package part content types by MIME subtype tree in `packageProvenance.summary.partContentTypeSubtypeTrees`.

The rollup groups resolved package parts into:

- `vendor` for `vnd.` subtypes
- `personal` for `prs.` subtypes
- `experimental` for `x-` subtypes
- `standard` for ordinary registered-looking subtypes
- `(invalid)` for malformed content types
- `(missing)` for parts without a resolved content type

Each bucket carries part counts, byte totals, relationship and parameterized counts, content-type/source/media/subtype maps, default extensions, override part names, role counts, sorted part names, and largest-part metadata. This is metadata-only package review; it does not expose part payload bytes or invoke external ZIP, Office, Pandoc, browser, or validator tooling.

## Evidence

- Added focused coverage in `lanes/pandoc/tests/DocxOpenXmlContentTypeSubtypeTreeBucketsTest.php`.
- Updated `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` with `mappedDocxContentTypeSubtypeTreeBucketCases=1` and `docxContentTypeSubtypeTreeBucketAssertions=53`.
- Updated `lanes/pandoc/lane-status.json` focused PHP pass count to 491.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlContentTypeSubtypeTreeBucketsTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlContentTypeSubtypeTreeBucketsTest.php` with 1 file, 53 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlContentTypeSubtypeTreeBucketsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` with 2 files, 12,561 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php` with 78 files, 17,021 assertions, 0 failures
- Conflict-marker scan of touched lane files
