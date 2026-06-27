# Shared ZIP Zero-Byte Handoff Kinds

Slice: `plib-15tbc`, shared ZIP/OPC package core blocker, 2026-06-27.

`ZipPackage::entryHandoffPreflight()` now reports package-part-kind summaries for
zero-byte selected entries and zero-byte readable handoff entries. This keeps
empty files and directory markers visible by package role before DOCX, EPUB, and
ODT readers expose selected package bytes.

The new `selectedZeroBytePackagePartKindSummaries` and
`handoffZeroBytePackagePartKindSummaries` reuse the existing package-kind
classifier, so blocked zero-byte directory markers remain selected-only while
readable zero-byte media, metadata, and relationship parts stay grouped for
review.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 5,024 assertions, 0 failures

No Pandoc, office suite, TeX/browser engine, `zip`, `unzip`, `ZipArchive`,
Jupyter, Node tooling, external validator, online service, live provider test,
or live-service provider test was invoked.
