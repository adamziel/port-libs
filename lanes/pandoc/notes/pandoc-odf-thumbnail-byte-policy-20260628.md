# ODF package thumbnail byte policy

2026-06-28 `plib-qijd8`

## Slice

Declared `Thumbnails/` package previews are now treated as metadata-only package-thumbnail parts across both ODF package ingestion paths:

- `OdfReader` rich manifest/provenance/identity rows carry `thumbnailPackagePart`.
- `OpenDocumentPackage` compact manifest review, package inventory, and identity rows carry `thumbnailPackagePart`.
- Manifest byte exposure for thumbnails is blocked under `package-thumbnail-bytes-blocked`; encrypted thumbnails continue to use `encrypted-resource-bytes-blocked`.
- Package thumbnail summary rows keep size/CRC metadata for review but report `canExposeBytes=false` and the blocked-byte policy.

This does not add a new upstream mapped ODF/ODT case or change direct-format parity counts; it tightens package-ingestion provenance for an existing thumbnail package slice.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` -> 1 file, 1,911 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` -> thumbnail cases pass; command remains red with the known 22 unrelated `OdfReaderTest.php` handoff expectation failures recorded in lane status
