# ODF Package Path Byte-Length Buckets

Date: 2026-07-01
Bead: plib-a5ipn

## Slice

ODF/ODT package ingestion now carries metadata-only package path byte-length buckets through compact `OpenDocumentPackage` inventories and identities plus rich `OdfReader` package provenance, package identity, and document metadata.

The bucket handoff records ordered bucket names, per-bucket counts, entry-name maps, role counts, byte-exposure policy counts, and longest-entry summaries for package paths of 0-8, 9-16, 17-32, 33-64, and over 64 bytes. Per-entry metadata also carries the exact package path byte length and bucket range. This preserves package review provenance without exposing package bytes and without invoking Pandoc, office suites, TeX/browser engines, Node, zip/unzip, external validators, or live services.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePathByteLengthBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePathByteLengthBucketsTest.php`
