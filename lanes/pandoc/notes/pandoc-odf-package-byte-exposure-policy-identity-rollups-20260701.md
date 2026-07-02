# ODF package byte-exposure policy identity rollups

Date: 2026-07-01
Hook: plib-0rouj

## Summary

ODF/ODT package identity summaries now carry the existing metadata-only package byte-exposure policy rollups, including ordered policy item lists and byte/compressed-byte totals.

Compact `OpenDocumentPackage` identity now mirrors `packageInventory` policy item counts, items, and byte-length maps. Rich `OdfReader` package identity now mirrors `packageProvenance` package-part policy item counts, items, and byte-length maps.

The item records preserve path/part, roles, declaration state, policy, and byte-exposure flags without exposing package payload bytes.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services were invoked.
