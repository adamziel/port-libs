# Pandoc ODT Compact RDF Sidecar Metadata

Bead: `plib-l3hdl`
Base: `0dfe5caf66`
Date: 2026-06-12 UTC

`OpenDocumentPackage::summarize()` now reports RDF metadata sidecars through a
compact `packageRdfMetadata` review surface. The package review keeps declared,
undeclared, missing, encrypted, and invalid-media-type RDF sidecars visible with
byte length, compressed length, compression method, CRC32, issue codes, and
`package-rdf-metadata` inventory roles while keeping RDF sidecars out of
document media handoff.

The fixture covers manifest-declared `application/rdf+xml`, filename-based
`manifest.rdf`, missing package parts, encrypted sidecars, and undeclared
`manifest.rdf` package entries. This is metadata-only package ingestion; it does
not parse RDF triples or expose sidecars as document media.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 697 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69117 assertions, 0 failures`

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
