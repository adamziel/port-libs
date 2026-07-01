# ODF Configuration Sidecar Rollups

Bead: `plib-g35ih`

## Summary

Compact `OpenDocumentPackage` configuration sidecar summaries now expose
deterministic aggregate rollups for `Configurations2/` package review:

- `configurationAreaCounts`
- `configurationKindCounts`
- `configurationMediaTypeBaseCounts` and `configurationMediaTypeBases`
- `configurationByteExposurePolicyCounts`

The rollups are derived from existing metadata-only configuration review items,
so configuration package bytes remain blocked and no document-media exposure
policy changes.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 2286 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfCompactConfigurationBytePolicyParityTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - `2 test files, 5352 assertions, 0 failures`
- Post-rebase gate: `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfCompactConfigurationBytePolicyParityTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - `3 test files, 7638 assertions, 0 failures`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check`
