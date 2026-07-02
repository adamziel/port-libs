# Pandoc shared ZIP local-header offset buckets

Bead: plib-xjtsp
Date: 2026-07-02

## Slice

`ZipPackage::packageManifestPreflight()` now carries metadata-only local-header offset bucket rollups for shared ZIP/OPC package review. The bucket surface groups entries by the byte offset of their local header:

- `start-of-archive`
- `1-to-255-bytes`
- `256-to-1023-bytes`
- `1024-plus-bytes`

Each bucket reports entry counts, file/directory splits, compressed and uncompressed byte totals, local-header/local-record/source-record byte totals, data-descriptor counts and bytes, first/last local-header offsets, directory roots, compression methods, and entry names. Per-entry manifest rows also expose `localHeaderOffsetBucket`.

## Constraints

The slice does not expose package payload bytes and does not invoke external Pandoc, office tools, ZIP tools, validators, or live services. It reuses the existing in-memory ZIP fixture builder and the shared `ZipPackage` manifest path used by OPC preflight code.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
