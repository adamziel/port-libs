# ODF ZIP Platform Attribute Issue Buckets

Date: 2026-07-02
Bead: plib-qtaxj

## Slice

ODF/ODT package ingestion now carries metadata-only ZIP platform-attribute issue buckets through compact `OpenDocumentPackage` inventory/identity and rich `OdfReader` package provenance, package identity, and document metadata.

The buckets group existing per-entry issue codes, including DOS hidden attributes, internal text attributes, and Unix executable files, by package role and manifest media family. The implementation only summarizes existing ZIP metadata and does not expose package payload bytes or invoke external ZIP tools.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPlatformAttributeIssueBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPlatformAttributeIssueBucketsTest.php` passed with 1 file, 69 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPlatformAttributeIssueBucketsTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php` passed with 2 files, 144 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPlatformAttributeIssueBucketsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` passed with 3 files, 7,655 assertions, 0 failures.
