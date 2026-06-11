# Pandoc ODF Manifest Encryption Summary Provenance Slice 2026-06-11

Slice: `pandoc-odf-manifest-encryption-summary-20260611T182810Z`, based on current main `c0cfa42e2`.

## Scope

This slice stays inside `lanes/pandoc` and covers ODF/OpenDocument package ingestion. It summarizes encrypted `META-INF/manifest.xml` entries for review handoff while preserving the existing per-entry encryption metadata and encrypted-byte withholding policy.

## Implementation

- `OdfReader` now derives `encryptionSummary` from encrypted manifest items.
- The summary is exposed on document manifest metadata, import-report manifest metadata, and the top-level import-report encryption section.
- Summary provenance includes encrypted parts, missing-part count, declared/stored/compressed byte totals, checksum types, algorithm names, key-derivation names, start-key-generation names, per-item rows, and encrypted media-type buckets.
- Added a focused ODF package test covering two encrypted manifest entries with distinct checksum and algorithm provenance.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 3973 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 65131 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, `zip`/`unzip`, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Added one focused ODF/OpenDocument package ingestion PASS case with 41 assertions.
- Lane status `phpPass` moves `3093 -> 3094`; `phpFail` remains `0`.
