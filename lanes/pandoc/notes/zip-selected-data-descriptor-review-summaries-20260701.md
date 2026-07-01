# ZIP Selected Data Descriptor Review Summaries

Date: 2026-07-01
Slice: plib-en267

## Summary

`ZipPackage::entryHandoffPreflight()` selected-entry data descriptor review summaries now expose descriptor value offsets and descriptor end offsets alongside descriptor record offsets. This gives DOCX, EPUB, ODF, and other ZIP/OPC callers a metadata-only byte map for signed and unsigned data descriptors before handing selected package entries to bounded readers.

## Coverage

- `ZipPackageTest.php` now checks selected descriptor review issue counts, entry names by review issue, signed and unsigned descriptor buckets, descriptor byte totals, descriptor offsets, descriptor value offsets, and descriptor end offsets.
- Focused validation passed with `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 file, 6068 assertions, 0 failures.
- Related OPC validation passed with `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`: 1 file, 5333 assertions, 0 failures.
- Broad Pandoc lane validation remains red outside this slice: `php tools/run-tests.php lanes/pandoc/tests` reported 534 files, 142301 assertions, and 8912 failures.

No external Pandoc, office suite, TeX/browser engine, Node, zip/unzip, validators, or live services were invoked.
