# ODF Embedded Database Object Package Provenance

Bead: `plib-t0jqy`
Date: 2026-07-01 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion
Base: `32750f463`

## Behavior

`OpenDocumentPackage` and `OdfReader` now classify
`application/vnd.oasis.opendocument.database` manifest roots as embedded
OpenDocument object packages. Database object roots are reported with
`objectType=database`, and contained parts inherit the existing
`embedded-object-package-bytes-blocked` policy so database payload bytes are not
exposed as normal document media.

This is package-ingestion provenance only. It does not parse embedded database
schemas, execute database queries, render database objects, or change visible
ODT document content.

No Pandoc binary, office suite, TeX/browser engine, unzip/zip, Jupyter, Node
tooling, external validator, online service, or live provider test was invoked.

## Direct-Format Parity Accounting

- Direct embedded database rendering support: `0 -> 0`
- Mapped ODF/ODT embedded database package cases: `0 -> 1`
- Focused embedded database package assertions: `0 -> 44`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderEmbeddedDatabaseObjectPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderEmbeddedDatabaseObjectPackageTest.php`
  - `1 test files, 44 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderEmbeddedDatabaseObjectPackageTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php`
  - `4 test files, 2056 assertions, 0 failures`
