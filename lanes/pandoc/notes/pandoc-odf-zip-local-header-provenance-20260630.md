# ODF/ODT ZIP Local Header Provenance - 2026-06-30

Bead: plib-88227

## Scope

ODF/ODT package ingestion now carries ZIP local-header byte layout and local extra-field structure through compact `OpenDocumentPackage` summaries, rich `OdfReader` package provenance, and metadata-only package identity.

The slice keeps payload bytes blocked. It records offsets, local-header lengths, variable field lengths, local extra-field ids/record spans, data-start/end offsets, local CRC/size header values, and contiguous-record checks as review metadata only.

## Direct-Format Parity

Direct-format parity remains active for the Pandoc lane. This slice improves native ODF/ODT package ingestion accounting without invoking Pandoc, office suites, zip/unzip, TeX, browsers, Jupyter, Node tooling, or external validators.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderLocalHeaderPackageProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLocalHeaderPackageProvenanceTest.php`
  - 1 file, 81 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1,896 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderLocalHeaderPackageProvenanceTest.php`
  - 4 files, 224 assertions, 0 failures
