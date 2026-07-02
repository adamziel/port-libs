# ZIP package manifest entry-comment length buckets

## Slice

`plib-qnva6` adds metadata-only entry-comment length bucket rollups to
`ZipPackage::packageManifestPreflight()`. Commented central-directory entries
now keep an `entryCommentLengthBucket` in `entryCommentSummaries`, and the
package manifest exposes ordered `entryCommentLengthBuckets` plus bucket
summaries for comment byte totals, central-directory review/source-record
bytes, directory roots, extension keys, compression methods, entry names, and
longest-comment entries.

The slice keeps comment bytes non-exposed and does not invoke external ZIP
tools.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 6067 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 11400 assertions, 0 failures`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- conflict-marker scan of changed lane files
