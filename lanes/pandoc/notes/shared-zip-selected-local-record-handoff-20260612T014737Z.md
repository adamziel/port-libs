# Shared ZIP Selected Local Record Handoff

Bead: `plib-foe37`
Slice: `pandoc-shared-zip-package-core-blocker-20260612T014737Z`
Base: current main `65bad4e34f`

## Scope

This slice keeps selected ZIP/OPC package handoff accounting in the shared
`ZipPackage::entryHandoffPreflight()` primitive before DOCX, EPUB, or ODF
readers receive selected package bytes.

## Implementation

- Added selected local record aggregate provenance:
  `selectedLocalHeaderBytes`, `selectedLocalDataDescriptorBytes`,
  `selectedLocalRecordBytes`, `selectedLocalByteWindowStart`,
  `selectedLocalByteWindowEnd`, `selectedLocalByteWindowBytes`, and
  `selectedLocalByteWindowHasInterveningBytes`.
- Added per-request present-entry local record provenance:
  `dataDescriptorLength`, `localRecordEnd`, and `localRecordBytes`.
- Reused existing bounded local-header and data-descriptor metadata; no reader
  byte exposure or accept/reject policy changed.

## Test Accounting

- Added one focused `ZipPackageTest` PASS case:
  `preflights selected zip package local record windows before reader handoff`.
- Expanded selected handoff assertions for contiguous and non-contiguous
  selected byte windows.
- Lane counters: `mappedZipSelectedLocalRecordHandoffCases = 1`,
  `zipSelectedLocalRecordHandoffAssertions = 72`.
- `phpPass` moves `3168 -> 3169`; `phpFail` remains `0`.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3830 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68866 assertions, 0 failures`

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
