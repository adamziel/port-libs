# ODF Package Byte Exposure Policy Rollups

Slice: `pandoc-odf-package-byte-exposure-policy-rollups-20260614`

Current base: `57d859f652`

## Summary

Native `OdfReader` package provenance now exposes deterministic byte-exposure
policy rollups for ODF/ODT package review:

- `manifestByteExposurePolicyCounts` and `manifestByteExposurePolicyItems`
  summarize policy decisions across manifest file-entry rows.
- `packagePartByteExposurePolicyCounts` and
  `packagePartByteExposurePolicyItems` summarize policy decisions across actual
  ZIP package inventory parts.
- Undeclared ZIP entries now carry `undeclared-package-entry-no-bytes` in
  `packageProvenance.parts`, matching the existing `undeclaredEntries` review
  policy without exposing undeclared bytes.

This stays inside native PHP package ingestion and does not invoke Pandoc,
office suites, `zip`/`unzip`, `ZipArchive`, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Verification

```bash
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Results:

- Focused `OdfReaderTest.php`: 1 file, 4682 assertions, 0 failures.
- Full `lanes/pandoc/tests`: 46 files, 81306 assertions, 0 failures.

## Accounting

- `phpPass`: `3473 -> 3474`
- `phpFail`: `0`
- `mappedOdfPackageByteExposurePolicyRollupCases`: `1`
- `odfPackageByteExposurePolicyRollupAssertions`: `7`
