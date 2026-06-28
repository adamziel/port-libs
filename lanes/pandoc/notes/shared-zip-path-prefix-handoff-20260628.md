# Shared ZIP Path Prefix Handoff

`ZipPackage::entryHandoffPreflight()` now carries `pathPrefixes` for each
selected package entry and emits `selectedPathPrefixSummaries` plus
`handoffPathPrefixSummaries`.

The summaries are cumulative path buckets, so package readers can compare the
full selected package tree against the readable byte-exposure subset before
DOCX, EPUB, or ODT ingestion. Directory entries remain scoped to their parent
prefix, while nested file entries are counted under each ancestor prefix.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 5,395 assertions, 0 failures

## Accounting

- Added `sharedZipSelectedHandoffPathPrefixCases = 1`
- Added `mappedSharedZipSelectedHandoffPathPrefixCases = 1`
