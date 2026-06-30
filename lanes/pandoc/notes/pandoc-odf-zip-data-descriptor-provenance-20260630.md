# Pandoc ODF ZIP Data Descriptor Provenance

Slice: `plib-8r2qz`
Date: 2026-06-30 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Behavior

ODF/ODT package ingestion now carries ZIP general-purpose flag and data
descriptor provenance through compact `OpenDocumentPackage` summaries and rich
`OdfReader` package provenance. Streamed package entries using ZIP data
descriptors now expose metadata-only counters, signed/unsigned descriptor
counts, descriptor byte length, descriptor match status, zero local-header
placeholder flags, and per-part descriptor CRC/size metadata.

The metadata is included in package identity inputs so streamed-entry packaging
changes remain visible to review gates. Package bytes remain governed by the
existing ODF byte-exposure policies; descriptor metadata does not expose
document, media, script, signature, or sidecar payload bytes.

No Pandoc binary, office suite, zip/unzip CLI, TeX/browser/Typst engine,
external validator, online service, live provider test, or live-service
provider test was invoked.

## Accounting

- Focused PHP behavior coverage: `+1` ODF/ODT package-ingestion test case.
- Focused assertions: `+57`.
- Direct-format parity remains active; this slice closes a package-ingestion
  provenance gap and does not change the broader unsupported format denominator.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderDataDescriptorPackageProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDataDescriptorPackageProvenanceTest.php`
  - 1 test file, 57 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 1896 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderDataDescriptorPackageProvenanceTest.php`
  - 4 test files, 200 assertions, 0 failures
