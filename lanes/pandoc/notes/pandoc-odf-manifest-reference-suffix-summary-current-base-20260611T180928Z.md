# Pandoc ODF Manifest Reference Suffix Summary Slice 2026-06-11

Slice: `pandoc-odf-manifest-reference-suffix-summary-20260611T180928Z`, based on current main `a478c52c1`.

## Scope

This slice stays inside `lanes/pandoc` and covers ODF/OpenDocument package ingestion. It adds package-level provenance for manifest full-path URI suffixes so review packets can inspect query and fragment-bearing manifest declarations, including missing package parts, without rewalking individual manifest entries.

## Implementation

- `OdfReader::packageProvenance()` now aggregates manifest full-path suffix, query, and fragment counts.
- Package provenance now exposes `manifestPartReferenceSuffixItems` with the raw `fullPath`, resolved package `part`, parsed suffix/query/fragment fields, media type, existence, directory, encryption, and byte-exposure state.
- Added a focused ODF package test covering existing content/styles references plus a missing image declaration with query/fragment suffix provenance.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 3933 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 64815 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, unzip/zip, Jupyter, Node tooling, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Added one focused ODF/OpenDocument package ingestion PASS case.
- Lane status `phpPass` moves `3088 -> 3089`; `phpFail` remains `0`.
