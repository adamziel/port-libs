# EPUB Malformed Nav Document Ingestion Slice

Bead: `plib-le9hr`

Current base: `b963243f6`

## Summary

EPUB3 package ingestion now keeps package construction alive when a declared XHTML `nav` manifest item cannot be parsed. The selected navigation source is preserved as `nav`, the malformed part emits an `invalid-nav-document` diagnostic, and package validation plus WordPress handoff expose the document parse diagnostic instead of aborting before metadata, manifest, spine, and package provenance can be reviewed.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` -> 1 file, 1462 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 65879 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
