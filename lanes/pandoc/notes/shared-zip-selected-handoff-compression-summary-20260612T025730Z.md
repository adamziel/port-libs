# Shared ZIP selected handoff compression summary 20260612T025730Z

Slice: `plib-sguio`, shared ZIP/OPC package core blocker.

Base: `origin/main` `713ba0d252`.

## Scope

Selected ZIP/OPC handoff preflight already reports requested entries, selected
byte totals, expansion ratios, role summaries, and per-entry compression
metadata. This slice adds bounded selected-entry file/directory and compression
method rollups before payload bytes are read.

## Change

`ZipPackage::entryHandoffPreflight()` now returns selected unique-entry
accounting for:

- file and directory counts
- stored, deflated, supported, and unsupported compression-method counts
- compression method byte buckets
- unsupported selected compression-method entries

Duplicate requests still do not inflate selected-entry budgets. The focused
test covers a selected deflated document, stored media, stored directory,
oversized stored attachment, unsupported selected attachment, and missing
required/optional entries.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
  - `No syntax errors detected in lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3908 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69619 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
executed.
