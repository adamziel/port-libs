# EPUB metadata meta-property vocabulary diagnostics

## Slice

`pandoc-epub-metadata-meta-property-vocabulary` maps one bounded native PHP EPUB3 package ingestion case for OPF metadata `<meta property>` vocabulary review.

The package summary now reports metadata meta-property vocabulary entries for:

- reserved package prefixes such as `dcterms:modified`
- package-declared prefixes such as `review:source-record`
- absolute IRI properties with URL fragments
- malformed absolute URL fragments
- malformed property tokens
- unknown prefix references

The same vocabulary diagnostics are carried into package validation and the WordPress import metadata handoff, so review tooling can reject or inspect unresolved metadata properties without shelling out to Pandoc or external EPUB validators.

## Evidence

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file, 2308 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 81231 assertions, 0 failures

## Boundaries

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Broader EPUB package ingestion parity remains partial while remaining package surfaces, conversion behavior, CSS cascade/export policy, and external resource behavior are mapped in later bounded slices.
