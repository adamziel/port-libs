# Shared ZIP/OPC local-header archive-extra provenance

Slice: `plib-h6vwa` (`20260611T235905Z`)

## Scope

`ZipPackage::localHeaderVariableFieldsPreflight()` now skips and accounts for
archive extra data records encountered inside the central-directory stream
before local-header variable fields are scanned. This keeps local name,
local extra-field, and data-start byte provenance available to raw strict ZIP
package review even when an inter-entry archive extra data record later blocks
`ZipPackage` construction.

The summary now reports skipped archive-extra record count, byte totals, and
per-record offsets/issues. `rawStrictImportPreflight()` carries that same
local-header summary and no longer reduces this layout to a
`raw-local-header-variable-fields-preflight-failed` diagnostic.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed after rebase on `origin/main` `34a94da3c9`: 1 test files,
  3651 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after rebase on `origin/main` `34a94da3c9`: 44 test files,
  67774 assertions, 0 failures.

## Accounting

- Added one focused ZIP package PASS case:
  `preflights zip local header variable fields across inter-entry archive extra records`.
- `lane-status.json` `phpPass`: `3151 -> 3152`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3225 -> 3226`.
- New counters: `mappedZipLocalHeaderArchiveExtraRecordCases = 1`,
  `zipLocalHeaderArchiveExtraRecordAssertions = 21`.

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
