# DOCX Package Thumbnail Issue Rollup

Slice: `plib-crnlj` DOCX/OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now carries metadata-only package-thumbnail issue rollups
through `packageThumbnails` and `packageProvenance.summary`. Thumbnail
preflights expose issue-code counts plus relationship IDs, internal target
parts, and external targets grouped by issue code, while preserving the existing
per-thumbnail issue records and byte-exposure policy.

This slice does not expose package payload bytes beyond existing bounded
metadata, execute external Pandoc, invoke office-suite tooling, run
TeX/browser engines, use Typst, Jupyter, Node, ZIP/unzip, validators, or live
services.

Post-rebase validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageThumbnailIssueRollupTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageThumbnailIssueRollupTest.php`
  - Result: 1 file, 11 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageThumbnailIssueRollupTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php`
  - Result: 3 files, 12,574 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php`
  - Result: 78 files, 16,979 assertions, 0 failures.

Manifest accounting:

- `mappedDocxPackageThumbnailIssueRollupCases`: `0 -> 1`.
- `docxPackageThumbnailIssueRollupAssertions`: `0 -> 11`.
- `benchmarkDenominator.mapped`: `2883 -> 2884`.
