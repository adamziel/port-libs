# Pandoc ODF Signature Reference Suffix Provenance Slice 2026-06-11

Slice: `pandoc-odf-signature-reference-suffix-provenance-20260611T175945Z`, based on current main `0b4dca730`.

## Scope

This slice stays inside `lanes/pandoc` and covers ODF/OpenDocument package ingestion. It preserves parsed query and fragment suffix provenance for XML signature `dsig:Reference` URI targets that resolve to package parts, while keeping existing same-document, external, unsafe, missing, undeclared, and encrypted target classification intact.

## Implementation

- `OdfReader::signatureReferenceTargetMetadata()` now resolves signature package targets through the existing manifest package reference parser.
- Package-part signature references now expose `partReference`, `partSuffix`, `partQuery`, and `partFragment`.
- Signature reference summaries now aggregate package-part suffix, query, and fragment counts at both signature-part and package levels.
- Added a focused ODF package test covering existing, missing, query-bearing, and fragment-bearing signature package targets.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 3906 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 64656 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, unzip/zip, Jupyter, Node tooling, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Added one focused ODF/OpenDocument package ingestion PASS case.
- Lane status `phpPass` moves `3086 -> 3087`; `phpFail` remains `0`.
