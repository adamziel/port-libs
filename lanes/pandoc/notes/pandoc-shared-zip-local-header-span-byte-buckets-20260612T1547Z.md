# Shared ZIP Local Header Span Byte Buckets

Slice: shared ZIP/OPC package primitive on current main `82e220973c`.

## Implementation

`ZipPackage::localHeaderSpanPreflight()` now aggregates byte bucket provenance for
the local-header span scanner before package instantiation:

- available local-header entry count;
- local header bytes;
- compressed payload bytes claimed by the central directory;
- data descriptor bytes;
- claimed local record bytes;
- unclaimed byte totals and affected entry counts;
- local records contiguous with the next local header or central directory.

The summary is propagated unchanged through `rawStrictImportPreflight()`, so
DOCX, EPUB, and ODF handoff code can review package layout risk without
constructing a `ZipPackage` or exposing package bytes.

## Scope

This is bounded to native PHP ZIP/OPC package layout provenance under
`lanes/pandoc`. It does not invoke Pandoc, office suites, TeX/PDF engines,
browser renderers, `zip`/`unzip`, `ZipArchive`, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4232 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 72058 assertions, 0 failures

## Accounting

- `phpPass`: `3235 -> 3236`
- `phpFail`: `0`
- `mappedZipLocalHeaderSpanByteBucketCases`: `1`
- `zipLocalHeaderSpanByteBucketAssertions`: `27`
- mapped denominator: `3255 -> 3256`
