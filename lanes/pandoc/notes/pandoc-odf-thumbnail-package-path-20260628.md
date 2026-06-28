# ODF thumbnail package path provenance

Area: Pandoc ODF/ODT OpenDocument package ingestion

This slice keeps `Thumbnails/` package previews metadata-only while aligning
rich and compact package-review rows around stable package paths:

- `OdfReader` package thumbnail rows now include `packagePath`, matching the
  compact `OpenDocumentPackage` sidecar review surface.
- `OpenDocumentPackage` manifest review now aggregates thumbnail package
  entries with `packageThumbnailPartCount` and `packageThumbnailItems`.
- The focused regression verifies declared, missing, and undeclared thumbnail
  rows, package identity flags, inventory roles, compact/rich parity, and
  WordPress non-exposure of thumbnail bytes.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderThumbnailPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderThumbnailPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`

Focused result: 3 files, 2,296 assertions, 0 failures.
