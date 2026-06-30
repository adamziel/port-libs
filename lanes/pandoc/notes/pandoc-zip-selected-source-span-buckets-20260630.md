# Pandoc ZIP Selected Source Span Buckets

Slice: `plib-dv8j0`
Date: 2026-06-30

`ZipPackage::entryHandoffPreflight()` now summarizes selected entry source byte
span provenance into named buckets for reader handoff review. The buckets cover
source records, local records/headers, compressed data, data descriptors, and
central-directory record/review fields, including entry counts, zero-byte
counts, byte totals, and entry-name provenance. This lets DOCX/EPUB/ODT import
gates audit which ZIP record regions are represented without walking every
selected entry record.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with
  4,911 assertions and 0 failures.
