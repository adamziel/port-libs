# Pandoc ODF Manifest Package Order Provenance

Slice: `plib-84rk2`
Date: 2026-07-02 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

Compact `OpenDocumentPackage` and rich `OdfReader` package provenance now
expose a metadata-only `manifestPackageOrder` review. The review compares
manifest-declared package part order with ZIP central-directory order, preserving
existing declared paths, missing declared paths, undeclared ZIP entries,
per-part local-header and central-directory positions, order deltas, mismatch
issues, and identity-visible rollups.

Bytes remain governed by existing ODF byte exposure policies. This slice does
not read blocked sidecar bytes, invoke upstream Pandoc, or use office suites,
ZIP tools, external validators, browser engines, TeX engines, or Node tooling.

Accounting:

- `mappedOdfManifestPackageOrderCases`: `1`
- `odfManifestPackageOrderAssertions`: `55`
- `benchmarkDenominator.mapped`: `2317 -> 2318`

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderManifestPackageOrderProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderManifestPackageOrderProvenanceTest.php`
  - 1 test file, 55 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderManifestPackageOrderProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php lanes/pandoc/tests/OdfReaderLocalHeaderPackageProvenanceTest.php lanes/pandoc/tests/OdfReaderDataDescriptorPackageProvenanceTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php`
  - 6 test files, 404 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdfReaderManifestPackageOrderProvenanceTest.php`
  - 3 test files, 7889 assertions, 0 failures
