# ODF Package Path Segment Name Characters

Bead: `plib-vj6k4`
Date: 2026-07-02

## Scope

ODF/ODT rich package ingestion now mirrors the compact OpenDocumentPackage package path-segment name character review fields through `OdfReader` package provenance, package identity, and document metadata.

The metadata-only rollup covers uppercase, whitespace, percent-encoded octet, and non-ASCII package path segments, including per-part flags, review records, flag-to-segment maps, flag-to-part maps, and per-segment occurrence/path-position summaries. No package payload bytes are exposed through the review fields.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePathSegmentNameCharactersTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePathSegmentNameCharactersTest.php` - 1 file, 239 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePathSegmentNameCharactersTest.php lanes/pandoc/tests/OdfPackagePathByteLengthBucketsTest.php lanes/pandoc/tests/OdfPackagePartRawExtensionInventoryTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` - 5 files, 7,976 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Node, zip/unzip, validators, or live services were invoked.
