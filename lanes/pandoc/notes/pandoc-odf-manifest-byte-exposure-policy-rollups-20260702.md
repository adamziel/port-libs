# ODF manifest byte-exposure policy rollups

Date: 2026-07-02
Hook: plib-7iltj

## Summary

ODF/ODT package ingestion now carries metadata-only manifest byte-exposure policy rollups through compact `OpenDocumentPackage` manifest review and package identity summaries. The rich `OdfReader` package identity also exposes the manifest policy item count and ordered item list already collected in package provenance.

The rollups group manifest-declared entries by policy such as `package-root-no-bytes`, `package-bytes-exposable`, script/configuration/font/RDF/layout/signature/database blockers, sidecar blockers, directory entries, and embedded-object blockers without exposing package payload bytes.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`

No external Pandoc, office suite, TeX/browser engine, Typst, Node, zip/unzip tools, validators, or live services were invoked.
