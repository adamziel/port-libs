# Pandoc ODF Manifest File-Entry Order Provenance Slice 2026-06-11

Slice: `pandoc-odf-manifest-file-entry-order-20260611T181604Z`, based on current main `8be9a5c45`.

## Scope

This slice stays inside `lanes/pandoc` and covers ODF/OpenDocument package ingestion. It makes manifest `file-entry` source order explicit for review handoff so package reviewers can compare manifest order with ZIP local-header and central-directory order without inferring from array position.

## Implementation

- `OdfReader` now adds `manifestIndex` to each manifest item.
- `packageProvenance.parts[*]` exposes the matching manifest index for ZIP entries declared in the manifest.
- `packageProvenance.manifestFileEntryOrder` lists root, existing, missing, directory, encrypted, and byte-exposable manifest entries in source order.
- Added a focused ODF test with reordered manifest entries and one missing declared media part.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 3954 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 65281 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, `zip`/`unzip`, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Added one focused ODF/OpenDocument package ingestion PASS case with 21 assertions.
- Lane status `phpPass` moves `3097 -> 3098`; `phpFail` remains `0`.
