# Pandoc ODF Rich Package Leaf Name Provenance

Slice: `plib-1chnl`
Date: 2026-07-02 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

`OdfReader` package provenance now mirrors compact package inventory leaf-name
rollups for rich ODF imports. Each package entry carries a normalized package
path profile (`directoryRoot`, `parentDirectory`, `leafName`, `entryBaseName`,
`entryExtension`, `entryExtensionKey`, and `pathDepth`), and package provenance
plus package identity expose `leafNameSummaries`, `sharedLeafNameSummaries`,
and shared-leaf counts.

This is metadata-only review data. It does not expose blocked package bytes,
change package acceptance, invoke upstream Pandoc, or use office suites, ZIP
tools, external validators, browser engines, TeX engines, or Node tooling.

Accounting:

- `mappedOdfPackageLeafNameCases`: `1`
- `odfPackageLeafNameAssertions`: `29`
- `benchmarkDenominator.mapped`: `2317 -> 2318`

Verification:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageLeafNameSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageLeafNameSummaryTest.php`
  - 1 test file, 29 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageLeafNameSummaryTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfPackageAreaDepthProvenanceTest.php`
  - 4 test files, 231 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdfReaderPackageLeafNameSummaryTest.php`
  - 3 test files, 7863 assertions, 0 failures
