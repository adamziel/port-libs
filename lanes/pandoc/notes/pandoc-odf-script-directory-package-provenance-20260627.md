# Pandoc ODF Script Directory Package Provenance

Area: Pandoc ODF/ODT OpenDocument package ingestion

This slice extends compact `OpenDocumentPackage` package-script review metadata
with declared script directory roots. `packageScripts` now keeps existing
payload `items` semantics unchanged while also exposing `directoryCount` and
metadata-only `directories` rows for roots such as `Basic/` and `Scripts/`.

The directory rows carry script container and directory-kind provenance,
manifest path/reference fields, ZIP directory record metadata, and
`directory-entry-no-bytes` byte-exposure policy. They remain out of document
media handoff and do not expose macro or script payload bytes.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 1 test file, 1,897 assertions, and 0 failures.
