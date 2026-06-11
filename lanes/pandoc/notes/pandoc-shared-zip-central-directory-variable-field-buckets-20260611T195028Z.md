# pandoc-shared-zip-central-directory-variable-field-buckets-20260611T195028Z

## Slice

Shared ZIP/OPC package handoff now exposes central-directory variable-field byte buckets for native package review.

## What changed

- `ZipPackage::centralDirectoryVariableFieldsPreflight()` now reports deterministic byte buckets for central-directory names, central extra fields, entry comments, and the EOCD package comment.
- The same preflight now exposes review-field entry rollups and the largest per-entry variable-field record, preserving offsets and lengths already discovered during the bounded central-directory scan.
- Raw strict import preflight carries the same summary through `centralDirectoryVariableFields`, so DOCX/EPUB/ODF review queues can inspect this provenance before package payload handoff.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3209 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65650 assertions, 0 failures

## Accounting

- `phpPass`: 3108 -> 3109
- `phpFail`: 0
- `mappedZipCentralDirectoryVariableFieldBucketCases`: 1
- `zipCentralDirectoryVariableFieldBucketAssertions`: 26

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
