# ODF Package Area Identity Maps - 2026-07-01

Work item: `plib-g9920`

## Scope

- Compact `OpenDocumentPackage` package identities now carry
  `packageAreaSummaries`, `packagePathsByPackageArea`, and
  `packagePathsByPathDepth` from the package inventory.
- Rich `OdfReader` package identities now carry the same area and path-depth
  lookup maps in both the identity hash payload and the public
  `packageIdentity` metadata.
- The focused ODF package provenance fixture verifies compact inventory to
  identity parity, rich provenance to identity parity, and document manifest
  package identity parity for the new maps.

## Boundary

This is metadata-only ODF/ODT package ingestion work. It does not expose
blocked package bytes and does not invoke Pandoc, office suites, TeX/browser
engines, `zip`/`unzip`, `ZipArchive`, Node tooling, online services, live
providers, or external validators.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderTest.php`
