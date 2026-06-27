# Shared ZIP Readable Handoff Compression Buckets

Slice: `plib-zps13`

## Scope

`ZipPackage::entryHandoffPreflight()` now reports compression-method buckets for the readable handoff subset, not only the selected package subset. This lets DOCX, EPUB, and ODT importers compare all selected ZIP members against the members that actually passed size, kind, duplicate, and readability gates before any package bytes are exposed.

The new readable handoff fields are:

- `handoffCompressionMethodBucketCount`
- `handoffStoredEntryCount`
- `handoffDeflatedEntryCount`
- `handoffUnsupportedCompressionMethodCount`
- `handoffSupportedCompressionMethodEntryCount`
- `handoffCompressionMethodBuckets`
- `handoffUnsupportedCompressionMethodEntries`

The selected side also exposes `selectedCompressionMethodBucketCount` to match the existing selected bucket list.

## Boundary

Blocked oversized entries and unreadable unsupported-compression entries remain in selected summaries only. They do not contribute to readable handoff compression buckets, keeping the package byte-exposure boundary unchanged. No Pandoc, office suite, zip/unzip CLI, external validator, browser, TeX, or Node tooling is invoked.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Focused result: `1 test files, 5036 assertions, 0 failures`.

## Accounting

- `lane-status.json` `phpPass`: `460 -> 461`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2306 -> 2307`
- Added `mappedSharedZipReadableHandoffCompressionBucketCases = 1`
