# ODF/ODT package identity ZIP timestamp regression

Date: 2026-06-30

This slice adds focused regression coverage that rich `OdfReader` and compact
`OpenDocumentPackage` deterministic package identity snapshots carry ZIP
modification timestamp provenance already present on manifest and package review
rows.

Coverage added:
- rich identity manifest/package entries retain `zipModifiedAt`,
  `zipTimestampSource`, `zipLocalModifiedAt`, and `zipLocalTimestampSource`;
- compact identity manifest/package entries retain the same timestamp fields;
- both identity summaries expose ZIP timestamp counters;
- identity hashes change when only a package entry modified time changes.

Validation:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 2 files, 7,300 assertions, 0 failures.
