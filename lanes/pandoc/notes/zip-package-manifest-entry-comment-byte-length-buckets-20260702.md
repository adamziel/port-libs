# ZIP package manifest entry comment byte-length buckets

Date: 2026-07-02
Slice: `plib-jb1oa`

`ZipPackage::packageManifestPreflight()` now carries metadata-only entry
comment byte-length bucket rollups for shared ZIP/OPC package review. Commented
central-directory entries are grouped into `up-to-15-bytes`,
`16-to-63-bytes`, `64-to-127-bytes`, and `128-plus-bytes` buckets with entry
names, raw comment byte totals, central-directory/source-record byte totals,
directory roots, extension keys, compression methods, and longest-comment entry
names.

This preserves comment-size provenance without exposing comment bytes and
without invoking Pandoc, office suites, `zip`/`unzip`, Node tooling, browser
engines, or external validators.

Direct-format parity accounting:

- `mappedSharedZipPackageManifestEntryCommentCases`: `1 -> 2`
- `sharedZipPackageManifestEntryCommentAssertions`: `10 -> 157`

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 6076 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 2 files, 11409 assertions, 0 failures
