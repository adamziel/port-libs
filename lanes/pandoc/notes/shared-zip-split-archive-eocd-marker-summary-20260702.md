# Shared ZIP split archive EOCD marker summary

Issue: plib-o4xty

## Summary

`ZipPackage::splitArchivePreflight()` now reports metadata-only EOCD split
markers as structured field/value rows. The summary identifies non-zero
`diskNumber`, non-zero `centralDirectoryDisk`, and `diskEntryCount` values that
do not match `totalEntryCount`, while preserving the existing split-archive issue
codes and disk-start entry summaries.

The structured EOCD rows also flow through `ZipPackage::rawStrictImportPreflight()`
via the existing `splitArchive` handoff, so blocked split/spanned package review
can distinguish package-level EOCD markers from per-entry `diskStart` markers
without reading package payload bytes.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageSplitArchiveDiskStartSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageSplitArchiveDiskStartSummaryTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

No upstream Pandoc runner, external `zip`/`unzip`, office suite, browser, or
network validator was invoked.
