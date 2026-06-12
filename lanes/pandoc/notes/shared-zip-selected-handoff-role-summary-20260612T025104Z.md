# Shared ZIP selected handoff role summary 20260612T025104Z

Slice: `plib-g3ja1`, shared ZIP/OPC package core blocker.

Base: `origin/main` `412827d77a`.

## Scope

Selected ZIP/OPC handoff requests already carry optional `role` labels and the
handoff preflight tracks selected bytes, duplicate requests, raw-name
provenance, missing entries, and blocked entries. This slice adds bounded
per-role rollups so DOCX, EPUB, and ODF package readers can audit which package
roles consumed bytes or failed before exposing payloads.

## Change

`ZipPackage::entryHandoffPreflight()` now returns:

- `requestedRoleCount`
- `roleSummaries`

Each role summary reports request, required/optional, present/missing, handoff,
failed, duplicate-request, unique-entry, compressed-byte, uncompressed-byte,
selected-entry-name, missing-entry-name, failed-entry-name, and issue rollups.
Byte totals are unique per role, so duplicate requests do not inflate role
budgets.

The focused test covers a main document, duplicate attachment requests,
oversized attachment blocking, required sidecar missing, and optional unroled
missing requests.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
  - `No syntax errors detected in lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3899 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69497 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
executed.
