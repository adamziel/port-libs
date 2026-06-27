# Pandoc ODF Manifest Root Extension Provenance

Area: Pandoc ODF/ODT OpenDocument package ingestion

## Summary

ODF/ODT package ingestion now preserves non-manifest namespace extension child
elements that appear directly under `manifest:manifest`. These records carry the
extension element name, namespace URI, prefix, direct attribute count, and direct
child-element count through both compact `OpenDocumentPackage` review and rich
`OdfReader` package provenance.

The parser still rejects unknown direct children in the ODF manifest namespace.
Only extension namespace children are accepted as metadata-only review records,
and package bytes remain governed by the existing exposure policies.

## Coverage

- Added compact package coverage for manifest-root extension children and
  metadata-only identity hash differentiation.
- Added rich reader coverage for document manifest attributes, import report
  package provenance, compact/rich provenance parity, and package identity
  propagation.
- Direct ODF/ODT package-ingestion focused cases: 2 new passing cases.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1896 assertions, 0 failures
- Direct selected `OdfReaderTest` harness run for
  `preserves ODT manifest root extension child provenance in package review`
  - 1 selected test, 20 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderDocumentPartRootAttributesTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php`
  - 4 files, 174 assertions, 0 failures
