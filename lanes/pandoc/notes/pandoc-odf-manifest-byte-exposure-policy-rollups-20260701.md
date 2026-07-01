# ODF manifest byte-exposure policy rollups

Date: 2026-07-01
Hook: plib-7iltj

## Summary

ODF/ODT package ingestion now carries metadata-only manifest byte-exposure policy rollups through compact `OpenDocumentPackage` manifest review and package identity summaries. The rich `OdfReader` package identity also exposes the manifest policy item count and ordered item list already collected in package provenance.

The rollups group manifest-declared entries by policy such as `package-root-no-bytes`, `package-bytes-exposable`, `script-package-bytes-blocked`, sidecar blockers, directory entries, and embedded-object blockers without exposing package payload bytes.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageSidecarIdentityCountsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageSidecarIdentityCountsTest.php`

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services were invoked.
